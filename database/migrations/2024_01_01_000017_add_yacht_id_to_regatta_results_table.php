<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            $table->foreignUuid('yacht_id')
                ->nullable()
                ->after('team_id')
                ->constrained('yachts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regatta_results', function (Blueprint $table) {
            $table->dropForeign(['yacht_id']);
            $table->dropColumn('yacht_id');
        });
    }
};
