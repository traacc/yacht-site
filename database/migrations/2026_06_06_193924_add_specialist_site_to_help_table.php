<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help', function (Blueprint $table) {
            $table->string('specialist_site')->nullable()->after('specialist_city');
        });
    }

    public function down(): void
    {
        Schema::table('help', function (Blueprint $table) {
            $table->dropColumn('specialist_site');
        });
    }
};
