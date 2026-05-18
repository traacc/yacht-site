<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yachts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            // Номер ВФПС — уникальный идентификатор яхты в ассоциации
            $table->string('vfps_number')->unique();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gims_number')->nullable();
            $table->string('orc_cert_url')->nullable();
            $table->string('class')->nullable();
            // Тип парусов: dacron, laminate, mixed
            //$table->enum('sail_type', ['dacron', 'laminate', 'mixed'])->nullable();
            $table->string('project')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('reg_place')->nullable();
            $table->decimal('current_mass_kg', 8, 2)->nullable();

            // Статус яхты в системе: pending — ожидает одобрения администратора
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('rejection_reason')->nullable();
            $table->boolean('is_archived')->default(false);

            $table->string('owner_name')->nullable();
            $table->string('owner_email')->nullable();
            $table->string('owner_phone')->nullable();
            $table->string('owner_photo')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('yachts');
    }
};
