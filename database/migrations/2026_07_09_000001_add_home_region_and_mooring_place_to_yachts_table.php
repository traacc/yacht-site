<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->string('home_region')->nullable()->after('reg_place');
            $table->string('mooring_place')->nullable()->after('home_region');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn(['home_region', 'mooring_place']);
        });
    }
};
