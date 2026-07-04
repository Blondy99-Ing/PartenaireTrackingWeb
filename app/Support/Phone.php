<?php

namespace App\Support;

class Phone
{
    /**
     * Canonique sans + : 2376XXXXXXXX
     */
    public static function cm(?string $raw): ?string
    {
        if (!$raw) return null;

        // On garde seulement les chiffres
        $v = preg_replace('/\D+/', '', $raw);
        if (!$v) return null;

        // Ex: 00237XXXXXXXXX -> 237XXXXXXXXX
        if (str_starts_with($v, '00237')) {
            $v = substr($v, 2);
        }

        // Cas: 0966xxxxxxx / 0696xxxxxxx => 696xxxxxxx
        if (strlen($v) >= 10 && str_starts_with($v, '0')) {
            // On enlève seulement 1 zéro au début
            $v = ltrim($v, '0');
        }

        // Si commence par 237
        if (str_starts_with($v, '237')) {
            $rest = substr($v, 3);
            return preg_match('/^[6-9]\d{8}$/', $rest) ? ('237' . $rest) : null;
        }

        // Numéro local (9 chiffres)
        return preg_match('/^[6-9]\d{8}$/', $v) ? ('237' . $v) : null;
    }

    /**
     * E.164 : +2376XXXXXXXX
     */
    public static function e164(?string $raw): ?string
    {
        $n = self::cm($raw);
        return $n ? ('+' . $n) : null;
    }

    /**
     * Pour matcher une BD “sale” : toutes les variantes possibles.
     */
    public static function candidates(?string $raw): array
    {
        $n = self::cm($raw); // 2376XXXXXXXX
        if (!$n) return [];

        $nine = substr($n, 3); // 6XXXXXXXX

        return array_values(array_unique([
            $n,            // 2376XXXXXXXX
            '+'.$n,        // +2376XXXXXXXX
            '00'.$n,       // 002376XXXXXXXX
            $nine,         // 6XXXXXXXX
            '0'.$nine,     // 06XXXXXXXX
            '00'.'0'.$nine // 0006... (cas extrême)
        ]));
    }
}
