<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_rental_requests', function (Blueprint $table) {
            // Статус обработки заявки владельцем яхты.
            $table->string('status')->default('pending')->after('comment');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('yacht_rental_requests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
