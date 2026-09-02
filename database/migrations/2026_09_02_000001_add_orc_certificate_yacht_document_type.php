<?php

declare(strict_types=1);

use App\Enums\DocumentOwner;
use App\Models\YachtDocumentType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Тип документа «ORC-сертификат» для яхты.
     *
     * is_configurable = false — намеренно: настраиваемые типы попадают
     * в списки обязательных документов и яхты, и заявки на регату
     * (UpdateYachtRequiredDocumentsAction / UpdateRegattaEntryRequiredDocumentsAction
     * читают cachedConfigurable() без учёта owner), а ORC грузится
     * отдельным полем формы яхты и обязательным для заявки быть не должен.
     */
    public function up(): void
    {
        YachtDocumentType::updateOrCreate(
            ['key' => 'orc_certificate'],
            [
                'label' => 'ORC-сертификат',
                'description' => 'Сертификат ORC с гоночным баллом и параметрами яхты.',
                'is_configurable' => false,
                'owner' => DocumentOwner::Yacht,
                'sort_order' => 10,
            ],
        );

        YachtDocumentType::flushCache();
    }

    public function down(): void
    {
        YachtDocumentType::where('key', 'orc_certificate')->delete();

        YachtDocumentType::flushCache();
    }
};
