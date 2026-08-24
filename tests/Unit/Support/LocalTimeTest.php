<?php

use App\Support\LocalTime;
use Carbon\Carbon;

/**
 * config('app.display_timezone') vaut Africa/Douala (UTC+1) dans tout
 * l'environnement de test — ancré explicitement ici pour ne pas dépendre du
 * .env de la machine qui exécute les tests.
 */
beforeEach(function () {
    config(['app.display_timezone' => 'Africa/Douala']);
});

it('affiche — pour une valeur vide', function () {
    expect(LocalTime::display(null))->toBe('—');
    expect(LocalTime::displayRaw(null))->toBe('—');
});

it('convertit une valeur Carbon UTC (colonne cast datetime) vers Douala', function () {
    // 10:43:31 UTC = 11:43:31 Douala (UTC+1).
    $utc = Carbon::createFromFormat('Y-m-d H:i:s', '2026-08-24 10:43:31', 'UTC');

    expect(LocalTime::display($utc, 'H:i:s'))->toBe('11:43:31');
});

it('convertit une chaine DATETIME brute (getRawOriginal, jamais castee) vers Douala', function () {
    // Reproduit lease_cutoff_histories.scheduled_for : colonne DATETIME,
    // litteral UTC stocke tel quel, jamais converti par MySQL.
    expect(LocalTime::displayRaw('2026-08-24 10:43:31', 'H:i:s'))->toBe('11:43:31');
});

it('traverse minuit Douala correctement (23h30 Douala = 22h30 UTC)', function () {
    // Cas explicitement demande par le plan : verifie le changement de jour.
    $utc = LocalTime::inputToUtc('2026-08-24 23:30:00');

    expect($utc->toDateTimeString())->toBe('2026-08-24 22:30:00');

    // Aller-retour : reconvertir cette valeur UTC vers l'affichage doit
    // redonner exactement la saisie d'origine.
    expect(LocalTime::displayRaw($utc->toDateTimeString(), 'd/m/Y H:i:s'))
        ->toBe('24/08/2026 23:30:00');
});

it('interprete une saisie utilisateur comme heure murale Douala, jamais UTC', function () {
    // Un partenaire qui tape "10:00" pense a 10h locale (Douala), pas 10h UTC.
    $utc = LocalTime::inputToUtc('2026-08-24 10:00:00');

    expect($utc->timezone->getName())->toBe('UTC');
    expect($utc->toDateTimeString())->toBe('2026-08-24 09:00:00');
});

it('retourne null pour une saisie vide', function () {
    expect(LocalTime::inputToUtc(null))->toBeNull();
    expect(LocalTime::inputToUtc(''))->toBeNull();
    expect(LocalTime::inputToUtc('   '))->toBeNull();
});

it('calcule la periode "today" en Douala puis la convertit en UTC (bornes prêtes pour un WHERE)', function () {
    Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', '2026-08-24 23:30:00', 'UTC'));
    // 23:30 UTC = 00:30 Douala le 25/08 : "aujourd'hui" cote utilisateur est
    // donc le 25/08 Douala, PAS le 24/08 -- c'est exactement le cas qui
    // etait facilement faux avant ce correctif (frontiere de jour a minuit
    // UTC au lieu de minuit Douala).
    [$from, $to] = LocalTime::periodRange('today');

    expect($from->timezone->getName())->toBe('UTC');
    // minuit Douala le 25/08 = 23:00 UTC le 24/08.
    expect($from->toDateTimeString())->toBe('2026-08-24 23:00:00');
    expect($to->toDateTimeString())->toBe('2026-08-25 22:59:59');

    Carbon::setTestNow();
});

it('calcule la periode "specific_date" en Douala puis la convertit en UTC', function () {
    [$from, $to] = LocalTime::periodRange('specific_date', ['date' => '2026-08-24']);

    expect($from->toDateTimeString())->toBe('2026-08-23 23:00:00');
    expect($to->toDateTimeString())->toBe('2026-08-24 22:59:59');
});

it('calcule la periode "range" en Douala puis la convertit en UTC', function () {
    [$from, $to] = LocalTime::periodRange('range', ['from' => '2026-08-20', 'to' => '2026-08-22']);

    expect($from->toDateTimeString())->toBe('2026-08-19 23:00:00');
    expect($to->toDateTimeString())->toBe('2026-08-22 22:59:59');
});

it('retourne [null, null] pour une periode inconnue ou vide', function () {
    expect(LocalTime::periodRange(''))->toBe([null, null]);
    expect(LocalTime::periodRange('specific_date', []))->toBe([null, null]);
});
