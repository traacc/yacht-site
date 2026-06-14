<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('votings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->boolean('is_anonymous')->default(false);   // скрывать, кто как голосовал
            $table->boolean('allow_multiple')->default(false); // можно выбрать несколько вариантов
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('voting_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('voting_id')->constrained('votings')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('voting_id')->constrained('votings')->cascadeOnDelete();
            $table->foreignUuid('voting_option_id')->constrained('voting_options')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // нельзя дважды проголосовать за один и тот же вариант
            $table->unique(['voting_option_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votings');
        Schema::dropIfExists('voting_options');
        Schema::dropIfExists('votes');
    }
};
