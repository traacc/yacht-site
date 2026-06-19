<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yacht_ownership_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Яхта, владение которой запрашивается
            $table->foreignUuid('yacht_id')->constrained('yachts')->cascadeOnDelete();

            // Кто запрашивает передачу владения
            $table->foreignUuid('requester_id')->constrained('users')->cascadeOnDelete();

            // Текущий владелец на момент подачи заявки (снимок)
            $table->foreignUuid('previous_owner_id')->nullable()->constrained('users')->nullOnDelete();

            // pending — на рассмотрении, approved — одобрено, rejected — отклонено
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            // Комментарий заявителя и причина отказа
            $table->text('comment')->nullable();
            $table->string('rejection_reason')->nullable();

            // Кто и когда рассмотрел заявку
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yacht_ownership_transfers');
    }
};
