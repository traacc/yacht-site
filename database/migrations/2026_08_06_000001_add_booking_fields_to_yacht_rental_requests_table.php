<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_rental_requests', function (Blueprint $table) {
            // Витрина бронирования спрашивает email: подтверждение брони и
            // счёт уходят на почту, а раньше в заявке был только телефон.
            $table->string('email')->nullable()->after('phone');

            // Отметка о принятии условий аренды — доказательство согласия.
            // Хранится время, а не флаг: важно, когда именно его дали.
            $table->timestamp('agreement_accepted_at')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('yacht_rental_requests', function (Blueprint $table) {
            $table->dropColumn(['email', 'agreement_accepted_at']);
        });
    }
};
