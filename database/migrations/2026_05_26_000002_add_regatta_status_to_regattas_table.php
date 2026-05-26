<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->string('regatta_status')->default('upcoming')->after('prizes');
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropColumn('regatta_status');
        });
    }
};