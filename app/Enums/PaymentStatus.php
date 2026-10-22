<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Menunggu = 'MENUNGGU';
    case Lunas = 'LUNAS';
    case Kedaluwarsa = 'KEDALUWARSA';
    case Batal = 'BATAL';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu',
            self::Lunas => 'Lunas',
            self::Kedaluwarsa => 'Kedaluwarsa',
            self::Batal => 'Batal',
        };
    }
}
