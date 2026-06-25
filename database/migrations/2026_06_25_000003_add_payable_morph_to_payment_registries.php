<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            // Полиморфная связь: payable_type = 'App\Models\RegattaEntry' и т.д.
            // Nullable — реестр может существовать и без привязки к другой модели.
            $table->string('payable_type')->nullable()->after('document');
            $table->uuid('payable_id')->nullable()->after('payable_type');

            $table->index(['payable_type', 'payable_id'], 'payment_registries_payable_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_registries', function (Blueprint $table) {
            $table->dropIndex('payment_registries_payable_index');
            $table->dropColumn(['payable_type', 'payable_id']);
        });
    }
};
