<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('question', 500)->comment('Вопрос');
            $table->text('answer')->comment('Ответ (HTML)');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Переносим существующие вопросы из настройки home.faq в новую таблицу
        $raw = DB::table('settings')->where('key', 'home.faq')->value('value');

        if ($raw) {
            $items = json_decode($raw, true) ?: [];
            $sort = 0;

            foreach ($items as $item) {
                if (empty($item['question']) || empty($item['answer'])) {
                    continue;
                }

                DB::table('faqs')->insert([
                    'id' => (string) Str::uuid(),
                    'question' => mb_substr((string) $item['question'], 0, 500),
                    'answer' => (string) $item['answer'],
                    'is_active' => true,
                    'sort_order' => $sort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Настройка больше не используется — данные теперь в таблице faqs
        DB::table('settings')->where('key', 'home.faq')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('faqs');
    }
};
