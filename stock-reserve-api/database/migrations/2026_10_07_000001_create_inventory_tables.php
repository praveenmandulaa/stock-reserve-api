<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->unsignedInteger('available');
            $t->timestamps();
        });
        Schema::create('reservations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('product_id')->constrained();
            $t->unsignedInteger('quantity');
            $t->string('idempotency_key', 100);
            $t->string('status')->default('pending');
            $t->timestamp('expires_at');
            $t->timestamps();
            $t->unique(['user_id', 'idempotency_key']);
            $t->index(['status', 'expires_at']);
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('reservation_id');
            $t->foreign('reservation_id')->references('id')->on('reservations');
            $t->string('event');
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('personal_access_tokens');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
