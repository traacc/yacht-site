<?php

declare(strict_types=1);

namespace App\Enums;

enum YachtDocumentType: string
{
    case OrcCertificate = 'orc_certificate';
    case ShipTicket     = 'ship_ticket';
    case Insurance      = 'insurance';
    /*case Regulation     = 'regulation';
    case RaceInstructions = 'race_instructions';
    case Charter        = 'charter';
    case Protocol       = 'protocol';
    case Other          = 'other';*/

    public function label(): string
    {
        return match ($this) {
            self::OrcCertificate   => 'ORC-сертификат',
            self::ShipTicket       => 'Судовой билет',
            self::Insurance        => 'Страховка',

        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $acc, self $case) => $acc + [$case->value => $case->label()],
            [],
        );
    }

    /**
     * Типы, доступные для настройки обязательности.
     * Не все типы документов имеет смысл делать обязательными (например 'other').
     *
     * @return self[]
     */
    public static function configurable(): array
    {
        return [
            self::OrcCertificate,
            self::ShipTicket,
            self::Insurance,
            /*self::Regulation,
            self::RaceInstructions,
            self::Charter,
            self::Protocol,*/
        ];
    }
}
