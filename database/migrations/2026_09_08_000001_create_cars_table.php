<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plate')->nullable();
            $table->unsignedSmallInteger('radius')->nullable(); // R13–R22
            $table->string('car_type')->nullable();
            $table->boolean('has_runflat')->default(false);
            $table->boolean('has_tpms')->default(false);
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
