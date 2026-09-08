<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Реальный прайс не дифференцирует цену по RunFlat/TPMS — это доп. работы, а не оси правил:
     * опции удаляются из прайс-правил, снимка записи и карточки авто. Цена в прайсе — за единицу
     * (1 колесо/шт), поэтому строка состава получает количество.
     */
    public function up(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropUnique('price_rules_combo_unique');
            $table->dropColumn(['has_runflat', 'has_tpms']);
            $table->unique(['service_id', 'radius', 'car_type'], 'price_rules_combo_unique');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['has_runflat', 'has_tpms']);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->dropColumn(['has_runflat', 'has_tpms']);
        });

        Schema::table('booking_services', function (Blueprint $table) {
            $table->unsignedTinyInteger('quantity')->default(1); // 1–4, цена строки — за единицу
        });
    }

    public function down(): void
    {
        Schema::table('price_rules', function (Blueprint $table) {
            $table->dropUnique('price_rules_combo_unique');
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
            $table->unique(['service_id', 'radius', 'car_type', 'has_runflat', 'has_tpms'], 'price_rules_combo_unique');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
        });

        Schema::table('cars', function (Blueprint $table) {
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
        });

        Schema::table('booking_services', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
