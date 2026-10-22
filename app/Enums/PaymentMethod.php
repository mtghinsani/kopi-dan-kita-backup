<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Online = 'ONLINE';
    case Kasir = 'KASIR';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Bayar Online',
            self::Kasir => 'Bayar di Kasir',
        };
    }
}
