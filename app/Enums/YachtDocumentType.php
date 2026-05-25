<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * @deprecated Используйте модель App\Models\YachtDocumentType.
 *
 * Сохранён для обратной совместимости. Методы делегируют чтение
 * к таблице yacht_document_types.
 */
enum YachtDocumentType: string
{
    case OrcCertificate   = 'orc_certificate';
    case ShipTicket       = 'ship_ticket';
    case Insurance        = 'insurance';
    case Regulation       = 'regulation';
    case RaceInstructions = 'race_instructions';
    case Charter          = 'charter';
    case Protocol         = 'protocol';
    case Other            = 'other';

    public function label(): string
    {
        $model = \App\Models\YachtDocumentType::cachedAll()
            ->first(fn (\App\Models\YachtDocumentType $t) => $t->key === $this->value);

        return $model?->label ?? $this->legacyLabel();
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return \App\Models\YachtDocumentType::options();
    }

    /**
     * @return self[]
     */
    public static function configurable(): array
    {
        return \App\Models\YachtDocumentType::cachedConfigurable()
            ->map(fn (\App\Models\YachtDocumentType $t) => self::tryFrom($t->key))
            ->filter()
            ->all();
    }

    private function legacyLabel(): string
    {
        return match ($this) {
            self::OrcCertificate   => 'ORC-сертификат',
            self::ShipTicket       => 'Судовой билет',
            self::Insurance        => 'Страховка',
            self::Regulation       => 'Положение',
            self::RaceInstructions => 'Гоночная инструкция',
            self::Charter          => 'Устав',
            self::Protocol         => 'Протокол',
            self::Other            => 'Прочее',
        };
    }
}
