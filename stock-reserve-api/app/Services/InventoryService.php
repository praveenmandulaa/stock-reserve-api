<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function reserve(int $userId, int $productId, int $quantity, string $key): array
    {
        try {
            return DB::transaction(function () use ($userId, $productId, $quantity, $key) {
                $existing = DB::table('reservations')->where('user_id', $userId)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $this->replay($existing, $productId, $quantity);
                }
                $id = (string) Str::uuid();
                $now = now();
                DB::table('reservations')->insert(['id' => $id, 'user_id' => $userId, 'product_id' => $productId, 'quantity' => $quantity, 'idempotency_key' => $key, 'status' => 'pending', 'expires_at' => $now->copy()->addMinutes(15), 'created_at' => $now, 'updated_at' => $now]);
                // One guarded SQL update prevents stock from crossing below zero.
                $changed = DB::table('products')->where('id', $productId)->where('available', '>=', $quantity)->decrement('available', $quantity, ['updated_at' => $now]);
                abort_unless($changed === 1, 409, 'Insufficient available stock.');
                $this->audit($id, 'reserved');

                return ['data' => DB::table('reservations')->find($id), 'replayed' => false];
            }, 3);
        } catch (QueryException $e) {
            // A concurrent duplicate insert is rolled back before reading its winner.
            if (in_array($e->getCode(), ['23000', '23505'])) {
                $existing = DB::table('reservations')->where('user_id', $userId)->where('idempotency_key', $key)->first();
                if ($existing) {
                    return $this->replay($existing, $productId, $quantity);
                }
            }
            throw $e;
        }
    }

    private function replay(object $r, int $productId, int $quantity): array
    {
        abort_unless((int) $r->product_id === $productId && (int) $r->quantity === $quantity, 409, 'Idempotency key already used with different input.');

        return ['data' => $r, 'replayed' => true];
    }

    public function transition(string $id, int $userId, string $action): object
    {
        return DB::transaction(function () use ($id, $userId, $action) {
            $r = DB::table('reservations')->where('id', $id)->where('user_id', $userId)->lockForUpdate()->first();
            abort_unless($r, 404);
            if ($r->status === $action) {
                return $r;
            }
            abort_unless($r->status === 'pending', 409, 'Reservation is already '.$r->status.'.');
            $target = now()->gte($r->expires_at) ? 'expired' : $action;
            $this->finish($r, $target);

            return DB::table('reservations')->find($id);
        }, 3);
    }

    public function expire(): int
    {
        $count = 0;
        DB::table('reservations')->where('status', 'pending')->where('expires_at', '<=', now())->orderBy('id')->chunkById(100, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $count += DB::transaction(function () use ($row) {
                    $r = DB::table('reservations')->where('id', $row->id)->lockForUpdate()->first();
                    if (! $r || $r->status !== 'pending' || now()->lt($r->expires_at)) {
                        return 0;
                    }

                    return $this->finish($r, 'expired');
                }, 3);
            }
        }, 'id');

        return $count;
    }

    private function finish(object $r, string $status): int
    {
        $changed = DB::table('reservations')->where('id', $r->id)->where('status', 'pending')->update(['status' => $status, 'updated_at' => now()]);
        if ($changed === 1) {
            if ($status !== 'confirmed') {
                DB::table('products')->where('id', $r->product_id)->increment('available', $r->quantity, ['updated_at' => now()]);
            }
            $this->audit($r->id, $status);
        }

        return $changed;
    }

    private function audit(string $id,string $event): void
    {
        DB::table('audit_events')->insert(['reservation_id' => $id, 'event' => $event, 'created_at' => now()]);
    }
}
