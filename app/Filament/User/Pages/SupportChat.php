<?php

declare(strict_types=1);

namespace App\Filament\User\Pages;

use App\Actions\Chat\StartSupportConversationAction;
use App\Livewire\Chat\ConversationThread;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Переписка со службой поддержки в личном кабинете.
 *
 * Полноразмерная версия плавающего виджета с публичных страниц: тот же диалог
 * и тот же компонент ленты. Сюда ведёт кнопка «Открыть» из письма, Telegram и
 * колокольчика.
 *
 * @see ConversationThread
 * @see ChatMessageReceivedNotification
 */
class SupportChat extends Page
{
    protected string $view = 'filament.user.pages.support-chat';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Поддержка';

    protected static ?string $title = 'Служба поддержки';

    protected static ?int $navigationSort = 1;

    public ?string $conversationId = null;

    public static function getNavigationGroup(): ?string
    {
        return 'Аккаунт';
    }

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        // На этой странице диалог заводим сразу: пользователь пришёл именно
        // ради переписки, а пустой тред оператор увидит только с первым
        // сообщением — в списке они сортируются по last_message_at.
        $this->conversationId = app(StartSupportConversationAction::class)
            ->handle($user)
            ->getKey();
    }
}
