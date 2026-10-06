<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function product(int $stock = 5): int
    {
        return DB::table('products')->insertGetId(['sku' => 'SKU-'.uniqid(), 'name' => 'Keyboard', 'available' => $stock, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function customer(): User
    {
        return User::factory()->create();
    }

    private function reserve(User $u, int $id, int $qty = 2, string $key = 'unique-key-001')
    {
        return $this->actingAs($u, 'sanctum')->withHeader('Idempotency-Key', $key)->postJson('/api/v1/reservations', ['product_id' => $id, 'quantity' => $qty]);
    }

    public function test_api_requires_authentication(): void
    {
        $this->getJson('/api/v1/products')->assertUnauthorized();
    }

    public function test_customer_cannot_create_products_or_read_audit(): void
    {
        $u = $this->customer();
        $this->actingAs($u, 'sanctum')->postJson('/api/v1/products', ['sku' => 'A', 'name' => 'A', 'available' => 3])->assertForbidden();
        $this->getJson('/api/v1/audit')->assertForbidden();
    }

    public function test_reserve_and_replay_deduct_stock_once(): void
    {
        $id = $this->product();
        $u = $this->customer();
        $a = $this->reserve($u, $id)->assertCreated()->json('data.id');
        $this->reserve($u, $id)->assertOk()->assertJsonPath('data.id', $a)->assertJsonPath('replayed', true);
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 3]);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_key_cannot_be_reused_for_different_payload(): void
    {
        $id = $this->product();
        $u = $this->customer();
        $this->reserve($u, $id);
        $this->reserve($u, $id, 3)->assertConflict();
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 3]);
    }

    public function test_insufficient_stock_rolls_back_reservation_and_audit(): void
    {
        $id = $this->product(1);
        $this->reserve($this->customer(), $id, 2)->assertConflict();
        $this->assertDatabaseCount('reservations', 0);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 1]);
    }

    public function test_sequential_competing_customers_cannot_oversell(): void
    {
        $id = $this->product(3);
        $this->reserve($this->customer(), $id, 2, 'customer-one')->assertCreated();
        $this->reserve($this->customer(), $id, 2, 'customer-two')->assertConflict();
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 1]);
    }

    public function test_cancellation_restores_stock_only_once(): void
    {
        $id = $this->product();
        $u = $this->customer();
        $rid = $this->reserve($u, $id)->json('data.id');
        $this->postJson('/api/v1/reservations/'.$rid.'/cancel')->assertOk()->assertJsonPath('status', 'cancelled');
        $this->postJson('/api/v1/reservations/'.$rid.'/cancel')->assertOk();
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 5]);
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_confirmation_does_not_release_stock_and_cannot_be_cancelled(): void
    {
        $id = $this->product();
        $rid = $this->reserve($this->customer(), $id)->json('data.id');
        $this->postJson('/api/v1/reservations/'.$rid.'/confirm')->assertOk()->assertJsonPath('status', 'confirmed');
        $this->postJson('/api/v1/reservations/'.$rid.'/cancel')->assertConflict();
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 3]);
    }

    public function test_other_customer_cannot_access_or_change_reservation(): void
    {
        $id = $this->product();
        $rid = $this->reserve($this->customer(), $id)->json('data.id');
        $this->actingAs($this->customer(), 'sanctum')->postJson('/api/v1/reservations/'.$rid.'/cancel')->assertNotFound();
        $this->getJson('/api/v1/reservations')->assertJsonCount(0, 'data');
    }

    public function test_expiration_job_is_repeatable(): void
    {
        $id = $this->product();
        $rid = $this->reserve($this->customer(), $id)->json('data.id');
        $this->travel(16)->minutes();
        $this->artisan('inventory:expire')->assertSuccessful();
        $this->artisan('inventory:expire')->assertSuccessful();
        $this->assertDatabaseHas('reservations', ['id' => $rid, 'status' => 'expired']);
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 5]);
        $this->assertDatabaseCount('audit_events', 2);
    }

    public function test_confirming_after_expiry_returns_expired_and_releases_stock(): void
    {
        $id = $this->product();
        $rid = $this->reserve($this->customer(), $id)->json('data.id');
        $this->travel(16)->minutes();
        $this->postJson('/api/v1/reservations/'.$rid.'/confirm')->assertOk()->assertJsonPath('status', 'expired');
        $this->assertDatabaseHas('products', ['id' => $id, 'available' => 5]);
    }

    public function test_input_validation(): void
    {
        $id = $this->product();
        $u = $this->customer();
        $this->reserve($u, $id, 0)->assertUnprocessable();
        $this->reserve($u, $id, 1, 'bad')->assertUnprocessable();
    }

    public function test_register_cannot_make_admin_and_token_works(): void
    {
        $token = $this->postJson('/api/v1/register', ['name' => 'Tester', 'email' => 'tester@example.test', 'password' => 'example-password-123', 'is_admin' => true])->assertCreated()->json('token');
        $this->assertDatabaseHas('users', ['email' => 'tester@example.test', 'is_admin' => false]);
        $this->withToken($token)->getJson('/api/v1/products')->assertOk();
        $this->postJson('/api/v1/logout')->assertNoContent();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->postJson('/api/v1/login', ['email' => 'missing@example.test', 'password' => 'not-password'])->assertUnauthorized();
    }

    public function test_admin_can_create_product(): void
    {
        $u = $this->customer();
        $u->is_admin = true;
        $u->save();
        $this->actingAs($u,'sanctum')->postJson('/api/v1/products',['sku' => 'NEW', 'name' => 'Monitor', 'available' => 4])->assertCreated();
    }
}
