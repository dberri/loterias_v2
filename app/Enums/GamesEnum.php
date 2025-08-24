<?php

namespace App\Enums;

enum GamesEnum: string
{
    case MEGA_SENA = 'megasena';
    case LOTOFACIL = 'lotofacil';
    case QUINA = 'quina';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
