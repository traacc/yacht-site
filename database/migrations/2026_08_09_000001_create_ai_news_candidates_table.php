<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Очередь модерации AI-новостей и атрибуция опубликованных внешних материалов.
 *
 * Кандидаты намеренно хранятся отдельно от news: News автоматически получает
 * published_at и запускает observer, поэтому использовать её как staging нельзя.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $table): void {
            $table->string('external_url', 2048)->nullable()->change();
            $table->string('source_name')->nullable()->after('external_url');
            $table->timestamp('source_published_at')->nullable()->after('source_name');
        });

        Schema::create('ai_news_candidates', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('news_id')->nullable()->constrained('news')->nullOnDelete();

            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('content');

            $table->string('source_name');
            $table->string('source_url', 2048);
            $table->char('source_hash', 64)->unique();
            $table->timestamp('source_published_at')->nullable();

            // Строка, а не MySQL ENUM: статусы можно расширять без сырого ALTER.
            $table->string('status', 32)->default('pending');
            $table->unsignedTinyInteger('relevance_score')->default(0);
            $table->text('selection_reason')->nullable();

            $table->string('ai_response_id')->nullable();
            $table->string('ai_model')->nullable();
            $table->timestamp('discovered_at');
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'discovered_at']);
            $table->index(['status', 'relevance_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_news_candidates');

        Schema::table('news', function (Blueprint $table): void {
            $table->dropColumn(['source_name', 'source_published_at']);
            $table->string('external_url')->nullable()->change();
        });
    }
};
