<?php

declare(strict_types=1);

namespace App\Actions\Yacht;

use App\Models\YachtDocumentType as YachtDocumentTypeModel;
use App\Services\SettingsService;

/**
 * Чтение и сохранение списка обязательных документов для яхт.
 *
 * Типы документов хранятся в таблице yacht_document_types (динамически),
 * настройки обязательности — в settings с ключом 'yacht.required_documents'.
 *
 * Дефолтные значения задаются через поле is_default в модели YachtDocumentType
 * или через устаревший enum YachtDocumentType (fallback).
 */
final class UpdateYachtRequiredDocumentsAction
{
    private const SETTING_KEY = 'yacht.required_documents';
    private const SETTING_GROUP = 'yacht';

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Получить текущий список обязательных документов.
     *
     * Возвращает ассоциативный массив key => bool для всех настраиваемых типов.
     *
     * @return array<string, bool>
     */
    public function get(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY);

        $result = [];
        foreach ($this->configurableTypes() as $type) {
            $key = $type['key'];
            if (is_array($stored)) {
                $result[$key] = (bool) ($stored[$key] ?? $type['is_default']);
            } else {
                $result[$key] = $type['is_default'];
            }
        }

        return $result;
    }

    /**
     * Получить только обязательные типы документов (value === true).
     *
     * @return array<int, array{doc_type: string, title: string}>
     */
    public function getRequiredList(): array
    {
        $required = $this->get();

        return array_values(array_filter(
            array_map(
                fn (YachtDocumentTypeModel $type) => [
                    'doc_type' => $type->key,
                    'title'    => $type->label,
                ],
                YachtDocumentTypeModel::cachedConfigurable()->all(),
            ),
            fn (array $doc) => $required[$doc['doc_type']] ?? false,
        ));
    }

    /**
     * Сохранить настройки обязательности документов.
     *
     * @param array<string, bool> $data  Ассоциативный массив key => bool.
     */
    public function save(array $data): void
    {
        $sanitized = [];
        foreach ($this->configurableTypes() as $type) {
            $key = $type['key'];
            $sanitized[$key] = (bool) ($data[$key] ?? false);
        }

        $this->settings->set(self::SETTING_KEY, $sanitized, self::SETTING_GROUP);
    }

    /**
     * Дефолтные значения на основе устаревшего enum (fallback, если таблица пуста).
     *
     * @return array<string, bool>
     */
    public function defaults(): array
    {
        $defaultKeys = [
            'orc_certificate',
            'ship_ticket',
            'insurance',
        ];

        $result = [];
        foreach ($this->configurableTypes() as $type) {
            $result[$type['key']] = in_array($type['key'], $defaultKeys, true);
        }

        return $result;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * @return array<int, array{key: string, is_default: bool}>
     */
    private function configurableTypes(): array
    {
        return YachtDocumentTypeModel::cachedConfigurable()
            ->map(fn (YachtDocumentTypeModel $t) => [
                'key'        => $t->key,
                'is_default' => in_array($t->key, ['orc_certificate', 'ship_ticket', 'insurance'], true),
            ])
            ->all();
    }
}