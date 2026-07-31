<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

/**
 * Контент раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * Одна страница со вкладками вместо четырёх пунктов меню: ключи лежат в одной
 * группе настроек и сохраняются одним save(), а группа «Сайт» и без того
 * перегружена.
 *
 * Флот на странице мероприятий здесь не настраивается — он берётся из яхт с
 * признаком «сдаётся в аренду», чтобы не расходиться с каталогом /yachts.
 */
class ServicesPageSettings extends Page
{
    use RestrictsAccessByRole;

    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $navigationLabel = 'Услуги';

    protected static ?string $title = 'Настройки раздела «Услуги»';

    protected static ?int $navigationSort = 31;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /** Группа настроек: один forgetGroup() сбрасывает кэш всего раздела. */
    private const GROUP = 'services';

    public array $data = [];

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->form->fill([
            // Хаб раздела
            'hub_intro' => $settings->get('services.hub.intro', ''),
            'hub_hero_image' => $this->fileState($settings->get('services.hub.hero_image')),
            'hub_seo_description' => $settings->get('services.hub.seo_description', ''),

            // Аренда флота
            'fleet_intro' => $settings->get('services.fleet_rental.intro', ''),
            'fleet_hero_image' => $this->fileState($settings->get('services.fleet_rental.hero_image')),
            'fleet_advantages' => (array) $settings->get('services.fleet_rental.advantages', []),
            'fleet_min_yachts' => (int) $settings->get('services.fleet_rental.min_yachts', 2),
            'fleet_note' => $settings->get('services.fleet_rental.note', ''),

            // Проведение мероприятий
            'events_intro' => $settings->get('services.event.intro', ''),
            'events_hero_image' => $this->fileState($settings->get('services.event.hero_image')),
            'events_formats' => (array) $settings->get('services.event.formats', []),
            'events_show_fleet' => (bool) $settings->get('services.event.show_fleet', true),
            'events_fleet_note' => $settings->get('services.event.fleet_note', ''),
            'events_venues' => $this->venuesState((array) $settings->get('services.event.venues', [])),
            'events_gallery' => $this->galleryState($settings->get('services.event.gallery', [])),
            'events_cases' => (array) $settings->get('services.event.cases', []),

            // Обучение судовождению
            'training_intro' => $settings->get('services.training.intro', ''),
            'training_hero_image' => $this->fileState($settings->get('services.training.hero_image')),
            'training_programs' => (array) $settings->get('services.training.programs', []),
            'training_gallery' => $this->galleryState($settings->get('services.training.gallery', [])),

            // Яхтенные путешествия и походы
            'tours_intro' => $settings->get('services.tour.intro', ''),
            'tours_hero_image' => $this->fileState($settings->get('services.tour.hero_image')),
            'tours_included' => (array) $settings->get('services.tour.included', []),
            'tours_note' => $settings->get('services.tour.note', ''),
            'tours_gallery' => $this->galleryState($settings->get('services.tour.gallery', [])),
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
                Tabs::make('services')
                    ->columnSpanFull()
                    ->tabs([
                        Tabs\Tab::make('Общее')
                            ->schema($this->hubFields()),

                        Tabs\Tab::make('Аренда флота')
                            ->schema($this->fleetFields()),

                        Tabs\Tab::make('Мероприятия')
                            ->schema($this->eventFields()),

                        Tabs\Tab::make('Обучение')
                            ->schema($this->trainingFields()),

                        Tabs\Tab::make('Путешествия')
                            ->schema($this->tourFields()),
                    ]),
            ]);
    }

    // ──────────────────────────────────────────────
    // Вкладки
    // ──────────────────────────────────────────────

    /** @return list<Component> */
    private function hubFields(): array
    {
        return [
            Section::make('Страница «Услуги»')
                ->description('Вводный текст хаба раздела. Карточки подразделов формируются автоматически.')
                ->schema([
                    $this->heroImage('hub_hero_image', 'services'),

                    RichEditor::make('hub_intro')
                        ->label('Вводный текст')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('services')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsMaxSize(5120)
                        ->columnSpanFull(),

                    Textarea::make('hub_seo_description')
                        ->label('Описание для поисковых систем')
                        ->helperText('Показывается в результатах поиска и при отправке ссылки в мессенджер.')
                        ->rows(2)
                        ->maxLength(300)
                        ->columnSpanFull(),
                ]),
        ];
    }

    /** @return list<Component> */
    private function fleetFields(): array
    {
        return [
            Section::make('Описание подраздела')
                ->schema([
                    $this->heroImage('fleet_hero_image', 'services/fleet-rental'),

                    RichEditor::make('fleet_intro')
                        ->label('Вводный текст')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('services/fleet-rental')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsMaxSize(5120)
                        ->columnSpanFull(),
                ]),

            Section::make('Форма подбора')
                ->description('Подбор яхт на диапазон дат считается по календарю аренды и одобренным бронированиям.')
                ->schema([
                    TextInput::make('fleet_min_yachts')
                        ->label('Количество яхт по умолчанию')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(2)
                        ->rules(['nullable', 'integer', 'min:1', 'max:50']),

                    Textarea::make('fleet_note')
                        ->label('Примечание под результатом подбора')
                        ->placeholder('Например: расчёт ориентировочный, итоговая стоимость подтверждается менеджером.')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Преимущества')
                ->description('Короткие блоки под формой подбора.')
                ->schema([
                    Repeater::make('fleet_advantages')
                        ->label('Блоки')
                        ->addActionLabel('Добавить блок')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Заголовок')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            Textarea::make('text')
                                ->label('Текст')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500']),
                        ]),
                ]),
        ];
    }

    /** @return list<Component> */
    private function eventFields(): array
    {
        return [
            Section::make('Описание подраздела')
                ->schema([
                    $this->heroImage('events_hero_image', 'services/events'),

                    RichEditor::make('events_intro')
                        ->label('Текстовый блок')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('services/events')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsMaxSize(5120)
                        ->columnSpanFull(),
                ]),

            Section::make('Форматы мероприятий')
                ->schema([
                    Repeater::make('events_formats')
                        ->label('Форматы')
                        ->addActionLabel('Добавить формат')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Название')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            Textarea::make('desc')
                                ->label('Описание')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500']),
                        ]),
                ]),

            Section::make('Флот')
                ->description('Список яхт берётся из каталога: показываются яхты с признаком «сдаётся в аренду».')
                ->schema([
                    Toggle::make('events_show_fleet')
                        ->label('Показывать блок флота')
                        ->default(true),

                    Textarea::make('events_fleet_note')
                        ->label('Подводка к блоку флота')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Площадки')
                ->schema([
                    Repeater::make('events_venues')
                        ->label('Площадки')
                        ->addActionLabel('Добавить площадку')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Название')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            TextInput::make('address')
                                ->label('Адрес')
                                ->maxLength(255)
                                ->rules(['nullable', 'string', 'max:255']),
                            TextInput::make('capacity')
                                ->label('Вместимость')
                                ->placeholder('Например: до 80 гостей')
                                ->maxLength(255)
                                ->rules(['nullable', 'string', 'max:255']),
                            Textarea::make('desc')
                                ->label('Описание')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500'])
                                ->columnSpanFull(),
                            FileUpload::make('photo')
                                ->label('Фото площадки')
                                ->image()
                                ->acceptedFileTypes($this->imageTypes())
                                ->imageEditor()
                                ->imageEditorAspectRatios(['16:9', '4:3', null])
                                ->disk('public')
                                ->directory('services/events/venues')
                                ->visibility('public')
                                ->maxSize(10240)
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Галерея мероприятий')
                ->schema([
                    $this->gallery('events_gallery', 'services/events/gallery'),
                ]),

            Section::make('Проведённые мероприятия')
                ->description('Короткие карточки в подтверждение опыта. Необязательный блок.')
                ->schema([
                    Repeater::make('events_cases')
                        ->label('Мероприятия')
                        ->addActionLabel('Добавить мероприятие')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Название')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            TextInput::make('date')
                                ->label('Когда')
                                ->placeholder('Например: июль 2026')
                                ->maxLength(255)
                                ->rules(['nullable', 'string', 'max:255']),
                            Textarea::make('desc')
                                ->label('Описание')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500'])
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    /** @return list<Component> */
    private function trainingFields(): array
    {
        return [
            Section::make('Описание подраздела')
                ->schema([
                    $this->heroImage('training_hero_image', 'services/training'),

                    RichEditor::make('training_intro')
                        ->label('Вводный текст')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('services/training')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsMaxSize(5120)
                        ->columnSpanFull(),
                ]),

            Section::make('Программы обучения')
                ->schema([
                    Repeater::make('training_programs')
                        ->label('Программы')
                        ->addActionLabel('Добавить программу')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Название')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            TextInput::make('duration')
                                ->label('Длительность')
                                ->placeholder('Например: 5 дней')
                                ->maxLength(255)
                                ->rules(['nullable', 'string', 'max:255']),
                            TextInput::make('price')
                                ->label('Стоимость')
                                ->placeholder('Например: от 45 000 ₽')
                                ->maxLength(255)
                                ->rules(['nullable', 'string', 'max:255']),
                            Textarea::make('desc')
                                ->label('Описание')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500'])
                                ->columnSpanFull(),
                        ]),
                ]),

            Section::make('Галерея')
                ->schema([
                    $this->gallery('training_gallery', 'services/training/gallery'),
                ]),
        ];
    }

    /** @return list<Component> */
    private function tourFields(): array
    {
        return [
            Section::make('Описание подраздела')
                ->description('Сами походы добавляются в разделе «Услуги: Походы» — здесь только обрамление страницы.')
                ->schema([
                    $this->heroImage('tours_hero_image', 'services/tours'),

                    RichEditor::make('tours_intro')
                        ->label('Вводный текст')
                        ->fileAttachmentsDisk('public')
                        ->fileAttachmentsDirectory('services/tours')
                        ->fileAttachmentsVisibility('public')
                        ->fileAttachmentsMaxSize(5120)
                        ->columnSpanFull(),
                ]),

            Section::make('Что входит в стоимость')
                ->description('Общие для всех походов условия. Индивидуальные цены задаются у каждого похода.')
                ->schema([
                    Repeater::make('tours_included')
                        ->label('Блоки')
                        ->addActionLabel('Добавить блок')
                        ->reorderable()
                        ->collapsible()
                        ->defaultItems(0)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->schema([
                            TextInput::make('title')
                                ->label('Заголовок')
                                ->placeholder('Например: Проживание на яхте')
                                ->required()
                                ->maxLength(255)
                                ->rules(['required', 'string', 'max:255']),
                            Textarea::make('text')
                                ->label('Текст')
                                ->rows(2)
                                ->maxLength(500)
                                ->rules(['nullable', 'string', 'max:500']),
                        ]),

                    Textarea::make('tours_note')
                        ->label('Примечание под блоками')
                        ->placeholder('Например: авиабилеты и трансфер оплачиваются отдельно.')
                        ->rows(2)
                        ->maxLength(500)
                        ->columnSpanFull(),
                ]),

            Section::make('Галерея')
                ->schema([
                    $this->gallery('tours_gallery', 'services/tours/gallery'),
                ]),
        ];
    }

    // ──────────────────────────────────────────────
    // Общие поля
    // ──────────────────────────────────────────────

    private function heroImage(string $key, string $directory): FileUpload
    {
        return FileUpload::make($key)
            ->label('Фон шапки страницы')
            ->helperText('Широкое изображение для верхнего блока. Если не задано — используется фон по умолчанию.')
            ->image()
            ->acceptedFileTypes($this->imageTypes())
            ->imageEditor()
            ->imageEditorViewportWidth(1920)
            ->imageEditorViewportHeight(800)
            ->imageEditorAspectRatios(['21:9', '16:9', null])
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->maxSize(10240)
            ->validationMessages(['max' => 'Файл слишком большой. Максимальный размер — 10 МБ.'])
            ->columnSpanFull();
    }

    private function gallery(string $key, string $directory): FileUpload
    {
        return FileUpload::make($key)
            ->label('Фотографии')
            ->helperText('Порядок файлов определяет порядок показа.')
            ->image()
            ->acceptedFileTypes($this->imageTypes())
            ->multiple()
            ->reorderable()
            ->disk('public')
            ->directory($directory)
            ->visibility('public')
            ->maxFiles(60)
            ->maxSize(10240)
            ->columnSpanFull();
    }

    /** @return list<string> */
    private function imageTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'];
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

    // ──────────────────────────────────────────────
    // Нормализация состояния формы ↔ настроек
    // ──────────────────────────────────────────────

    /** FileUpload хранит состояние массивом, настройка — строкой. */
    private function fileState(mixed $value): array
    {
        return is_string($value) && $value !== '' ? [$value] : [];
    }

    /** @return list<string> */
    private function galleryState(mixed $value): array
    {
        return collect((array) $value)
            ->flatten()
            ->filter(fn ($item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $venues
     * @return array<int, array<string, mixed>>
     */
    private function venuesState(array $venues): array
    {
        return collect($venues)
            ->map(function (array $venue): array {
                $venue['photo'] = $this->fileState($venue['photo'] ?? null);

                return $venue;
            })
            ->values()
            ->all();
    }

    /** Обратное преобразование: массив FileUpload → строка настройки. */
    private function fileValue(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value === [] ? null : reset($value);
        }

        // Незавершённая загрузка не должна попасть в настройки.
        if ($value instanceof TemporaryUploadedFile || ! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /** @return list<string> */
    private function galleryValue(mixed $value): array
    {
        return collect((array) $value)
            ->flatten()
            ->reject(fn ($item): bool => $item instanceof TemporaryUploadedFile)
            ->filter(fn ($item): bool => is_string($item) && $item !== '')
            ->values()
            ->all();
    }

    /**
     * Репитер без пустых записей: строка без заголовка на сайте не показывается.
     *
     * @return array<int, array<string, mixed>>
     */
    private function repeaterValue(mixed $value, array $keys): array
    {
        return collect((array) $value)
            ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['title'] ?? '')) !== '')
            ->map(function (array $item) use ($keys): array {
                $row = [];

                foreach ($keys as $key) {
                    $row[$key] = $key === 'photo'
                        ? $this->fileValue($item['photo'] ?? null)
                        : trim((string) ($item[$key] ?? ''));
                }

                return $row;
            })
            ->values()
            ->all();
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->setMany([
            // Хаб
            'services.hub.intro' => $data['hub_intro'] ?? '',
            'services.hub.hero_image' => $this->fileValue($data['hub_hero_image'] ?? null),
            'services.hub.seo_description' => trim((string) ($data['hub_seo_description'] ?? '')),

            // Аренда флота
            'services.fleet_rental.intro' => $data['fleet_intro'] ?? '',
            'services.fleet_rental.hero_image' => $this->fileValue($data['fleet_hero_image'] ?? null),
            'services.fleet_rental.advantages' => $this->repeaterValue($data['fleet_advantages'] ?? [], ['title', 'text']),
            'services.fleet_rental.min_yachts' => max(1, (int) ($data['fleet_min_yachts'] ?? 2)),
            'services.fleet_rental.note' => trim((string) ($data['fleet_note'] ?? '')),

            // Мероприятия
            'services.event.intro' => $data['events_intro'] ?? '',
            'services.event.hero_image' => $this->fileValue($data['events_hero_image'] ?? null),
            'services.event.formats' => $this->repeaterValue($data['events_formats'] ?? [], ['title', 'desc']),
            'services.event.show_fleet' => (bool) ($data['events_show_fleet'] ?? true),
            'services.event.fleet_note' => trim((string) ($data['events_fleet_note'] ?? '')),
            'services.event.venues' => $this->repeaterValue(
                $data['events_venues'] ?? [],
                ['title', 'address', 'capacity', 'desc', 'photo'],
            ),
            'services.event.gallery' => $this->galleryValue($data['events_gallery'] ?? []),
            'services.event.cases' => $this->repeaterValue($data['events_cases'] ?? [], ['title', 'date', 'desc']),

            // Обучение
            'services.training.intro' => $data['training_intro'] ?? '',
            'services.training.hero_image' => $this->fileValue($data['training_hero_image'] ?? null),
            'services.training.programs' => $this->repeaterValue(
                $data['training_programs'] ?? [],
                ['title', 'duration', 'price', 'desc'],
            ),
            'services.training.gallery' => $this->galleryValue($data['training_gallery'] ?? []),

            // Яхтенные путешествия и походы
            'services.tour.intro' => $data['tours_intro'] ?? '',
            'services.tour.hero_image' => $this->fileValue($data['tours_hero_image'] ?? null),
            'services.tour.included' => $this->repeaterValue($data['tours_included'] ?? [], ['title', 'text']),
            'services.tour.note' => trim((string) ($data['tours_note'] ?? '')),
            'services.tour.gallery' => $this->galleryValue($data['tours_gallery'] ?? []),
        ], self::GROUP);

        $settings->forgetGroup(self::GROUP);

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
