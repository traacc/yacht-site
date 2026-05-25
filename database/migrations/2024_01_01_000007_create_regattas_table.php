<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regattas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('season_id')->constrained('seasons')->restrictOnDelete();
            // Серия необязательна
            $table->foreignUuid('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->string('name');
            // Коэффициент важности регаты — множитель очков
            $table->decimal('level_coefficient', 4, 2)->default(1.00);
            $table->date('date_start');
            $table->date('date_end');
            $table->string('background_image')->nullable();
            $table->string('location')->nullable();
            $table->string('water_area')->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->text('regulations')->nullable();
            $table->json('coordinates')->nullable();
            $table->unsignedTinyInteger('race_days_count')->default(1);
            $table->unsignedTinyInteger('races_count')->default(1);
            $table->text('prizes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regattas');
    }
};
