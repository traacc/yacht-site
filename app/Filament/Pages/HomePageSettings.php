<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\RestrictsAccessByRole;
use App\Models\Team;
use App\Models\User;
use App\Services\ImageConverter;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class HomePageSettings extends Page
{
    use RestrictsAccessByRole;

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

        $teams = $settings->get('home.top_teams', []);
        $participants = $settings->get('home.top_participants', []);
        $sponsors = $settings->get('home.sponsors', []);

        // Нормализуем gallery_photos в индексированный массив строк
        $rawPhotos = $settings->get('home.gallery_photos', []);
        $galleryPhotos = collect((array) $rawPhotos)
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        // Hero-фон: список файлов (одно изображение/видео либо набор изображений для слайд-шоу)
        $heroMedia = collect((array) $settings->get('home.hero_media', []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        // Заполняем форму через fill() — это запускает afterStateHydrated на FileUpload
        $this->form->fill([
            // TOP-3 команд
            'top_team_1' => $teams[0]['id'] ?? null,
            'top_team_1_points' => $teams[0]['points'] ?? null,
            'top_team_2' => $teams[1]['id'] ?? null,
            'top_team_2_points' => $teams[1]['points'] ?? null,
            'top_team_3' => $teams[2]['id'] ?? null,
            'top_team_3_points' => $teams[2]['points'] ?? null,
            // TOP-3 участников
            'top_participant_1' => $participants[0]['id'] ?? null,
            'top_participant_1_points' => $participants[0]['points'] ?? null,
            'top_participant_2' => $participants[1]['id'] ?? null,
            'top_participant_2_points' => $participants[1]['points'] ?? null,
            'top_participant_3' => $participants[2]['id'] ?? null,
            'top_participant_3_points' => $participants[2]['points'] ?? null,
            // Спонсоры / партнёры
            'sponsors' => $sponsors,
            // Галерея
            'gallery_photos' => $galleryPhotos,
            'gallery_count' => (int) $settings->get('home.gallery_count', 10),
            'gallery_random' => (bool) $settings->get('home.gallery_random', false),
            'gallery_sort' => $settings->get('home.gallery_sort', 'manual') ?? 'manual',
            // Hero-фон главной страницы
            'hero_media' => $heroMedia,
            'hero_crop_x' => (float) $settings->get('home.hero_crop_x', 0.0),
            'hero_crop_y' => (float) $settings->get('home.hero_crop_y', 0.0),
            'hero_crop_w' => (float) $settings->get('home.hero_crop_w', 1.0),
            'hero_crop_h' => (float) $settings->get('home.hero_crop_h', 1.0),
            'hero_height' => (int) $settings->get('home.hero_height', 768),
            // Всплывающий баннер
            'banner_enabled' => (bool) $settings->get('home.banner_enabled', false),
            'banner_title' => $settings->get('home.banner_title', ''),
            'banner_text' => $settings->get('home.banner_text', ''),
            'banner_button_text' => $settings->get('home.banner_button_text', ''),
            'banner_button_url' => $settings->get('home.banner_button_url', ''),
        ]);
    }

    /**
     * URL изображения для превью области просмотра (viewport-контрол).
     *
     * Читает ТЕКУЩЕЕ состояние формы, поэтому превью обновляется сразу после
     * загрузки — ещё до сохранения: для только что загруженного файла берётся
     * временный URL Livewire, для уже сохранённого — публичный URL с диска.
     * Видео и пустое состояние → дефолтный фон (зум/позиция всё равно работают).
     */
    public function heroPreviewUrl(): string
    {
        $videoExtensions = ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm4v'];

        foreach ((array) ($this->data['hero_media'] ?? []) as $file) {
            // Свежая загрузка — временный файл Livewire.
            if ($file instanceof TemporaryUploadedFile) {
                if (str_starts_with((string) $file->getMimeType(), 'image/')) {
                    try {
                        return $file->temporaryUrl();
                    } catch (\Throwable) {
                        // temporaryUrl недоступен (напр. не-превьюабельный тип) — пропускаем.
                    }
                }

                continue;
            }

            // Уже сохранённый файл — путь на публичном диске.
            if (is_string($file) && $file !== '') {
                $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
                if (! in_array($ext, $videoExtensions, true)) {
                    return Storage::disk('public')->url($file);
                }
            }
        }

        return asset('/images/bg/bg_hero.webp');
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

                // ── Всплывающий баннер ────────────────────────
                Section::make('Всплывающий баннер')
                    ->description('Всплывающее окно для посетителей главной страницы. Показывается гостям один раз. Если выключено — баннер не отображается.')
                    ->schema([
                        Toggle::make('banner_enabled')
                            ->label('Показывать баннер')
                            ->helperText('Включите, чтобы показывать всплывающий баннер гостям сайта.')
                            ->default(false)
                            ->live(),

                        TextInput::make('banner_title')
                            ->label('Заголовок')
                            ->placeholder('Хотите гоняться с нами?')
                            ->maxLength(255)
                            ->visible(fn ($get) => (bool) $get('banner_enabled'))
                            ->rules(['nullable', 'string', 'max:255']),

                        Textarea::make('banner_text')
                            ->label('Текст')
                            ->placeholder('Краткое описание для посетителя')
                            ->rows(3)
                            ->maxLength(1000)
                            ->visible(fn ($get) => (bool) $get('banner_enabled'))
                            ->rules(['nullable', 'string', 'max:1000']),

                        Grid::make(2)
                            ->visible(fn ($get) => (bool) $get('banner_enabled'))
                            ->schema([
                                TextInput::make('banner_button_text')
                                    ->label('Текст на кнопке')
                                    ->placeholder('Подробнее')
                                    ->maxLength(255)
                                    ->rules(['nullable', 'string', 'max:255']),

                                TextInput::make('banner_button_url')
                                    ->label('Ссылка на кнопке')
                                    ->placeholder('https://example.com')
                                    ->url()
                                    ->maxLength(2048)
                                    ->rules(['nullable', 'url', 'max:2048']),
                            ]),
                    ]),

                // ── Hero-фон главной страницы ─────────────────
                Section::make('Фон главной страницы (Hero)')
                    ->description('Загрузите изображение или видео для фона верхнего блока главной страницы. Если ничего не загружено — используется видео по умолчанию.')
                    ->schema([
                        FileUpload::make('hero_media')
                            ->label('Фон (изображение, видео или слайд-шоу)')
                            ->helperText('Один файл — статичный фон (изображение или видео). Загрузите несколько изображений — они будут показываться как автоматическое слайд-шоу. Порядок задаётся перетаскиванием.')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif', 'video/mp4', 'video/webm'])
                            ->multiple()
                            ->reorderable()
                            ->disk('public')
                            ->directory('home/hero')
                            ->visibility('public')
                            // ->maxSize(51200)
                            ->maxFiles(20)
                            // Реактивно — чтобы превью viewport-контрола обновлялось сразу после загрузки.
                            ->live()
                            /*
                            ->imageEditor()
                            // Область отображения hero-блока при Full HD (1920px) и 100% масштабе:
                            // ширина 1920px, высота 40vw = 768px → соотношение 5:2 (2.5:1).
                            ->imageEditorViewportWidth(1920)
                            ->imageEditorViewportHeight(768)
                            ->imageEditorAspectRatios([
                                '5:2', // = 1920×768, точное соответствие видимой области на сайте
                            ])
                            */
                            ->columnSpanFull(),

                        // Визуальный контрол области просмотра: тащим изображение мышью
                        // (панорамирование) + ползунок/колесо (зум). Значения хранятся
                        // в скрытых полях ниже и применяются на сайте вживую.
                        ViewField::make('hero_viewport_control')
                            ->label('Область просмотра (Full HD)')
                            ->view('filament.forms.hero-viewport')
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Hidden::make('hero_crop_x')->default(0),
                        Hidden::make('hero_crop_y')->default(0),
                        Hidden::make('hero_crop_w')->default(1),
                        Hidden::make('hero_crop_h')->default(1),
                        Hidden::make('hero_height')->default(768),
                    ]),

                // ── Спонсоры / партнёры ──────────────────────
                Section::make('Партнёры ассоциации')
                    ->description('Логотипы партнёров для блока «Партнёры ассоциации» на главной странице. Перетаскивайте записи для изменения порядка отображения.')
                    ->schema([
                        Repeater::make('sponsors')
                            ->label('Логотипы партнёров')
                            ->addActionLabel('Добавить партнёра')
                            ->reorderable()
                            ->collapsible()
                            ->defaultItems(0)
                            ->schema([
                                FileUpload::make('logo')
                                    ->label('Логотип')
                                    ->image()
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif', 'image/svg+xml'])
                                    ->disk('public')
                                    ->directory('home/sponsors')
                                    ->visibility('public')
                                    ->maxSize(5120)
                                    ->imageEditor()
                                    ->imageEditorViewportWidth(1920)
                                    ->imageEditorViewportHeight(1080)
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        null,
                                    ])
                                    ->columnSpanFull(),

                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Название')
                                        ->placeholder('Название партнёра')
                                        ->maxLength(255)
                                        ->rules(['nullable', 'string', 'max:255']),

                                    TextInput::make('url')
                                        ->label('Ссылка')
                                        ->placeholder('https://example.com')
                                        ->url()
                                        ->maxLength(2048)
                                        ->rules(['nullable', 'url', 'max:2048']),
                                ]),

                                Textarea::make('description')
                                    ->label('Описание')
                                    ->helperText('Показывается в модальном окне при клике на логотип партнёра.')
                                    ->placeholder('Чем занимается партнёр и как связан с ассоциацией')
                                    ->rows(4)
                                    ->maxLength(5000)
                                    ->rules(['nullable', 'string', 'max:5000'])
                                    ->columnSpanFull(),
                            ]),
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
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/avif', 'image/heic', 'image/heif'])
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
                                    'manual' => 'Ручной (как загружено)',
                                    'newest' => 'Сначала новые',
                                    'oldest' => 'Сначала старые',
                                ])
                                ->default('manual')
                                ->required()
                                ->visible(fn ($get) => ! $get('gallery_random'))
                                ->rules(['required', 'in:manual,newest,oldest']),
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
            'data.top_team_1' => ['nullable', 'exists:teams,id'],
            'data.top_team_1_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_team_2' => ['nullable', 'exists:teams,id'],
            'data.top_team_2_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_team_3' => ['nullable', 'exists:teams,id'],
            'data.top_team_3_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_1' => ['nullable', 'exists:users,id'],
            'data.top_participant_1_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_2' => ['nullable', 'exists:users,id'],
            'data.top_participant_2_points' => ['nullable', 'numeric', 'min:0'],
            'data.top_participant_3' => ['nullable', 'exists:users,id'],
            'data.top_participant_3_points' => ['nullable', 'numeric', 'min:0'],
            'data.gallery_count' => ['required', 'integer', 'min:1', 'max:50'],
            'data.gallery_random' => ['boolean'],
            'data.gallery_sort' => ['required', 'in:manual,newest,oldest'],
            'data.banner_enabled' => ['boolean'],
            'data.banner_title' => ['nullable', 'string', 'max:255'],
            'data.banner_text' => ['nullable', 'string', 'max:1000'],
            'data.banner_button_text' => ['nullable', 'string', 'max:255'],
            'data.banner_button_url' => ['nullable', 'url', 'max:2048'],
        ]);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);
        $converter = app(ImageConverter::class);

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

        // Спонсоры: оставляем только записи с загруженным логотипом
        $sponsors = collect((array) ($data['sponsors'] ?? []))
            ->map(function (array $item): array {
                $logo = collect((array) ($item['logo'] ?? []))
                    ->flatten()
                    ->filter(fn ($v) => is_string($v) && $v !== '')
                    ->first();

                // HEIC-логотип нормализуем в webp (браузер heic не покажет).
                if (is_string($logo) && $logo !== '') {
                    $logo = app(ImageConverter::class)->normalizeHeicToWebp($logo, 'public');
                }

                return [
                    'logo' => $logo,
                    'name' => trim((string) ($item['name'] ?? '')) ?: null,
                    'url' => trim((string) ($item['url'] ?? '')) ?: null,
                    'description' => trim((string) ($item['description'] ?? '')) ?: null,
                ];
            })
            ->filter(fn (array $item) => ! empty($item['logo']))
            ->values()
            ->all();

        $settings->set('home.sponsors', $sponsors, 'home');

        // Нормализуем пути фото в индексированный массив строк перед сохранением
        // (HEIC → webp; прочие форматы не трогаем).
        $photos = collect((array) ($data['gallery_photos'] ?? []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn (string $path) => $converter->normalizeHeicToWebp($path, 'public'))
            ->values()
            ->all();

        $settings->set('home.gallery_photos', $photos, 'home');
        $settings->set('home.gallery_count', (int) $data['gallery_count'], 'home');
        $settings->set('home.gallery_random', (bool) $data['gallery_random'], 'home');
        $settings->set('home.gallery_sort', $data['gallery_sort'] ?? 'manual', 'home');

        // Hero-фон: нормализуем к списку путей.
        // HEIC сперва декодируем в webp через Imagick, затем общий toWebp (jpg/png → webp; видео не трогаем).
        $heroMedia = collect((array) ($data['hero_media'] ?? []))
            ->flatten()
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->map(fn (string $path) => $converter->toWebp($converter->normalizeHeicToWebp($path, 'public'), 'public'))
            ->values()
            ->all();

        $settings->set('home.hero_media', $heroMedia, 'home');

        // Hero-viewport: crop-прямоугольник (доли изображения) + высота блока.
        // Применяется вживую, сам файл не меняется.
        $settings->set('home.hero_crop_x', max(0, min(1, (float) ($data['hero_crop_x'] ?? 0))), 'home');
        $settings->set('home.hero_crop_y', max(0, min(1, (float) ($data['hero_crop_y'] ?? 0))), 'home');
        $settings->set('home.hero_crop_w', max(0.02, min(1, (float) ($data['hero_crop_w'] ?? 1))), 'home');
        $settings->set('home.hero_crop_h', max(0.02, min(1, (float) ($data['hero_crop_h'] ?? 1))), 'home');
        // Высота блока — не более 768px (при Full HD).
        $settings->set('home.hero_height', max(120, min(768, (int) ($data['hero_height'] ?? 768))), 'home');

        // Всплывающий баннер
        $settings->set('home.banner_enabled', (bool) ($data['banner_enabled'] ?? false), 'home');
        $settings->set('home.banner_title', trim((string) ($data['banner_title'] ?? '')) ?: null, 'home');
        $settings->set('home.banner_text', trim((string) ($data['banner_text'] ?? '')) ?: null, 'home');
        $settings->set('home.banner_button_text', trim((string) ($data['banner_button_text'] ?? '')) ?: null, 'home');
        $settings->set('home.banner_button_url', trim((string) ($data['banner_button_url'] ?? '')) ?: null, 'home');

        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
