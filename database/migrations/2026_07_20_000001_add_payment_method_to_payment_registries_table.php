<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            // Способ оплаты (App\Enums\PaymentMethod). Nullable — может быть неизвестен.
            $table->string('payment_method')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
