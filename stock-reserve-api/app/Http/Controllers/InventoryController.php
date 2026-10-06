<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InventoryController extends Controller
{
    public function register(Request $r)
    {
        $d = $r->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users', 'password' => 'required|string|min:12|max:128']);
        $user = User::create($d);

        return response()->json(['token' => $user->createToken('demo', ['*'], now()->addHours(8))->plainTextToken], 201);
    }

    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $u = User::where('email', $d['email'])->first();
        abort_unless($u && Hash::check($d['password'], $u->password), 401, 'Invalid credentials.');

        return ['token' => $u->createToken('demo', ['*'], now()->addHours(8))->plainTextToken];
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function products()
    {
        return DB::table('products')->orderBy('id')->paginate(20);
    }

    public function createProduct(Request $r)
    {
        abort_unless($r->user()->is_admin, 403);
        $d = $r->validate(['sku' => 'required|string|max:60|unique:products', 'name' => 'required|string|max:160', 'available' => 'required|integer|min:0|max:1000000']);
        $id = DB::table('products')->insertGetId($d + ['created_at' => now(), 'updated_at' => now()]);

        return response()->json(DB::table('products')->find($id), 201);
    }

    public function reservations(Request $r)
    {
        return DB::table('reservations')->where('user_id', $r->user()->id)->orderByDesc('created_at')->paginate(20);
    }

    public function reserve(Request $r, InventoryService $s)
    {
        $d = $r->validate(['product_id' => 'required|integer|exists:products,id', 'quantity' => 'required|integer|min:1|max:1000']);
        $key = $r->header('Idempotency-Key');
        validator(['key' => $key], ['key' => 'required|string|min:8|max:100|regex:/^[A-Za-z0-9_-]+$/'])->validate();
        $out = $s->reserve($r->user()->id, $d['product_id'], $d['quantity'], $key);

        return response()->json($out, $out['replayed'] ? 200 : 201);
    }

    public function confirm(Request $r, string $id, InventoryService $s)
    {
        return $s->transition($id, $r->user()->id, 'confirmed');
    }

    public function cancel(Request $r, string $id, InventoryService $s)
    {
        return $s->transition($id, $r->user()->id, 'cancelled');
    }

    public function audit(Request $r)
    {
        abort_unless($r->user()->is_admin, 403);

        return DB::table('audit_events')->orderByDesc('id')->paginate(50);
    }
}
