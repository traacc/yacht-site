<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_news_candidates', function (Blueprint $table): void {
            $table->string('image_url', 2048)->nullable()->after('source_hash');
        });
    }

    public function down(): void
    {
        Schema::table('ai_news_candidates', function (Blueprint $table): void {
            $table->dropColumn('image_url');
        });
    }
};
