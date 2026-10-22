<?php

namespace App\Enums;

enum OrderStatus: string
{
    case MenungguPembayaran = 'MENUNGGU_PEMBAYARAN';
    case Dibayar = 'DIBAYAR';
    case Diproses = 'DIPROSES';
    case SiapDiambil = 'SIAP_DIAMBIL';
    case Selesai = 'SELESAI';
    case Dibatalkan = 'DIBATALKAN';
    case Kedaluwarsa = 'KEDALUWARSA';

    public function label(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'Menunggu Pembayaran',
            self::Dibayar => 'Dibayar',
            self::Diproses => 'Diproses',
            self::SiapDiambil => 'Siap Diambil',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
            self::Kedaluwarsa => 'Kedaluwarsa',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::MenungguPembayaran => 'text-bg-warning',
            self::Dibayar => 'text-bg-success',
            self::Diproses => 'text-bg-primary',
            self::SiapDiambil => 'text-bg-info',
            self::Selesai => 'text-bg-secondary',
            self::Dibatalkan => 'text-bg-danger',
            self::Kedaluwarsa => 'text-bg-dark',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Selesai, self::Dibatalkan, self::Kedaluwarsa], true);
    }

    /** Transisi sah menurut aturan bisnis; selain ini ditolak dengan 409. */
    public function canTransitionTo(self $to): bool
    {
        $allowed = match ($this) {
            self::MenungguPembayaran => [self::Dibayar, self::Dibatalkan, self::Kedaluwarsa],
            self::Dibayar => [self::Diproses, self::Dibatalkan],
            self::Diproses => [self::SiapDiambil, self::Dibatalkan],
            self::SiapDiambil => [self::Selesai, self::Dibatalkan],
            self::Selesai, self::Dibatalkan, self::Kedaluwarsa => [],
        };

        return in_array($to, $allowed, true);
    }
}
