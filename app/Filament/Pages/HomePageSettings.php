<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Team;
use App\Models\User;
use App\Services\ImageConverter;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class HomePageSettings extends Page
{
    protected string $view = 'filament-panels::pages.page';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $navigationLabel = 'Настройки главной';

    protected static ?string $title = 'Настройки главной страницы';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'Сайт';

    /**
     * Единый массив состояния формы — стандартный паттерн Filament для Page с FileUpload.
     * Использование $this->form->fill() / $this->form->getState() гарантирует вызов
     * saveUploadedFiles() через beforeStateDehydrated-хук FileUpload.
     *
     * @var array<string, mixed>
     */
    public array $data = [];

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $teams        = $settings->get('home.top_teams', []);
        $participants = $settings->get('home.top_participants', []);
        $faq          = $settings->get('home.faq', []);

        // Нормализуем gallery_photos в индексированный массив строк
        $rawPhotos = $settings->get('home.gallery_photos', []);
        $galleryPhotos = collect((array) $rawPhotos)
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        // Hero-фон: одиночный файл (изображение или видео)
        $heroMedia = collect((array) $settings->get('home.hero_media', []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->first();

        // Заполняем форму через fill() — это запускает afterStateHydrated на FileUpload
        $this->form->fill([
            // TOP-3 команд
            'top_team_1'               => $teams[0]['id']     ?? null,
            'top_team_1_points'        => $teams[0]['points'] ?? null,
            'top_team_2'               => $teams[1]['id']     ?? null,
            'top_team_2_points'        => $teams[1]['points'] ?? null,
            'top_team_3'               => $teams[2]['id']     ?? null,
            'top_team_3_points'        => $teams[2]['points'] ?? null,
            // TOP-3 участников
            'top_participant_1'        => $participants[0]['id']     ?? null,
            'top_participant_1_points' => $participants[0]['points'] ?? null,
            'top_participant_2'        => $participants[1]['id']     ?? null,
            'top_participant_2_points' => $participants[1]['points'] ?? null,
            'top_participant_3'        => $participants[2]['id']     ?? null,
            'top_participant_3_points' => $participants[2]['points'] ?? null,
            // FAQ
            'faq' => $faq,
            // Галерея
            'gallery_photos' => $galleryPhotos,
            'gallery_count'  => (int) $settings->get('home.gallery_count', 10),
            'gallery_random' => (bool) $settings->get('home.gallery_random', false),
            'gallery_sort'   => $settings->get('home.gallery_sort', 'manual') ?? 'manual',
            // Hero-фон главной страницы
            'hero_media' => $heroMedia,
            // Режим обновления сайта
            'maintenance_mode'    => (bool) $settings->get('home.maintenance_mode', false),
            'maintenance_message' => $settings->get('home.maintenance_message', 'Сайт в процессе обновления'),
        ]);
    }

    // ──────────────────────────────────────────────
    // Content schema (Filament 4)
    // ──────────────────────────────────────────────

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

                // ── Режим обновления сайта ────────────────────
                Section::make('Режим обновления')
                    ->description('Если включено — посетители видят заглушку вместо содержимого сайта. Панель администратора остаётся доступной.')
                    ->schema([
                        Toggle::make('maintenance_mode')
                            ->label('Скрыть содержимое сайта')
                            ->helperText('Включите, чтобы временно закрыть публичный сайт для посетителей.')
                            ->default(false)
                            ->live(),

                        TextInput::make('maintenance_message')
                            ->label('Текст заглушки')
                            ->placeholder('Сайт в процессе обновления')
                            ->default('Сайт в процессе обновления')
                            ->maxLength(255)
                            ->visible(fn ($get) => (bool) $get('maintenance_mode'))
                            ->rules(['nullable', 'string', 'max:255']),
                    ]),

                // ── Hero-фон главной страницы ─────────────────
                Section::make('Фон главной страницы (Hero)')
                    ->description('Загрузите изображение или видео для фона верхнего блока главной страницы. Если ничего не загружено — используется видео по умолчанию.')
                    ->schema([
                        FileUpload::make('hero_media')
                            ->label('Фон (изображение или видео)')
                            ->helperText('Поддерживаются изображения (JPG, PNG, WebP) и видео (MP4, WebM).')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'])
                            ->disk('public')
                            ->directory('home/hero')
                            ->visibility('public')
                            ->maxSize(51200)
                            ->columnSpanFull(),
                    ]),

                // ── TOP-3 команд ──────────────────────────────
                Section::make('TOP-3 команд')
                    ->description('Выберите три команды и укажите количество очков для отображения в рейтинговом блоке на главной странице.')
                    ->schema([
                        // 1-е место
                        Grid::make(2)->schema([
                            Select::make('top_team_1')
                                ->label('1-е место — команда')
                                ->placeholder('Выберите команду')
                                ->options(fn () => Team::orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:teams,id']),

                            TextInput::make('top_team_1_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),

                        // 2-е место
                        Grid::make(2)->schema([
                            Select::make('top_team_2')
                                ->label('2-е место — команда')
                                ->placeholder('Выберите команду')
                                ->options(fn () => Team::orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:teams,id']),

                            TextInput::make('top_team_2_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),

                        // 3-е место
                        Grid::make(2)->schema([
                            Select::make('top_team_3')
                                ->label('3-е место — команда')
                                ->placeholder('Выберите команду')
                                ->options(fn () => Team::orderBy('name')->pluck('name', 'id')->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:teams,id']),

                            TextInput::make('top_team_3_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),
                    ]),

                // ── TOP-3 участников ──────────────────────────
                Section::make('TOP-3 участников')
                    ->description('Выберите трёх участников и укажите количество очков для отображения в рейтинговом блоке на главной странице.')
                    ->schema([
                        // 1-е место
                        Grid::make(2)->schema([
                            Select::make('top_participant_1')
                                ->label('1-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->name ?: $u->name])
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:users,id']),

                            TextInput::make('top_participant_1_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),

                        // 2-е место
                        Grid::make(2)->schema([
                            Select::make('top_participant_2')
                                ->label('2-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->name ?: $u->name])
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:users,id']),

                            TextInput::make('top_participant_2_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),

                        // 3-е место
                        Grid::make(2)->schema([
                            Select::make('top_participant_3')
                                ->label('3-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->name ?: $u->name])
                                    ->toArray())
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->rules(['nullable', 'exists:users,id']),

                            TextInput::make('top_participant_3_points')
                                ->label('Очки')
                                ->placeholder('0')
                                ->numeric()
                                ->minValue(0)
                                ->nullable()
                                ->rules(['nullable', 'numeric', 'min:0']),
                        ]),
                    ]),



                // ── Галерея главной страницы ──────────────────
                Section::make('Галерея главной страницы')
                    ->description('Настройте фотографии, отображаемые в слайдере галереи на главной странице.')
                    ->schema([

                        // Загрузка / выбор фотографий для пула галереи.
                        // reorderable() позволяет задать ручной порядок показа.
                        FileUpload::make('gallery_photos')
                            ->label('Фотографии галереи')
                            ->helperText('Загрузите фотографии для галереи. Порядок файлов определяет порядок показа при ручной сортировке.')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('home/gallery')
                            ->visibility('public')
                            ->maxFiles(100)
                            ->columnSpanFull(),

                        Grid::make(3)->schema([

                            // Максимальное количество отображаемых фото
                            TextInput::make('gallery_count')
                                ->label('Количество фото')
                                ->helperText('Сколько фото показывать в слайдере.')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(50)
                                ->default(10)
                                ->required()
                                ->rules(['required', 'integer', 'min:1', 'max:50']),

                            // Режим случайного выбора
                            Toggle::make('gallery_random')
                                ->label('Случайный порядок')
                                ->helperText('Если включено — фото выбираются случайно из пула при каждом открытии страницы.')
                                ->default(false)
                                ->live(),

                            // Порядок сортировки (скрывается при включённом рандоме)
                            Select::make('gallery_sort')
                                ->label('Порядок сортировки')
                                ->helperText('Применяется только при отключённом случайном порядке.')
                                ->options([
                                    'manual'  => 'Ручной (как загружено)',
                                    'newest'  => 'Сначала новые',
                                    'oldest'  => 'Сначала старые',
                                ])
                                ->default('manual')
                                ->required()
                                ->visible(fn ($get) => ! $get('gallery_random'))
                                ->rules(['required', 'in:manual,newest,oldest']),
                        ]),
                    ]),

                                // ── FAQ ──────────────────────────────────────
                Section::make('FAQ')
                    ->description('Добавьте вопросы и ответы для блока «Часто задаваемые вопросы» на главной странице. Перетаскивайте записи для изменения порядка отображения.')
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

    // ──────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────

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
        // getState() вызывает callBeforeStateDehydrated → saveUploadedFiles()
        // что перемещает файлы из temp-хранилища в постоянное и обновляет пути в $data
        $data = $this->form->getState();

        $this->validate([
            'data.top_team_1'               => ['nullable', 'exists:teams,id'],
            'data.top_team_1_points'        => ['nullable', 'numeric', 'min:0'],
            'data.top_team_2'               => ['nullable', 'exists:teams,id'],
            'data.top_team_2_points'        => ['nullable', 'numeric', 'min:0'],
            'data.top_team_3'               => ['nullable', 'exists:teams,id'],
            'data.top_team_3_points'        => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_1'        => ['nullable', 'exists:users,id'],
            'data.top_participant_1_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_2'        => ['nullable', 'exists:users,id'],
            'data.top_participant_2_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_3'        => ['nullable', 'exists:users,id'],
            'data.top_participant_3_points' => ['nullable', 'numeric', 'min:0'],
            'data.gallery_count'            => ['required', 'integer', 'min:1', 'max:50'],
            'data.gallery_random'           => ['boolean'],
            'data.gallery_sort'             => ['required', 'in:manual,newest,oldest'],
            'data.maintenance_mode'         => ['boolean'],
            'data.maintenance_message'      => ['nullable', 'string', 'max:255'],
        ]);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->set('home.top_teams', [
            ['id' => $data['top_team_1'], 'points' => $data['top_team_1_points']],
            ['id' => $data['top_team_2'], 'points' => $data['top_team_2_points']],
            ['id' => $data['top_team_3'], 'points' => $data['top_team_3_points']],
        ], 'home');

        $settings->set('home.top_participants', [
            ['id' => $data['top_participant_1'], 'points' => $data['top_participant_1_points']],
            ['id' => $data['top_participant_2'], 'points' => $data['top_participant_2_points']],
            ['id' => $data['top_participant_3'], 'points' => $data['top_participant_3_points']],
        ], 'home');

        // FAQ: фильтруем пустые записи и сохраняем
        $faq = collect((array) ($data['faq'] ?? []))
            ->filter(fn (array $item) => ! empty($item['question']) && ! empty($item['answer']))
            ->values()
            ->all();

        $settings->set('home.faq', $faq, 'home');

        // Нормализуем пути фото в индексированный массив строк перед сохранением
        $photos = collect((array) ($data['gallery_photos'] ?? []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        $settings->set('home.gallery_photos', $photos, 'home');
        $settings->set('home.gallery_count',  (int) $data['gallery_count'], 'home');
        $settings->set('home.gallery_random', (bool) $data['gallery_random'], 'home');
        $settings->set('home.gallery_sort',   $data['gallery_sort'] ?? 'manual', 'home');

        // Hero-фон: нормализуем к одиночному пути (строка) либо null
        $heroMedia = collect((array) ($data['hero_media'] ?? []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->first();

        // Изображения автоматически перекодируем в WebP (видео не трогаем)
        $heroMedia = app(ImageConverter::class)->toWebp($heroMedia, 'public');

        $settings->set('home.hero_media', $heroMedia, 'home');

        // Режим обновления сайта
        $settings->set('home.maintenance_mode', (bool) ($data['maintenance_mode'] ?? false), 'home');
        $settings->set(
            'home.maintenance_message',
            trim((string) ($data['maintenance_message'] ?? '')) ?: 'Сайт в процессе обновления',
            'home',
        );

        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
