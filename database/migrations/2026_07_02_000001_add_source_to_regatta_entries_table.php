<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            // Источник получения заявки:
            // personal_cabinet — подана пользователем из личного кабинета
            // quick_request    — подана через публичную быструю заявку (без входа)
            // admin            — создана администратором из админки
            $table->enum('source', ['personal_cabinet', 'quick_request', 'admin', 'unknown'])
                  ->default('unknown')
                  ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
