<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            // Пояснение, выводимое вместо даты, когда регата перенесена,
            // но новая дата пока не известна.
            $table->string('postponed_note')->nullable()->after('postponed_to_date');
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropColumn('postponed_note');
        });
    }
};
