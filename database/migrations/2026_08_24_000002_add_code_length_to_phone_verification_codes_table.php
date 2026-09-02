<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сколько цифр в коде — свойство кампании Flash Call на стороне zvonok.com,
 * а не константа сайта: провайдер может звонить с номера, у которого «кодовых»
 * цифр не четыре. Храним фактическую длину пришедшего pincode, чтобы форма
 * просила и проверяла ровно столько цифр, сколько будет в звонке.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->unsignedTinyInteger('code_length')->nullable()->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->dropColumn('code_length');
        });
    }
};
