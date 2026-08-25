<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Point de passage UNIQUE entre "valeur stockée en UTC" et "valeur montrée ou
 * saisie en heure murale d'affichage" (config('app.display_timezone'),
 * Africa/Douala). Généralise le motif déjà correct de
 * LeaseCutoffPlannerService::toLocalDisplay()/toLocalDisplayFromRaw()
 * (trouvé et corrigé le 19/08/2026) à tout le système.
 *
 * Règle stricte : ne jamais afficher une date à un utilisateur, ni interpréter
 * une date qu'il saisit, sans passer par une méthode de cette classe.
 * config('app.timezone') doit rester UTC partout ailleurs dans le code — c'est
 * le fuseau de STOCKAGE, jamais celui d'affichage.
 */
class LocalTime
{
    /**
     * Affiche une valeur Carbon (ou castée 'datetime' par Eloquent, donc déjà
     * interprétée en UTC = app.timezone) en heure d'affichage.
     */
    public static function display($value, string $format = 'd/m/Y à H:i'): string
    {
        if (! $value) {
            return '—';
        }

        return Carbon::parse($value)
            ->copy()
            ->setTimezone(config('app.display_timezone', 'Africa/Douala'))
            ->format($format);
    }

    /**
     * Affiche une chaîne DATETIME BRUTE relue via DB::table()/getRawOriginal()
     * (jamais castée par Eloquent). Les colonnes datetime sont toujours
     * écrites en UTC en base ; ancrer explicitement l'interprétation sur UTC
     * ici évite de dépendre du fuseau ambiant de l'environnement qui exécute
     * le code.
     */
    public static function displayRaw(?string $rawUtcValue, string $format = 'd/m/Y à H:i'): string
    {
        $rawUtcValue = trim((string) $rawUtcValue);

        if ($rawUtcValue === '') {
            return '—';
        }

        $tz = config('app.display_timezone', 'Africa/Douala');

        /*
         * Chemin nominal : format exact des colonnes DATETIME de la base.
         * createFromFormat est volontairement strict — il refuse toute
         * donnée résiduelle — ce qui garantit qu'on n'interprète pas de
         * travers une valeur inattendue.
         */
        try {
            return Carbon::createFromFormat('Y-m-d H:i:s', $rawUtcValue, 'UTC')
                ->setTimezone($tz)
                ->format($format);
        } catch (\Throwable $e) {
            // On tente une lecture tolérante ci-dessous.
        }

        /*
         * Repli tolérant. Cette méthode ne reçoit pas que des colonnes de
         * base : certains appelants lui passent des horodatages venus du
         * fournisseur GPS, qui peuvent porter des fractions de seconde, un
         * suffixe de fuseau ou un format ISO. Une exception y faisait tomber
         * toute la page — un simple affichage de date ne doit jamais avoir
         * cet effet.
         *
         * Une valeur qui porte déjà son fuseau est respectée ; une valeur
         * naïve reste ancrée sur UTC, comme dans le chemin nominal.
         */
        try {
            $porteUnFuseau = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/', $rawUtcValue);

            return ($porteUnFuseau ? Carbon::parse($rawUtcValue) : Carbon::parse($rawUtcValue, 'UTC'))
                ->setTimezone($tz)
                ->format($format);
        } catch (\Throwable $e) {
            return '—';
        }
    }

    /**
     * Interprète une saisie utilisateur (formulaire, filtre) comme une heure
     * MURALE d'affichage (Douala), puis la convertit en UTC — prête à être
     * stockée ou liée dans une clause SQL. C'est l'étape manquante partout où
     * le code se contentait de Carbon::parse($input, 'Africa/Douala') sans
     * jamais reconvertir avant liaison : Laravel ne le fait jamais tout seul
     * (Illuminate\Database\Connection::prepareBindings() réémet les chiffres
     * du fuseau déjà attaché à l'objet, sans les convertir en UTC).
     */
    public static function inputToUtc(?string $localInput, ?string $format = null): ?Carbon
    {
        $localInput = trim((string) $localInput);

        if ($localInput === '') {
            return null;
        }

        $tz = config('app.display_timezone', 'Africa/Douala');

        $carbon = $format
            ? Carbon::createFromFormat($format, $localInput, $tz)
            : Carbon::parse($localInput, $tz);

        return $carbon->setTimezone('UTC');
    }

    /**
     * Calcule les bornes [début, fin] d'une période nommée en heure murale
     * d'affichage, PUIS les reconvertit en UTC — prêtes à être liées
     * directement dans un where()/whereBetween(). Remplace les implémentations
     * ad hoc dupliquées (AlertController, UnifiedCutoffHistoryService,
     * LeaseCutoffHistoryService, DashboardLeaseService...), qui construisaient
     * toutes des bornes taguées Douala puis les liaient telles quelles — faux
     * d'environ 1h sur toute colonne DATETIME.
     *
     * @param array{date?: ?string, from?: ?string, to?: ?string} $extra
     *   'date' pour period='specific_date' ; 'from'/'to' pour period='range'.
     * @return array{0: ?Carbon, 1: ?Carbon} bornes en UTC, ou [null, null] si
     *   la période est vide/inconnue.
     */
    public static function periodRange(string $period, array $extra = []): array
    {
        $tz = config('app.display_timezone', 'Africa/Douala');
        $now = Carbon::now($tz);

        [$from, $to] = match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'this_year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'specific_date' => self::specificDateRange($extra['date'] ?? null, $tz),
            'range' => self::customRange($extra['from'] ?? null, $extra['to'] ?? null, $tz),
            default => [null, null],
        };

        return [
            $from?->copy()->setTimezone('UTC'),
            $to?->copy()->setTimezone('UTC'),
        ];
    }

    private static function specificDateRange(?string $date, string $tz): array
    {
        $date = trim((string) $date);

        if ($date === '') {
            return [null, null];
        }

        $parsed = Carbon::parse($date, $tz);

        return [$parsed->copy()->startOfDay(), $parsed->copy()->endOfDay()];
    }

    private static function customRange(?string $from, ?string $to, string $tz): array
    {
        $from = trim((string) $from);
        $to = trim((string) $to);

        return [
            $from !== '' ? Carbon::parse($from, $tz)->startOfDay() : null,
            $to !== '' ? Carbon::parse($to, $tz)->endOfDay() : null,
        ];
    }
}
