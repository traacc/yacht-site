<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Полиморфная связь: documentable_type = 'App\Models\Regatta' и т.д.
            $table->string('documentable_type');
            $table->uuid('documentable_id');
            // Тип документа: regulation, race_instructions, charter, protocol и т.п.
            $table->string('doc_type');
            $table->string('title');
            $table->string('url');
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['documentable_type', 'documentable_id'], 'documents_poly_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
