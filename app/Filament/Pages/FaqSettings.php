<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FaqSettings extends Page
{
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?string $title = 'Часто задаваемые вопросы';

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->form->fill([
            'faq' => $settings->get('home.faq', []),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
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
                Section::make('FAQ')
                    ->description('Вопросы и ответы для блока «Часто задаваемые вопросы» на главной странице и вкладки «Для пользователей» на странице «Помощь». Перетаскивайте записи для изменения порядка отображения.')
                    ->schema([
                        Repeater::make('faq')
                            ->label('Вопросы и ответы')
                            ->addActionLabel('Добавить вопрос')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                TextInput::make('question')
                                    ->label('Вопрос')
                                    ->placeholder('Введите вопрос')
                                    ->required()
                                    ->maxLength(500)
                                    ->rules(['required', 'string', 'max:500']),

                                RichEditor::make('answer')
                                    ->label('Ответ')
                                    ->placeholder('Введите развёрнутый ответ')
                                    ->required()
                                    ->columnSpanFull()
                                    ->rules(['required']),
                            ]),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Сохранить настройки')
                ->color('primary')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        // Фильтруем пустые записи и сохраняем
        $faq = collect((array) ($data['faq'] ?? []))
            ->filter(fn (array $item) => ! empty($item['question']) && ! empty($item['answer']))
            ->values()
            ->all();

        $settings->set('home.faq', $faq, 'home');
        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
