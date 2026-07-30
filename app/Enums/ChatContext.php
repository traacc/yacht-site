<?php

declare(strict_types=1);

namespace App\Enums;

use App\Actions\Chat\StartSupportConversationAction;
use App\Livewire\Chat\SupportChatWidget;
use App\Models\Help;
use Illuminate\Database\Eloquent\Model;

/**
 * Откуда пользователь открыл чат поддержки.
 *
 * Белый список: событие `open-support-chat` приходит из браузера, поэтому ни
 * произвольную тему обращения, ни произвольный morph-класс принимать нельзя —
 * только алиас из этого перечисления. Глобальный morph map (enforceMorphMap)
 * для этого не годится: media.model_type в проекте хранит FQCN.
 *
 * Добавление нового контекста: кейс здесь + `context: '<алиас>'` в кнопке.
 *
 * @see SupportChatWidget::open()
 * @see StartSupportConversationAction
 */
enum ChatContext: string
{
    case SiteHelp = 'site-help';
    case Faq = 'faq';
    case HelpSpecialist = 'help-specialist';

    /** Тема обращения, которую видит оператор. */
    public function label(): string
    {
        return match ($this) {
            self::SiteHelp => 'Вопрос по работе сайта',
            self::Faq => 'Вопрос из раздела F.A.Q.',
            self::HelpSpecialist => 'Вопрос по специалисту из справочника',
        };
    }

    /**
     * Модель, на которую ссылается контекст (записывается в subject_*).
     *
     * @return class-string<Model>|null null — контекст только «этикеточный», без объекта.
     */
    public function modelClass(): ?string
    {
        return match ($this) {
            self::HelpSpecialist => Help::class,
            self::SiteHelp, self::Faq => null,
        };
    }
}
