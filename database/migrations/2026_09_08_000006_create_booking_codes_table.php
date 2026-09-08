<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('code_hash'); // хэш кода из SMS, не plaintext
            $table->timestamp('used_at')->nullable(); // код создаёт ровно одну запись
            // заявка до подтверждения не хранится: при вводе кода передаётся актуальный выбор (одна SMS на цикл)
            // TTL кода без поля: просрочка = created_at + reservation_timeout_min (крон чистит неиспользованные)
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_codes');
    }
};
