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
            $table->json('payload'); // заявка: снимок услуг с ценами, авто, дата+час
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable(); // код создаёт ровно одну запись
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_codes');
    }
};
