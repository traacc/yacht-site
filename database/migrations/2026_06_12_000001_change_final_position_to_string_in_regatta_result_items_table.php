<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->string('final_position')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('regatta_result_items', function (Blueprint $table) {
            $table->unsignedSmallInteger('final_position')->nullable()->change();
        });
    }
};
