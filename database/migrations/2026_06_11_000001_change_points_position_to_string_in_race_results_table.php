<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('race_results', function (Blueprint $table) {
            $table->string('position', 20)->nullable()->change();
            $table->string('points', 20)->default('0')->change();
        });
    }

    public function down(): void
    {
        Schema::table('race_results', function (Blueprint $table) {
            $table->unsignedSmallInteger('position')->nullable()->change();
            $table->decimal('points', 8, 3)->default(0)->change();
        });
    }
};
