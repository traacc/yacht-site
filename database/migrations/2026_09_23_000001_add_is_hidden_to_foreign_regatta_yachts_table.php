<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Явное снятие лодки с витрины.
 *
 * Раньше эту роль исполняла занятость: статус «Занята» гасил и чартер целиком,
 * и места, и каюты. На практике так нельзя было сказать «лодку целиком уже
 * забрали, но в экипаж ещё берём» — статус занимал сразу всё.
 *
 * Теперь занятость отвечает только за чартер целиком, места и каюты гаснут по
 * свободным местам, а «убрать лодку со страницы совсем» — вот этот флаг.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->boolean('is_hidden')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('foreign_regatta_yachts', function (Blueprint $table): void {
            $table->dropColumn('is_hidden');
        });
    }
};
