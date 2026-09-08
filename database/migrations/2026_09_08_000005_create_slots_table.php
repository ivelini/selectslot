<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->unsignedTinyInteger('hour'); // 0–23
            $table->boolean('is_closed')->default(false);
            $table->string('close_reason')->nullable();
            $table->unsignedBigInteger('booking_id')->nullable(); // FK на bookings добавляется позже (цикл)
            $table->timestamps();

            $table->unique(['date', 'hour']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slots');
    }
};
