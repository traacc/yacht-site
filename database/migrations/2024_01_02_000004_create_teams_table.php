<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // Организатор команды; SET NULL при удалении (команда не удаляется)
            $table->foreignUuid('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('default_yacht_id')
                ->nullable()
                ->constrained('yachts')
                ->nullOnDelete();
            //$table->foreignUuid('yacht_id')->nullable()->constrained('yachts')->nullOnDelete(); // default yacht for the team
            $table->boolean('is_archived')->default(false);
            $table->string('picture')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
