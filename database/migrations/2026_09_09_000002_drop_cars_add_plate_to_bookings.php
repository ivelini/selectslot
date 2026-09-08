<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Сущность «автомобиль клиента» упразднена (решение): параметры и госномер —
     * снимок записи (bookings.radius/car_type/plate); при повторной записи оператор
     * подставляет параметры из последней записи клиента.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('car_id');
            $table->string('plate')->nullable(); // госномер из заявки (снимок)
        });

        Schema::dropIfExists('cars');
    }

    public function down(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('plate')->nullable();
            $table->unsignedSmallInteger('radius')->nullable();
            $table->string('car_type')->nullable();
            $table->timestamps();
            $table->index('customer_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('plate');
            $table->foreignId('car_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
