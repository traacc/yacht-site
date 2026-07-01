<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->json('suitable_for')->nullable()->after('past_regattas');
        });
    }

    public function down(): void
    {
        Schema::table('yachts', function (Blueprint $table) {
            $table->dropColumn('suitable_for');
        });
    }
};
