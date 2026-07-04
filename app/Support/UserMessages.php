<?php

namespace App\Support;

final class UserMessages
{
    public const LOGIN_FAILED = 'Identifiants invalides.';
    public const OTP_SEND_FAILED = 'Impossible d’envoyer le code actuellement.';
    public const OTP_INVALID = 'Code invalide ou expiré.';
    public const TOO_MANY_ATTEMPTS = 'Trop de tentatives. Veuillez réessayer plus tard.';
    public const SESSION_EXPIRED = 'Votre session a expiré. Veuillez recommencer.';

    public const SERVER_ERROR = 'Une erreur est survenue. Veuillez réessayer.';
    public const ACCESS_DENIED = 'Vous n’êtes pas autorisé à effectuer cette action.';

    public const VEHICLE_UNAVAILABLE = 'Véhicule temporairement indisponible.';
    public const VEHICLE_COMMAND_SENT = 'Demande envoyée avec succès.';

    public const CREATED = 'Enregistrement effectué avec succès.';
    public const UPDATED = 'Modification effectuée avec succès.';
    public const DELETED = 'Suppression effectuée avec succès.';


    public const GENERIC_NOT_FOUND = 'Information introuvable.';
    public const PAYMENT_FAILED = 'Impossible d’effectuer le paiement actuellement.';
    public const CONFIG_SAVE_FAILED = 'Impossible d’enregistrer le paramétrage actuellement.';
}