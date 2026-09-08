<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('radius');
            $table->string('car_type');
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
            $table->unsignedInteger('price'); // копейки
            $table->timestamps();

            $table->unique(['service_id', 'radius', 'car_type', 'has_runflat', 'has_tpms'], 'price_rules_combo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_rules');
    }
};
