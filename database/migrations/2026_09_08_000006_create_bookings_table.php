<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('slot_id')->constrained()->restrictOnDelete();
            $table->time('start_time');
            $table->string('status')->default('confirmed');
            $table->string('source')->default('site');
            $table->string('cancel_reason')->nullable();
            $table->string('confirmation_code_hash')->nullable();
            $table->uuid('idempotency_key')->nullable()->unique();
            // снимок параметров и цены на момент создания (ADR 0004)
            $table->unsignedSmallInteger('radius');
            $table->string('car_type');
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
            $table->unsignedInteger('total_price'); // копейки
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('slot_id');
            $table->index('customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
