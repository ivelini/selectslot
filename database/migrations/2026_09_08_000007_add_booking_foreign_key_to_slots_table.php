<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Привязка закрытия слота к записи (ФТ-16): FK добавляется после bookings — цикл ссылок
        Schema::table('slots', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('slots', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });
    }
};
