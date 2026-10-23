<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Сбор за береговую программу: цена за человека у регаты и число человек,
 * за которых платит заявка. NULL у регаты — сбора нет, у заявки — не выбрано.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regattas', function (Blueprint $table): void {
            $table->decimal('shore_fee_per_person', 10, 2)->nullable()->after('entry_fee_amount');
        });

        Schema::table('regatta_entries', function (Blueprint $table): void {
            $table->unsignedTinyInteger('shore_program_persons')->nullable()->after('seats');
        });
    }

    public function down(): void
    {
        Schema::table('regatta_entries', function (Blueprint $table): void {
            $table->dropColumn('shore_program_persons');
        });

        Schema::table('regattas', function (Blueprint $table): void {
            $table->dropColumn('shore_fee_per_person');
        });
    }
};
