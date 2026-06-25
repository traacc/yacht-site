<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->boolean('entry_fee_required')->default(false)->after('prizes');
            $table->decimal('entry_fee_amount', 10, 2)->nullable()->after('entry_fee_required');
        });

        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->boolean('fee_paid')->default(false)->after('documents_complete');
        });
    }

    public function down(): void
    {
        Schema::table('regattas', function (Blueprint $table) {
            $table->dropColumn(['entry_fee_required', 'entry_fee_amount']);
        });

        Schema::table('regatta_entries', function (Blueprint $table) {
            $table->dropColumn('fee_paid');
        });
    }
};
