<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Modules\Core\Models\Member;
use App\Modules\Core\Models\MemberPointTransaction;
use App\Modules\Pos\Models\PosOrder;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoyaltyService
{
    /**
     * Cari member berdasarkan HP (scoped tenant). Kalau tidak ketemu dan
     * `$name` diisi, daftarkan member baru. Kalau tidak ketemu dan `$name`
     * kosong, tolak -- caller (kasir/pelanggan) harus isi nama dulu untuk
     * daftar member baru.
     */
    public function findOrCreateMember(int $tenantId, string $phone, ?string $name): Member
    {
        $member = Member::query()->where('tenant_id', $tenantId)->where('phone', $phone)->first();

        if ($member) {
            return $member;
        }

        if (! $name) {
            throw ValidationException::withMessages([
                'name' => 'Nomor HP ini belum terdaftar sebagai member. Isi nama untuk daftar member baru.',
            ]);
        }

        return Member::query()->create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'phone' => $phone,
            'points_balance' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Dipanggil PERSIS setelah order dipastikan lunas (PosOrderService::
     * recordPayment()). No-op kalau order tidak punya member ATAU admin
     * belum mengisi aturan poin (loyalty_points_per_amount) -- fitur ini
     * harus aman dipasang walau aturan bisnisnya belum final.
     */
    public function awardPoints(PosOrder $order): void
    {
        if (! $order->member_id) {
            return;
        }

        $rate = AppSetting::current()->loyalty_points_per_amount;

        if (! $rate || bccomp((string) $rate, '0', 4) <= 0) {
            return;
        }

        $points = floor((float) bcdiv((string) $order->total_amount, (string) $rate, 6));

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $points): void {
            $member = Member::query()->lockForUpdate()->findOrFail($order->member_id);

            $newBalance = bcadd((string) $member->points_balance, (string) $points, 2);
            $member->update(['points_balance' => $newBalance]);

            MemberPointTransaction::query()->create([
                'tenant_id' => $order->tenant_id,
                'member_id' => $member->id,
                'pos_order_id' => $order->id,
                'type' => MemberPointTransaction::TYPE_EARN,
                'points' => $points,
                'balance_after' => $newBalance,
                'notes' => "Poin dari order #{$order->order_number}",
            ]);
        });
    }

    /**
     * Tukar sebagian poin member jadi potongan harga -- ditambahkan ke
     * discount_amount yang SUDAH ADA di order (bukan menimpa), supaya
     * bisa digabung dengan diskon manual kasir. Cuma boleh selagi order
     * masih OPEN (belum lunas).
     */
    public function redeemPoints(PosOrder $order, Member $member, string $points): PosOrder
    {
        return DB::transaction(function () use ($order, $member, $points): PosOrder {
            $order = PosOrder::query()->lockForUpdate()->findOrFail($order->id);
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            if (! $order->canAddItem()) {
                throw ValidationException::withMessages(['status' => 'Order ini sudah tidak bisa diubah.']);
            }

            $points = Decimal::toFixed($points, 2);

            if (bccomp($points, '0', 2) <= 0) {
                throw ValidationException::withMessages(['points' => 'Jumlah poin harus lebih dari 0.']);
            }

            if (bccomp($points, (string) $member->points_balance, 2) > 0) {
                throw ValidationException::withMessages(['points' => 'Saldo poin member tidak cukup.']);
            }

            $pointValue = AppSetting::current()->loyalty_point_value;

            if (! $pointValue || bccomp((string) $pointValue, '0', 4) <= 0) {
                throw ValidationException::withMessages(['points' => 'Nilai tukar poin belum diatur admin.']);
            }

            $discountValue = bcmul($points, (string) $pointValue, 4);

            // Jangan sampai potongan bikin total minus.
            if (bccomp($discountValue, (string) $order->total_amount, 4) > 0) {
                $discountValue = (string) $order->total_amount;
            }

            $order->discount_amount = bcadd((string) $order->discount_amount, $discountValue, 4);
            $order->recalculateTotals();
            $order->save();

            $newBalance = bcsub((string) $member->points_balance, $points, 2);
            $member->update(['points_balance' => $newBalance]);

            MemberPointTransaction::query()->create([
                'tenant_id' => $order->tenant_id,
                'member_id' => $member->id,
                'pos_order_id' => $order->id,
                'type' => MemberPointTransaction::TYPE_REDEEM,
                'points' => bcmul($points, '-1', 2),
                'balance_after' => $newBalance,
                'notes' => "Ditukar untuk order #{$order->order_number}",
            ]);

            return $order->refresh()->load('items.menu');
        });
    }
}
