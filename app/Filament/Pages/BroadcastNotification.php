<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\NotificationCategory;
use App\Filament\Concerns\RestrictsAccessByRole;
use App\Jobs\SendUserNotificationChunk;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Массовая рассылка уведомлений пользователям сайта.
 *
 * Каналы доставки для каждого получателя определяются его настройками в
 * личном кабинете — здесь задаётся только категория и содержимое.
 */
class BroadcastNotification extends Page
{
    use RestrictsAccessByRole;

    /** Размер порции получателей на одну job. */
    private const CHUNK_SIZE = 500;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Рассылка уведомлений';

    protected static ?string $title = 'Рассылка уведомлений';

    protected static ?int $navigationSort = 91;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'category' => NotificationCategory::Important->value,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('send')
                ->footer([
                    Actions::make($this->getFormActions())
                        ->key('form-actions'),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Сообщение')
                    ->description('Уведомление получат все пользователи, не отписавшиеся от выбранной категории. Способ доставки каждый выбирает сам в личном кабинете.')
                    ->schema([
                        Select::make('category')
                            ->label('Категория')
                            ->options(NotificationCategory::class)
                            ->required()
                            ->helperText('От неё зависит, кто получит рассылку: пользователь мог отписаться от конкретной категории.'),

                        TextInput::make('subject')
                            ->label('Заголовок')
                            ->required()
                            ->maxLength(150),

                        Textarea::make('message')
                            ->label('Текст')
                            ->required()
                            ->rows(6)
                            ->maxLength(2000),

                        TextInput::make('link')
                            ->label('Ссылка (необязательно)')
                            ->url()
                            ->maxLength(255)
                            ->helperText('Если указана — в письме и в Telegram появится кнопка «Открыть на сайте».'),

                        Placeholder::make('recipients')
                            ->label('Потенциальных получателей')
                            ->content(fn (): string => $this->recipientsSummary()),
                    ]),
            ]);
    }

    /**
     * Сколько людей увидит рассылку. Если включён запрет писать на
     * неподтверждённые адреса, отдельно показываем, скольким дойдёт письмо —
     * иначе общая цифра вводит в заблуждение.
     */
    private function recipientsSummary(): string
    {
        $total = User::query()->count();

        if (! app(SettingsService::class)->notifyVerifiedEmailsOnly()) {
            return (string) $total;
        }

        $withEmail = User::query()
            ->where('email', 'not like', '%@noemail.local')
            ->whereNotNull('email_verified_at')
            ->count();

        return "{$total}, из них письмо получат {$withEmail} (у остальных не подтверждён e-mail — им уведомление придёт в личный кабинет и Telegram)";
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Отправить рассылку')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Отправить рассылку?')
                ->modalDescription('Уведомление уйдёт всем подписчикам категории. Отменить отправку после подтверждения нельзя.')
                ->action('send'),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        // Select с options(Enum::class) отдаёт в состоянии сам enum, а не строку.
        $category = $data['category'] instanceof NotificationCategory
            ? $data['category']
            : NotificationCategory::from((string) $data['category']);

        $notification = new AdminBroadcastNotification(
            categoryValue: $category->value,
            subject: (string) $data['subject'],
            message: (string) $data['message'],
            link: filled($data['link'] ?? null) ? (string) $data['link'] : null,
        );

        $chunks = 0;

        // Только режем получателей на порции: отправку делают очередные job'ы.
        User::query()
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $users) use ($notification, &$chunks): void {
                SendUserNotificationChunk::dispatch($users->modelKeys(), $notification);
                $chunks++;
            });

        Notification::make()
            ->title('Рассылка поставлена в очередь')
            ->body("Порций к отправке: {$chunks}. Доставка идёт в фоне.")
            ->success()
            ->send();
    }
}
