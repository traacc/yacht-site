<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            // Момент фактической оплаты. Nullable — известен только для онлайн-платежей
            // и платежей, отмеченных вручную после этой миграции.
            $table->timestamp('paid_at')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            $table->dropColumn('paid_at');
        });
    }
};
