<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Menunggu = 'MENUNGGU';
    case Selesai = 'SELESAI';
}
