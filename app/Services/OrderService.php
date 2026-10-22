<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\VoucherType;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Voucher;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/** Siklus hidup pesanan: checkout, voucher, transisi status, konfirmasi kasir, pembatalan, ganti metode. */
class OrderService
{
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const MIN_TOTAL = 1000;

    public function __construct(private CartService $cart, private MidtransService $midtrans) {}

    /** BR-10: pemesanan dibuka dan sekarang di antara jam buka dan jam tutup. */
    public function isOpen(?StoreSetting $setting = null): bool
    {
        $setting ??= StoreSetting::current();
        $now = now();

        return $setting->pemesanan_dibuka
            && $now->gte(today()->setTimeFromTimeString($setting->jam_buka))
            && $now->lt($this->closingTime($setting));
    }

    public function closingTime(?StoreSetting $setting = null): CarbonInterface
    {
        $setting ??= StoreSetting::current();

        return today()->setTimeFromTimeString($setting->jam_tutup);
    }

    /**
     * Satu transaksi: harga dihitung ulang dari basis data, ketersediaan dicek ulang, voucher dikunci.
     *
     * @param  array{metode: string, voucher?: ?string}  $data
     */
    public function checkout(User $user, array $data): Order
    {
        $setting = StoreSetting::current();
        if (! $this->isOpen($setting)) {
            throw ValidationException::withMessages(['checkout' => 'Kedai sedang tidak menerima pesanan. Silakan pesan pada jam operasional.']);
        }

        $metode = PaymentMethod::from($data['metode']);

        return DB::transaction(function () use ($user, $data, $setting, $metode) {
            $cart = $this->cart->cartFor($user);
            $items = $cart->items()->with('product', 'options.option')->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['checkout' => 'Keranjang masih kosong.']);
            }

            foreach ($items as $item) {
                $habis = ! $item->product->tersedia || $item->options->contains(fn ($o) => ! $o->option->tersedia);
                if ($habis) {
                    throw ValidationException::withMessages(['checkout' => "{$item->product->nama} sedang habis. Hapus dari keranjang untuk melanjutkan."]);
                }
            }

            $subtotal = (int) $items->sum(fn ($item) => $this->cart->subtotal($item));

            $voucher = null;
            $diskon = 0;
            if (filled($data['voucher'] ?? null)) {
                $voucher = Voucher::where('kode', Str::upper(trim($data['voucher'])))->lockForUpdate()->first();
                $this->ensureVoucherUsable($voucher);
                $diskon = $this->discount($voucher, $subtotal);
                $voucher->increment('terpakai');
            }

            $order = Order::create([
                'user_id' => $user->id,
                'voucher_id' => $voucher?->id,
                'kode_pickup' => $this->generateCode(),
                'status' => OrderStatus::MenungguPembayaran,
                'subtotal' => $subtotal,
                'diskon' => $diskon,
                'total' => $subtotal - $diskon,
            ]);

            foreach ($items as $item) {
                $orderItem = $order->items()->create([
                    'product_id' => $item->product_id,
                    'nama_snapshot' => $item->product->nama,
                    'harga_snapshot' => $item->product->harga_dasar,
                    'qty' => $item->qty,
                    'subtotal' => $this->cart->subtotal($item),
                    'catatan' => $item->catatan,
                ]);
                $orderItem->options()->createMany($item->options->map(fn ($o) => [
                    'option_id' => $o->option_id,
                    'nama_snapshot' => $o->option->nama,
                    'selisih_snapshot' => $o->option->selisih_harga,
                ])->all());
            }

            $this->createPayment($order, $metode, $setting);

            $order->statusLogs()->create([
                'status_lama' => null,
                'status_baru' => OrderStatus::MenungguPembayaran,
                'diubah_oleh' => $user->id,
            ]);

            $cart->items()->delete();
            $cart->touch();

            return $order;
        });
    }

    /**
     * Pratinjau voucher untuk keranjang saat ini (POST /checkout/voucher); checkout memvalidasi ulang.
     *
     * @return array{kode: string, jenis: string, subtotal: int, diskon: int, total: int}
     */
    public function previewVoucher(User $user, string $kode): array
    {
        $voucher = Voucher::where('kode', Str::upper(trim($kode)))->first();
        $this->ensureVoucherUsable($voucher);

        $subtotal = $this->cart->summary($this->cart->cartFor($user))['subtotal'];
        $diskon = $this->discount($voucher, $subtotal);

        return [
            'kode' => $voucher->kode,
            'jenis' => $voucher->jenis->value,
            'subtotal' => $subtotal,
            'diskon' => $diskon,
            'total' => $subtotal - $diskon,
        ];
    }

    /** BR-13 sampai BR-15. */
    public function discount(Voucher $voucher, int $subtotal): int
    {
        $diskon = $voucher->jenis === VoucherType::Persen
            ? intdiv($subtotal * (int) $voucher->nilai, 100)
            : min((int) $voucher->nilai, $subtotal);

        return max(0, min($diskon, $subtotal - self::MIN_TOTAL));
    }

    /** BR-16. */
    private function ensureVoucherUsable(?Voucher $voucher): void
    {
        $message = match (true) {
            ! $voucher => 'Kode voucher tidak ditemukan.',
            now()->lt($voucher->berlaku_mulai) => 'Voucher belum berlaku.',
            now()->gt($voucher->berlaku_sampai) => 'Voucher sudah kedaluwarsa.',
            $voucher->terpakai >= $voucher->kuota => 'Kuota voucher sudah habis.',
            default => null,
        };

        if ($message) {
            throw ValidationException::withMessages(['voucher' => $message]);
        }
    }

    /** BR-06: 6 karakter tanpa I, O, 0, 1; ulangi bila sudah dipakai, paling banyak 5 kali. */
    public function generateCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            if (! Order::where('kode_pickup', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Gagal membuat kode pesanan unik.');
    }

    private function createPayment(Order $order, PaymentMethod $metode, StoreSetting $setting): Payment
    {
        return $order->payments()->create([
            'nominal' => $order->total,
            'status' => PaymentStatus::Menunggu,
            'metode' => $metode,
            // Kode pesanan + waktu + urutan pembayaran: unik walau basis data di-reset atau metode diganti dalam detik yang sama.
            'gateway_order_id' => $metode === PaymentMethod::Online
                ? $order->kode_pickup.'-'.now()->timestamp.'-'.($order->payments()->count() + 1)
                : null,
            'expired_at' => $metode === PaymentMethod::Online
                ? now()->addMinutes($setting->batas_bayar_menit)
                : $this->closingTime($setting),
        ]);
    }

    /**
     * Satu-satunya jalan mengubah status pesanan. Membaca ulang dengan lock, menolak transisi tidak sah (409),
     * mengisi kolom waktu, menulis log status. Batal atau kedaluwarsa sebelum dibayar juga mengakhiri
     * pembayaran aktif dan mengembalikan kuota voucher (BR-17).
     *
     * @param  array<string, mixed>  $extra
     */
    public function transition(Order $order, OrderStatus $to, ?User $actor, array $extra = []): Order
    {
        return DB::transaction(function () use ($order, $to, $actor, $extra) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new ConflictHttpException("Perubahan status dari {$from->value} ke {$to->value} tidak diizinkan.");
            }

            $timestamps = match ($to) {
                OrderStatus::Dibayar => ['paid_at' => now()],
                OrderStatus::SiapDiambil => ['ready_at' => now()],
                OrderStatus::Selesai => ['completed_at' => now()],
                default => [],
            };
            $locked->update(['status' => $to, ...$timestamps, ...$extra]);

            if ($from === OrderStatus::MenungguPembayaran && in_array($to, [OrderStatus::Dibatalkan, OrderStatus::Kedaluwarsa], true)) {
                $locked->payments()->where('status', PaymentStatus::Menunggu)->update([
                    'status' => $to === OrderStatus::Kedaluwarsa ? PaymentStatus::Kedaluwarsa : PaymentStatus::Batal,
                ]);
                if ($locked->voucher_id) {
                    Voucher::whereKey($locked->voucher_id)->where('terpakai', '>', 0)->decrement('terpakai');
                }
            }

            $locked->statusLogs()->create([
                'status_lama' => $from,
                'status_baru' => $to,
                'diubah_oleh' => $actor?->id,
            ]);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        }, attempts: 3);
    }

    /** BR-07: hanya pembayaran aktif KASIR pada pesanan MENUNGGU_PEMBAYARAN; konfirmasi kedua 409. */
    public function confirmCashierPayment(Order $order, User $staff): Order
    {
        return DB::transaction(function () use ($order, $staff) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::MenungguPembayaran) {
                throw new ConflictHttpException($locked->paid_at ? 'Pesanan sudah dibayar.' : 'Pesanan tidak lagi menunggu pembayaran.');
            }

            $payment = $locked->payments()->where('status', PaymentStatus::Menunggu)->lockForUpdate()->latest('id')->first();
            if (! $payment || $payment->metode !== PaymentMethod::Kasir) {
                throw new UnprocessableEntityHttpException('Konfirmasi hanya untuk metode Bayar di Kasir.');
            }

            $payment->update([
                'status' => PaymentStatus::Lunas,
                'diverifikasi_oleh' => $staff->id,
                'verified_at' => now(),
            ]);

            $this->transition($order, OrderStatus::Dibayar, $staff);

            AuditLog::record($staff, 'KONFIRMASI_BAYAR', $payment,
                ['status' => PaymentStatus::Menunggu->value],
                ['status' => PaymentStatus::Lunas->value, 'nominal' => (int) $payment->nominal, 'kode' => $order->kode_pickup]);

            return $order;
        });
    }

    /** BR-04: pelanggan hanya boleh membatalkan saat MENUNGGU_PEMBAYARAN. */
    public function cancelByCustomer(Order $order, User $customer): Order
    {
        if ($order->status !== OrderStatus::MenungguPembayaran) {
            throw new ConflictHttpException('Pesanan yang sudah dibayar tidak dapat dibatalkan.');
        }

        $active = $order->activePayment()->first();
        if ($this->midtrans->isOnlinePending($active)) {
            $this->midtrans->cancel($active->gateway_order_id);
        }

        return $this->transition($order, OrderStatus::Dibatalkan, $customer, ['dibatalkan_oleh' => $customer->id]);
    }

    /**
     * BR-05: kedai membatalkan dengan alasan pada MENUNGGU_PEMBAYARAN sampai SIAP_DIAMBIL. Pesanan yang sudah
     * lunas tampil Perlu Refund di panel admin.
     */
    public function cancelByStore(Order $order, User $staff, string $alasan): Order
    {
        if (! $order->status->canTransitionTo(OrderStatus::Dibatalkan)) {
            throw new ConflictHttpException('Pesanan ini sudah tidak dapat dibatalkan.');
        }

        $active = $order->activePayment()->first();
        if ($this->midtrans->isOnlinePending($active)) {
            $this->midtrans->cancel($active->gateway_order_id);
        }

        return DB::transaction(function () use ($order, $staff, $alasan) {
            $from = $order->status;
            $this->transition($order, OrderStatus::Dibatalkan, $staff, ['alasan_batal' => $alasan, 'dibatalkan_oleh' => $staff->id]);
            AuditLog::record($staff, 'BATAL_KEDAI', $order, ['status' => $from->value], ['status' => OrderStatus::Dibatalkan->value, 'alasan' => $alasan]);

            return $order;
        });
    }

    /**
     * BR-18: ganti metode atau coba bayar lagi. Pembayaran aktif yang masih bisa dipakai dikembalikan apa adanya;
     * selain itu pembayaran lama menjadi BATAL (ONLINE dibatalkan dulu di Midtrans) dan dibuat baris baru.
     */
    public function switchPayment(Order $order, PaymentMethod $metode): Payment
    {
        if ($order->status !== OrderStatus::MenungguPembayaran) {
            throw new ConflictHttpException('Pesanan tidak lagi menunggu pembayaran.');
        }

        $active = $order->activePayment()->first();
        $reusable = $active?->metode === $metode
            && ($metode === PaymentMethod::Kasir || Cache::has("snap_token:{$active->id}"));
        if ($reusable) {
            return $active;
        }

        if ($this->midtrans->isOnlinePending($active)) {
            $this->midtrans->cancel($active->gateway_order_id);
        }

        return DB::transaction(function () use ($order, $metode) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== OrderStatus::MenungguPembayaran) {
                throw new ConflictHttpException('Pesanan tidak lagi menunggu pembayaran.');
            }

            $locked->payments()->where('status', PaymentStatus::Menunggu)->update(['status' => PaymentStatus::Batal]);

            return $this->createPayment($locked, $metode, StoreSetting::current());
        });
    }

    /** Pembayaran yang lewat batas: pembayaran KEDALUWARSA, pesanan ikut KEDALUWARSA bila masih menunggu. */
    public function expirePayment(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Menunggu) {
            return;
        }

        $order = $payment->order()->first();
        if ($order->status === OrderStatus::MenungguPembayaran) {
            $this->transition($order, OrderStatus::Kedaluwarsa, null);
        } else {
            $payment->update(['status' => PaymentStatus::Kedaluwarsa]);
        }
    }

    /**
     * Penjadwal: ONLINE dicek ke Status API dulu (settlement tetap diproses lunas), sisanya dibatalkan di Midtrans
     * lalu dikedaluwarsakan; KASIR langsung dikedaluwarsakan. Mengembalikan jumlah pembayaran yang diproses.
     */
    public function expireDuePayments(): int
    {
        $due = Payment::where('status', PaymentStatus::Menunggu)->where('expired_at', '<', now())->get();

        foreach ($due as $payment) {
            try {
                if ($payment->metode === PaymentMethod::Online) {
                    $remote = $this->midtrans->status($payment->gateway_order_id);
                    if ($remote) {
                        $this->midtrans->applyStatus($payment, $remote);
                        $payment->refresh();
                    }
                    if ($payment->status !== PaymentStatus::Menunggu) {
                        continue;
                    }
                    $this->midtrans->cancel($payment->gateway_order_id);
                }

                DB::transaction(fn () => $this->expirePayment(Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail()));
            } catch (\Throwable $e) {
                // Satu pembayaran gagal (misalnya Midtrans tidak terjangkau) tidak menghentikan yang lain; dicoba lagi menit berikutnya.
                Log::warning('orders:expire gagal untuk satu pembayaran', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return $due->count();
    }
}
