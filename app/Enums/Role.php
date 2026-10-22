<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'SUPER_ADMIN';
    case Karyawan = 'KARYAWAN';
    case Pelanggan = 'PELANGGAN';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Karyawan => 'Karyawan',
            self::Pelanggan => 'Pelanggan',
        };
    }
}
