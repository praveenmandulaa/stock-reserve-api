<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('inventory.demo_password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Set DEMO_PASSWORD to at least 12 characters before seeding.');
        }
        foreach (['admin', 'customer'] as $role) {
            $u = User::firstOrCreate(['email' => $role.'@example.test'], ['name' => ucfirst($role), 'password' => $password]);
            $u->is_admin = $role === 'admin';
            $u->save();
        }
        foreach ([['SKU-KB', 'Mechanical Keyboard', 12], ['SKU-MOUSE', 'Wireless Mouse', 8], ['SKU-MON', 'USB-C Monitor', 3]] as [$sku,$name,$qty]) {
            DB::table('products')->insertOrIgnore(['sku' => $sku, 'name' => $name, 'available' => $qty, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
