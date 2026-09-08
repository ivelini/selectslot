<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete(); // история: услугу деактивируют, не удаляют
            $table->unsignedInteger('price'); // цена строки на момент записи, копейки
            $table->timestamps();

            $table->unique(['booking_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_services');
    }
};
