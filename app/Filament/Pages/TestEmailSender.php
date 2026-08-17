<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\HasUnsavedDataChangesAlert;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use UnitEnum;

/**
 * Скрытая страница для проверки отправки почты (SMTP/DSN и т. п.).
 * Недоступна в навигации, открывается только по прямой ссылке и только
 * администратором — см. canAccess().
 */
class TestEmailSender extends Page
{
    use HasUnsavedDataChangesAlert;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $slug = 'test-email-sender';

    protected static ?string $title = 'Тестовая отправка письма';

    protected static string|UnitEnum|null $navigationGroup = null;

    protected string $view = 'filament-panels::pages.page';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'to' => auth()->user()?->email,
            'subject' => 'Тестовое письмо — '.config('app.name'),
            'body' => 'Это тестовое сообщение, отправленное со страницы проверки почты админ-панели.',
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
                Section::make('Проверка отправки почты')
                    ->description('Отправляет письмо через текущий почтовый драйвер ('.config('mail.default').'), чтобы проверить настройки SMTP.')
                    ->schema([
                        TextInput::make('to')
                            ->label('Получатель')
                            ->email()
                            ->required(),

                        TextInput::make('subject')
                            ->label('Тема письма')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('body')
                            ->label('Текст письма')
                            ->required()
                            ->rows(6),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('send')
                ->label('Отправить тестовое письмо')
                ->color('primary')
                ->submit('send'),
        ];
    }

    public function send(): void
    {
        $data = $this->form->getState();

        $this->validate([
            'data.to' => ['required', 'email'],
            'data.subject' => ['required', 'string', 'max:255'],
            'data.body' => ['required', 'string'],
        ]);

        try {
            Mail::raw($data['body'], function ($message) use ($data): void {
                $message->to($data['to'])->subject($data['subject']);
            });
        } catch (Throwable $e) {
            Notification::make()
                ->title('Ошибка отправки')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Письмо отправлено')
            ->body("Получатель: {$data['to']}. Драйвер: ".config('mail.default'))
            ->success()
            ->send();

        // Состояние формы теперь совпадает с сохранённым — сбрасываем базу сравнения,
        // иначе уход со страницы после сохранения будет считаться потерей изменений.
        $this->rememberData();
    }
}
