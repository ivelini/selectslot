<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Готовые комплексы услуг (например, «Сезонный шиномонтаж»): справочник без собственной
     * цены — клик по комплексу отмечает входящие услуги (×4). В запись/снимок комплекс
     * не попадает: хранятся только услуги с количествами.
     */
    public function up(): void
    {
        Schema::create('complex_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('complex_service_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complex_service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete(); // услугу деактивируют, не удаляют
            $table->timestamps();

            $table->unique(['complex_service_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complex_service_item');
        Schema::dropIfExists('complex_services');
    }
};
