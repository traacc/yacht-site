<?php

declare(strict_types=1);

use App\Enums\DocumentOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yacht_document_types', function (Blueprint $table) {
            $table->string('owner')->nullable()
                ->comment('Принадлежность типа: yacht — яхта, regatta_entry — заявка на регату, null — оба контекста')
                ->after('is_configurable');
        });
    }

    public function down(): void
    {
        Schema::table('yacht_document_types', function (Blueprint $table) {
            $table->dropColumn('owner');
        });
    }
};
