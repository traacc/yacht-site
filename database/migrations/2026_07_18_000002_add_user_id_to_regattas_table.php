<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            // Автор регаты. NULL — регата «ничья» (создана до введения роли,
            // импортом или консольной командой) и доступна только админам.
            $table->foreignUuid('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
