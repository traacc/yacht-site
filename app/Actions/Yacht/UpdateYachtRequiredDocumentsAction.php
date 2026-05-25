<?php

declare(strict_types=1);

namespace App\Actions\Yacht;

use App\Enums\YachtDocumentType;
use App\Services\SettingsService;

/**
 * Чтение и сохранение списка обязательных документов для яхт.
 *
 * Настройки хранятся в таблице settings с ключом 'yacht.required_documents'
 * в виде ассоциативного массива: doc_type => bool.
 */
final class UpdateYachtRequiredDocumentsAction
{
    private const SETTING_KEY = 'yacht.required_documents';
    private const SETTING_GROUP = 'yacht';

    /**
     * @param SettingsService $settings
     */
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Получить текущий список обязательных документов.
     *
     * Возвращает массив вида:
     *   ['orc_certificate' => true, 'ship_ticket' => true, 'insurance' => false, ...]
     *
     * Если настройка отсутствует — используются дефолтные значения
     * (orc_certificate, ship_ticket, insurance — обязательные).
     *
     * @return array<string, bool>
     */
    public function get(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        if (!is_array($stored)) {
            return $this->defaults();
        }

        $result = [];
        foreach (YachtDocumentType::configurable() as $type) {
            $result[$type->value] = (bool) ($stored[$type->value] ?? false);
        }

        return $result;
    }

    /**
     * Получить только обязательные типы документов (те, где value === true).
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public function getRequiredList(): array
    {
        return array_values(array_filter(
            array_map(
                fn (YachtDocumentType $type) => [
                    'doc_type' => $type->value,
                    'title'    => $type->label(),
                ],
                YachtDocumentType::configurable(),
            ),
            fn (array $doc) => $this->get()[$doc['doc_type']] ?? false,
        ));
    }

    /**
     * Сохранить настройки обязательности документов.
     *
     * @param array<string, bool> $data  Ассоциативный массив doc_type => bool.
     */
    public function save(array $data): void
    {
        $sanitized = [];
        foreach (YachtDocumentType::configurable() as $type) {
            $sanitized[$type->value] = (bool) ($data[$type->value] ?? false);
        }

        $this->settings->set(self::SETTING_KEY, $sanitized, self::SETTING_GROUP);
    }

    /**
     * Дефолтные значения: ORC-сертификат, судовой билет и страховка — обязательны.
     *
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        $defaultRequired = [
            YachtDocumentType::OrcCertificate,
            YachtDocumentType::ShipTicket,
            YachtDocumentType::Insurance,
        ];

        $result = [];
        foreach (YachtDocumentType::configurable() as $type) {
            $result[$type->value] = in_array($type, $defaultRequired, true);
        }

        return $result;
    }
}