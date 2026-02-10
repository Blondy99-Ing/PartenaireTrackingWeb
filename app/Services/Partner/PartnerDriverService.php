<?php

namespace App\Services\Partner;

use App\Models\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PartnerDriverService
{
    private const DRIVER_ROLE_SLUG = 'utilisateur_secondaire';

    public function listDrivers(User $partner)
    {
        return User::query()
            ->with('role')
            ->where('partner_id', $partner->id)
            ->whereHas('role', fn($q) => $q->where('slug', self::DRIVER_ROLE_SLUG))
            ->latest()
            ->get();
    }

    public function createDriver(User $partner, array $data): User
    {
        $roleId = Role::where('slug', self::DRIVER_ROLE_SLUG)->value('id');

        if (!$roleId) {
            // si rôle absent => erreur claire
            throw new ConflictHttpException("Le rôle '".self::DRIVER_ROLE_SLUG."' n'existe pas.");
        }

        // Anti-doublon multi-format
        $this->assertPhoneAvailable($data['phone']);
        $this->assertEmailAvailable($data['email'] ?? null);

        return DB::transaction(function () use ($partner, $data, $roleId) {

            $photoPath = null;
            if (!empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $disk = config('media.disk', 'public');
                $photoPath = $data['photo']->store('users/photos', $disk);
            }

            $payload = [
                'nom'        => $data['nom'],
                'prenom'     => $data['prenom'],
                'phone'      => $data['phone'], // déjà normalisé +237...
                'email'      => $data['email'] ?? null,
                'ville'      => $data['ville'] ?? null,
                'quartier'   => $data['quartier'] ?? null,
                'photo'      => $photoPath,
                'password'   => Hash::make($data['password']),

                // imposé
                'role_id'    => $roleId,
                'partner_id' => $partner->id,
                'created_by' => $partner->id,

                // obligatoire chez toi
                'user_unique_id' => $this->generateUserUniqueId(),
            ];

            try {
                return User::create($payload)->load('role');
            } catch (QueryException $e) {
                // sécurité (race condition) => 1062 duplicate
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->throwConflictFromDuplicate($e->getMessage());
                }
                throw $e;
            }
        });
    }

    public function updateDriver(User $partner, int $driverId, array $data): User
    {
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);

        // Anti-doublon (en excluant le driver)
        $this->assertPhoneAvailable($data['phone'], $driver->id);
        $this->assertEmailAvailable($data['email'] ?? null, $driver->id);

        return DB::transaction(function () use ($driver, $data) {

            if (!empty($data['photo']) && $data['photo'] instanceof UploadedFile) {
                $disk = config('media.disk', 'public');
                if ($driver->photo) Storage::disk($disk)->delete($driver->photo);
                $driver->photo = $data['photo']->store('users/photos', $disk);
            }

            $driver->nom      = $data['nom'];
            $driver->prenom   = $data['prenom'];
            $driver->phone    = $data['phone'];
            $driver->email    = $data['email'] ?? null;
            $driver->ville    = $data['ville'] ?? null;
            $driver->quartier = $data['quartier'] ?? null;

            if (!empty($data['password'])) {
                $driver->password = Hash::make($data['password']);
            }

            try {
                $driver->save();
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->throwConflictFromDuplicate($e->getMessage());
                }
                throw $e;
            }

            return $driver->load('role');
        });
    }

    public function deleteDriver(User $partner, int $driverId): void
    {
        $driver = $this->findOrFailDriverOfPartner($partner, $driverId);

        DB::transaction(function () use ($driver) {
            $disk = config('media.disk', 'public');
            if ($driver->photo) Storage::disk($disk)->delete($driver->photo);
            $driver->delete();
        });
    }

    private function findOrFailDriverOfPartner(User $partner, int $driverId): User
    {
        return User::query()
            ->with('role')
            ->where('id', $driverId)
            ->where('partner_id', $partner->id)
            ->whereHas('role', fn($q) => $q->where('slug', self::DRIVER_ROLE_SLUG))
            ->firstOrFail();
    }

    private function assertPhoneAvailable(string $phoneInput, ?int $ignoreUserId = null): void
    {
        // IMPORTANT: on re-génère les candidats depuis le phone input
        $candidates = Phone::candidates($phoneInput);
        if (empty($candidates)) {
            throw new ConflictHttpException('Téléphone invalide');
        }

        $q = User::query()->whereIn('phone', $candidates);
        if ($ignoreUserId) $q->where('id', '!=', $ignoreUserId);

        if ($q->exists()) {
            throw new ConflictHttpException('Téléphone déjà utilisé');
        }
    }

    private function assertEmailAvailable(?string $email, ?int $ignoreUserId = null): void
    {
        if (!$email) return;

        $q = User::query()->where('email', strtolower($email));
        if ($ignoreUserId) $q->where('id', '!=', $ignoreUserId);

        if ($q->exists()) {
            throw new ConflictHttpException('Email déjà utilisé');
        }
    }

    private function generateUserUniqueId(): string
    {
        // Exemple: PxT-202601-Ceis
        $prefix = 'PxT-'.now()->format('Ym').'-';

        do {
            $suffix = Str::ucfirst(Str::lower(Str::random(4)));
            $id = $prefix.$suffix;
        } while (User::where('user_unique_id', $id)->exists());

        return $id;
    }

    private function throwConflictFromDuplicate(string $message): void
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, 'users_phone_unique') || str_contains($lower, 'phone')) {
            throw new ConflictHttpException('Téléphone déjà utilisé');
        }

        if (str_contains($lower, 'users_email_unique') || str_contains($lower, 'email')) {
            throw new ConflictHttpException('Email déjà utilisé');
        }

        throw new ConflictHttpException('Donnée déjà utilisée');
    }
}
