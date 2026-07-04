<?php

namespace App\Http\Controllers\Partner;

use App\Enums\PartnerPermission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Partner\PartnerStaffService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PartnerStaffController extends Controller
{
    public function __construct(private PartnerStaffService $service) {}

    public function index(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $staffMembers = $this->service->listStaff($partner);

        $permissionGroups = PartnerPermission::grouped();

        return view('partner.staff.index', compact('staffMembers', 'permissionGroups'));
    }

    public function store(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $data = $request->validate([
            'nom'           => ['required', 'string', 'max:120'],
            'prenom'        => ['required', 'string', 'max:120'],
            'phone'         => ['required', 'string', 'max:40'],
            'email'         => ['nullable', 'email', 'max:190'],
            'ville'         => ['nullable', 'string', 'max:120'],
            'quartier'      => ['nullable', 'string', 'max:120'],
            'photo'         => ['nullable', 'image', 'max:4096'],
            'password'      => ['required', 'string', 'min:6', 'confirmed'],
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(PartnerPermission::values())],
        ], [
            'permissions.required' => 'Sélectionnez au moins une permission pour ce membre du staff.',
            'permissions.min'      => 'Sélectionnez au moins une permission pour ce membre du staff.',
        ]);

        try {
            $this->service->createStaff($partner, $data);

            return redirect()
                ->route('partner.staff.index')
                ->with('status', 'Membre du staff créé avec succès.');

        } catch (ConflictHttpException $e) {
            return back()
                ->withInput()
                ->withErrors(['general' => $e->getMessage()]);

        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(
                $this->uniqueErrorToMessages($e)
            );
        }
    }

    public function update(Request $request, int $id)
    {
        /** @var User $partner */
        $partner = $request->user();

        $data = $request->validate([
            'nom'           => ['required', 'string', 'max:120'],
            'prenom'        => ['required', 'string', 'max:120'],
            'phone'         => ['required', 'string', 'max:40'],
            'email'         => ['nullable', 'email', 'max:190'],
            'ville'         => ['nullable', 'string', 'max:120'],
            'quartier'      => ['nullable', 'string', 'max:120'],
            'photo'         => ['nullable', 'image', 'max:4096'],
            'password'      => ['nullable', 'string', 'min:6', 'confirmed'],
            'permissions'   => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in(PartnerPermission::values())],
        ], [
            'permissions.required' => 'Sélectionnez au moins une permission pour ce membre du staff.',
            'permissions.min'      => 'Sélectionnez au moins une permission pour ce membre du staff.',
        ]);

        try {
            $this->service->updateStaff($partner, $id, $data);

            return redirect()
                ->route('partner.staff.index')
                ->with('status', 'Membre du staff mis à jour avec succès.');

        } catch (ConflictHttpException $e) {
            return back()
                ->withInput()
                ->withErrors(['general' => $e->getMessage()]);

        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(
                $this->uniqueErrorToMessages($e)
            );
        }
    }

    public function destroy(Request $request, int $id)
    {
        /** @var User $partner */
        $partner = $request->user();

        try {
            $this->service->deleteStaff($partner, $id);

            return redirect()
                ->route('partner.staff.index')
                ->with('status', 'Membre du staff supprimé.');

        } catch (ConflictHttpException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function uniqueErrorToMessages(UniqueConstraintViolationException $e): array
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'users_phone_unique')) {
            return ['phone' => ['Ce numéro existe déjà.']];
        }

        if (str_contains($msg, 'users_email_unique')) {
            return ['email' => ['Cet email existe déjà.']];
        }

        return ['general' => ['Une contrainte d\'unicité a été violée.']];
    }
}
