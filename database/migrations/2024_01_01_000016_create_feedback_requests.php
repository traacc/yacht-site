<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Полиморфная связь: documentable_type = 'App\Models\Regatta' и т.д.
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->string('source')->nullable(); // откуда пришел запрос (например, "форма на сайте", "телефонный звонок" и т.п.)
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete(); // кто обработал запрос

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_requests');
    }
};
