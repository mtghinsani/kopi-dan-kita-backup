<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Option;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Keranjang pelanggan: validasi opsi (BR-19), harga (BR-12), dan ringkasan jumlah serta subtotal. */
class CartService
{
    public function cartFor(User $user): Cart
    {
        return Cart::firstOrCreate(['user_id' => $user->id]);
    }

    /**
     * @param  array{product_id: int, qty: int, options?: array<int>, catatan?: ?string}  $data
     */
    public function add(User $user, array $data): CartItem
    {
        $product = Product::with('activeOptionGroups.options')->findOrFail($data['product_id']);
        $optionIds = array_map('intval', $data['options'] ?? []);

        $this->validateSelection($product, $optionIds);

        $cart = $this->cartFor($user);

        // Item dengan opsi sama tidak digabung; tiap tambah membuat baris baru.
        return DB::transaction(function () use ($cart, $product, $optionIds, $data) {
            $item = $cart->items()->create([
                'product_id' => $product->id,
                'qty' => $data['qty'],
                'catatan' => $data['catatan'] ?? null,
            ]);
            $item->options()->createMany(array_map(fn ($id) => ['option_id' => $id], $optionIds));
            $cart->touch();

            return $item;
        });
    }

    public function updateQty(CartItem $item, int $qty): CartItem
    {
        $item->update(['qty' => $qty]);
        $item->cart->touch();

        return $item;
    }

    public function remove(CartItem $item): void
    {
        $item->delete();
        $item->cart->touch();
    }

    /** Harga satuan = harga_dasar + jumlah selisih_harga opsi terpilih (BR-12). */
    public function unitPrice(CartItem $item): int
    {
        $item->loadMissing('product', 'options.option');

        return (int) $item->product->harga_dasar + (int) $item->options->sum(fn ($o) => $o->option->selisih_harga);
    }

    public function subtotal(CartItem $item): int
    {
        return $this->unitPrice($item) * $item->qty;
    }

    /** @return array{count: int, subtotal: int} */
    public function summary(Cart $cart): array
    {
        $items = $cart->items()->with('product', 'options.option')->get();

        return [
            'count' => (int) $items->sum('qty'),
            'subtotal' => (int) $items->sum(fn ($item) => $this->subtotal($item)),
        ];
    }

    public function count(User $user): int
    {
        return (int) CartItem::whereRelation('cart', 'user_id', $user->id)->sum('qty');
    }

    /** @return array{id: int, nama: string, qty: int, opsi: array<string>, harga_satuan: int, subtotal: int} */
    public function itemPayload(CartItem $item): array
    {
        $item->loadMissing('product', 'options.option');

        return [
            'id' => $item->id,
            'nama' => $item->product->nama,
            'qty' => $item->qty,
            'opsi' => $item->options->map(fn ($o) => $o->option->nama)->all(),
            'harga_satuan' => $this->unitPrice($item),
            'subtotal' => $this->subtotal($item),
        ];
    }

    /**
     * Produk tersedia; opsi milik grup produk ini dan tersedia; jumlah pilihan per grup di antara batasnya.
     *
     * @param  array<int>  $optionIds
     */
    private function validateSelection(Product $product, array $optionIds): void
    {
        if (! $product->tersedia) {
            throw ValidationException::withMessages(['product_id' => "{$product->nama} sedang habis."]);
        }

        $options = $product->activeOptionGroups->flatMap->options->keyBy('id');

        foreach ($optionIds as $id) {
            $option = $options->get($id);
            if (! $option) {
                throw ValidationException::withMessages(['options' => 'Opsi yang dipilih tidak tersedia untuk menu ini.']);
            }
            if (! $option->tersedia) {
                throw ValidationException::withMessages(['options' => "Opsi {$option->nama} sedang habis."]);
            }
        }

        foreach ($product->activeOptionGroups as $group) {
            $chosen = count(array_intersect($optionIds, $group->options->pluck('id')->all()));
            $min = max($group->min_pilih, $group->wajib ? 1 : 0);

            if ($chosen < $min) {
                throw ValidationException::withMessages(['options' => "Pilih minimal {$min} untuk {$group->nama}."]);
            }
            if ($chosen > $group->max_pilih) {
                throw ValidationException::withMessages(['options' => "Pilih maksimal {$group->max_pilih} untuk {$group->nama}."]);
            }
        }
    }

    /** Data grup opsi untuk modal kustomisasi (FR-10). */
    public function optionsPayload(Product $product): array
    {
        $product->load('activeOptionGroups.options');

        return [
            'id' => $product->id,
            'nama' => $product->nama,
            'harga_dasar' => (int) $product->harga_dasar,
            'tersedia' => $product->tersedia,
            'groups' => $product->activeOptionGroups->map(fn ($group) => [
                'id' => $group->id,
                'nama' => $group->nama,
                'wajib' => $group->wajib,
                'min_pilih' => $group->min_pilih,
                'max_pilih' => $group->max_pilih,
                'options' => $group->options->map(fn (Option $option) => [
                    'id' => $option->id,
                    'nama' => $option->nama,
                    'selisih_harga' => (int) $option->selisih_harga,
                    'tersedia' => $option->tersedia,
                ])->all(),
            ])->all(),
        ];
    }
}
