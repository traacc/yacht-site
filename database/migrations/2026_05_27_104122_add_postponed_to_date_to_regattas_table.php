<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->date('postponed_to_date')->nullable()->after('regatta_status');
            $table->foreignUuid('postponed_to_regatta_id')
                ->nullable()
                ->after('postponed_to_date')
                ->constrained('regattas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropForeign(['postponed_to_regatta_id']);
            $table->dropColumn('postponed_to_regatta_id');
            $table->dropColumn('postponed_to_date');
        });
    }
};
