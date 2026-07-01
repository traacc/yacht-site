<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_rentals', function (Blueprint $table) {
            // Аренда больше не привязана к регатам — задаётся диапазоном дат.
            // Снимаем FK на regatta_id, затем даём yacht_id отдельный индекс,
            // иначе MySQL не даст удалить составной unique (он поддерживает FK
            // yacht_id), после чего удаляем составной unique и колонки.
            $table->dropForeign(['regatta_id']);
            $table->index('yacht_id');
            $table->dropUnique(['yacht_id', 'regatta_id']);
            $table->dropColumn(['regatta_id', 'price']);
        });

        Schema::table('yacht_rentals', function (Blueprint $table) {
            // Период доступности яхты для аренды.
            $table->date('date_start')->nullable()->after('yacht_id');
            $table->date('date_end')->nullable()->after('date_start');

            // Стоимость аренды за один день в пределах диапазона.
            $table->decimal('price_event', 12, 2)->nullable()->after('date_end'); // для мероприятий
            $table->decimal('price_pro', 12, 2)->nullable()->after('price_event'); // для профессиональных команд
        });
    }

    public function down(): void
    {
        Schema::table('yacht_rentals', function (Blueprint $table) {
            $table->dropColumn(['date_start', 'date_end', 'price_event', 'price_pro']);

            $table->foreignUuid('regatta_id')->nullable()->constrained('regattas')->cascadeOnDelete();
            $table->unique(['yacht_id', 'regatta_id']);
            $table->dropIndex(['yacht_id']);
            $table->decimal('price', 12, 2)->nullable();
        });
    }
};
