<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T-24: skema 17 tabel domain, data seeder, dan model tanpa updated_at.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private const DOMAIN_TABLES = [
        'users', 'audit_logs', 'store_settings', 'vouchers', 'orders', 'order_items', 'order_item_options',
        'payments', 'refunds', 'order_status_logs', 'categories', 'products', 'option_groups', 'options',
        'carts', 'cart_items', 'cart_item_options',
    ];

    public function test_all_domain_tables_exist(): void
    {
        foreach (self::DOMAIN_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ada.");
        }

        $this->assertCount(17, self::DOMAIN_TABLES);
        $this->assertFalse(Schema::hasTable('password_reset_tokens'));
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    public function test_users_table_follows_erd(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id', 'nama', 'email', 'no_hp', 'password_hash', 'role', 'aktif', 'privasi_versi', 'privasi_disetujui_at', 'created_at',
        ]));
        $this->assertFalse(Schema::hasColumn('users', 'password'));
        $this->assertFalse(Schema::hasColumn('users', 'remember_token'));
        $this->assertFalse(Schema::hasColumn('users', 'updated_at'));
    }

    public function test_payment_gateway_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('payments', [
            'metode', 'gateway_order_id', 'transaction_id', 'payment_type', 'diverifikasi_oleh', 'verified_at', 'expired_at',
        ]));
        $this->assertTrue(Schema::hasColumn('refunds', 'refund_key'));
    }

    public function test_seeder_creates_demo_data(): void
    {
        $this->seed();

        $this->assertSame(1, User::where('role', Role::SuperAdmin)->count());
        $this->assertSame(2, User::where('role', Role::Karyawan)->count());
        $this->assertSame(3, User::where('role', Role::Pelanggan)->count());
        $this->assertSame(5, Category::count());
        $this->assertSame(30, Product::count());
        $this->assertSame(3, Voucher::count());
        $this->assertSame(1, StoreSetting::count());

        $setting = StoreSetting::current();
        $this->assertSame(15, $setting->batas_bayar_menit);
        $this->assertTrue($setting->pemesanan_dibuka);

        $kopi = Product::whereRelation('category', 'nama', 'Kopi')->first();
        $this->assertSame(
            ['Ukuran', 'Suhu', 'Es', 'Gula', 'Tambahan'],
            $kopi->optionGroups->pluck('nama')->all(),
        );
        $tambahan = $kopi->optionGroups->firstWhere('nama', 'Tambahan');
        $this->assertSame(0, $tambahan->min_pilih);
        $this->assertSame(3, $tambahan->max_pilih);

        $roti = Product::whereRelation('category', 'nama', 'Roti')->first();
        $this->assertCount(0, $roti->optionGroups);

        $admin = User::where('email', 'admin@kopikita.test')->first();
        $this->assertTrue(password_verify('password', $admin->password_hash));
    }

    public function test_models_without_updated_at_can_be_created_and_updated(): void
    {
        $user = User::factory()->create();
        $user->update(['nama' => 'Nama Baru']);

        $order = Order::factory()->for($user)->create();
        $order->update(['status' => OrderStatus::Dibatalkan]);

        $payment = Payment::factory()->for($order)->create([
            'metode' => PaymentMethod::Kasir,
            'status' => PaymentStatus::Menunggu,
        ]);
        $payment->update(['status' => PaymentStatus::Batal]);

        $log = OrderStatusLog::create([
            'order_id' => $order->id,
            'status_lama' => OrderStatus::MenungguPembayaran,
            'status_baru' => OrderStatus::Dibatalkan,
            'diubah_oleh' => $user->id,
        ]);

        $audit = AuditLog::record($user, 'UBAH_AKUN', $user, ['nama' => 'Lama'], ['nama' => 'Nama Baru']);

        $this->assertSame('Nama Baru', $user->fresh()->nama);
        $this->assertSame(OrderStatus::Dibatalkan, $order->fresh()->status);
        $this->assertSame(PaymentStatus::Batal, $payment->fresh()->status);
        $this->assertNotNull($log->fresh()->changed_at);
        $this->assertSame('users', $audit->objek);
        $this->assertSame(['nama' => 'Nama Baru'], $audit->fresh()->nilai_baru);
    }

    public function test_order_status_transitions_follow_business_rules(): void
    {
        $this->assertTrue(OrderStatus::MenungguPembayaran->canTransitionTo(OrderStatus::Dibayar));
        $this->assertTrue(OrderStatus::Dibayar->canTransitionTo(OrderStatus::Diproses));
        $this->assertTrue(OrderStatus::SiapDiambil->canTransitionTo(OrderStatus::Selesai));
        $this->assertFalse(OrderStatus::Dibayar->canTransitionTo(OrderStatus::SiapDiambil));
        $this->assertFalse(OrderStatus::Selesai->canTransitionTo(OrderStatus::Dibatalkan));
        $this->assertTrue(OrderStatus::Kedaluwarsa->isFinal());
        $this->assertSame('text-bg-warning', OrderStatus::MenungguPembayaran->badgeClass());
    }
}
