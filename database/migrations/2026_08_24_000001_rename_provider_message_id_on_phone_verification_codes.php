<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Подтверждение телефона переехало с SMS (i-digital direct) на звонок
 * Flash Call (zvonok.com): вместо идентификатора сообщения храним
 * идентификатор звонка.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->renameColumn('provider_message_id', 'provider_call_id');
        });
    }

    public function down(): void
    {
        Schema::table('phone_verification_codes', function (Blueprint $table) {
            $table->renameColumn('provider_call_id', 'provider_message_id');
        });
    }
};
