<?php

namespace App\Enums;

/**
 * Predefined, application-wide catalog of permissions a partner can grant
 * to its staff members.
 *
 * This enum is the single source of truth. The `permissions` table is seeded
 * from it, the Gates are registered from it, and the staff UI is built from it.
 *
 * NOTE: managing staff and managing permissions are intentionally NOT in this
 * catalog — they remain reserved to the main partner so a staff member can
 * never escalate its own access.
 */
enum PartnerPermission: string
{
    case DashboardView        = 'dashboard.view';
    case TrackingView         = 'tracking.view';
    case AlertsView           = 'alerts.view';
    case TrajetsView          = 'trajets.view';
    case EngineControl        = 'engine.control';
    case AffectationsManage   = 'affectations.manage';
    case DriversManage        = 'drivers.manage';
    case LeaseView            = 'lease.view';
    case LeaseContractsManage = 'lease.contracts.manage';
    case LeasePayments        = 'lease.payments';
    case SettingsManage       = 'settings.manage';

    /**
     * Human-readable label (French) shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::DashboardView        => 'Tableau de bord',
            self::TrackingView         => 'Suivi des véhicules',
            self::AlertsView           => 'Alertes',
            self::TrajetsView          => 'Trajets',
            self::EngineControl        => 'Couper / démarrer le moteur',
            self::AffectationsManage   => 'Affectation chauffeur ↔ véhicule',
            self::DriversManage        => 'Gestion des chauffeurs',
            self::LeaseView            => 'Consultation des leases',
            self::LeaseContractsManage => 'Contrats & règles de coupure',
            self::LeasePayments        => 'Paiements & pardon de lease',
            self::SettingsManage       => 'Paramètres (géofences, fuseau, lease)',
        };
    }

    /**
     * Short description (French) used as helper text.
     */
    public function description(): string
    {
        return match ($this) {
            self::DashboardView        => 'Accéder au tableau de bord principal.',
            self::TrackingView         => 'Voir la carte et la liste des véhicules suivis.',
            self::AlertsView           => 'Consulter les alertes de la flotte.',
            self::TrajetsView          => 'Consulter l\'historique des trajets.',
            self::EngineControl        => 'Couper ou redémarrer le moteur d\'un véhicule à distance.',
            self::AffectationsManage   => 'Affecter ou retirer un chauffeur à un véhicule.',
            self::DriversManage        => 'Créer, modifier et supprimer des chauffeurs.',
            self::LeaseView            => 'Voir le tableau de bord et les listes de leases.',
            self::LeaseContractsManage => 'Gérer les contrats et les règles de coupure automatique.',
            self::LeasePayments        => 'Enregistrer des paiements et pardonner des leases impayés.',
            self::SettingsManage       => 'Modifier les paramètres : géofences, fuseau horaire, lease.',
        };
    }

    /**
     * UI grouping (French).
     */
    public function group(): string
    {
        return match ($this) {
            self::DashboardView,
            self::TrackingView,
            self::AlertsView,
            self::TrajetsView        => 'Suivi & supervision',

            self::EngineControl,
            self::AffectationsManage => 'Opérations véhicules',

            self::DriversManage      => 'Chauffeurs',

            self::LeaseView,
            self::LeaseContractsManage,
            self::LeasePayments      => 'Leases',

            self::SettingsManage     => 'Paramètres',
        };
    }

    /**
     * Sensitive permissions get a visual warning badge in the UI.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::EngineControl, self::LeasePayments => true,
            default => false,
        };
    }

    /**
     * All permission keys as plain strings (handy for validation rules).
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Catalog grouped by UI section, preserving enum declaration order.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $grouped = [];

        foreach (self::cases() as $permission) {
            $grouped[$permission->group()][] = $permission;
        }

        return $grouped;
    }
}
