<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignUuid('default_yacht_id')
                ->nullable()
                ->after('organizer_id')
                ->constrained('yachts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['default_yacht_id']);
            $table->dropColumn('default_yacht_id');
        });
    }
};
