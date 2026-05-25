<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Team;
use App\Models\User;
use App\Services\SettingsService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

    // ──────────────────────────────────────────────
    // Form state — TOP-3 команд
    // ──────────────────────────────────────────────

    public ?string $top_team_1        = null;
    public ?string $top_team_1_points = null;

    public ?string $top_team_2        = null;
    public ?string $top_team_2_points = null;

    public ?string $top_team_3        = null;
    public ?string $top_team_3_points = null;

    // ──────────────────────────────────────────────
    // Form state — TOP-3 участников
    // ──────────────────────────────────────────────

    public ?string $top_participant_1        = null;
    public ?string $top_participant_1_points = null;

    public ?string $top_participant_2        = null;
    public ?string $top_participant_2_points = null;

    public ?string $top_participant_3        = null;
    public ?string $top_participant_3_points = null;

    // ──────────────────────────────────────────────
    // Lifecycle
    // ──────────────────────────────────────────────

    public function mount(): void
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $teams        = $settings->get('home.top_teams', []);
        $participants = $settings->get('home.top_participants', []);

        // Структура: [['id' => ..., 'points' => ...], ...]
        $this->top_team_1        = $teams[0]['id']     ?? null;
        $this->top_team_1_points = $teams[0]['points'] ?? null;

        $this->top_team_2        = $teams[1]['id']     ?? null;
        $this->top_team_2_points = $teams[1]['points'] ?? null;

        $this->top_team_3        = $teams[2]['id']     ?? null;
        $this->top_team_3_points = $teams[2]['points'] ?? null;

        $this->top_participant_1        = $participants[0]['id']     ?? null;
        $this->top_participant_1_points = $participants[0]['points'] ?? null;

        $this->top_participant_2        = $participants[1]['id']     ?? null;
        $this->top_participant_2_points = $participants[1]['points'] ?? null;

        $this->top_participant_3        = $participants[2]['id']     ?? null;
        $this->top_participant_3_points = $participants[2]['points'] ?? null;
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
            ->statePath('')
            ->components([

                // ── TOP-3 команд ──────────────────────────────
                Section::make('TOP-3 команд')
                    ->description('Выберите три команды и укажите количество очков для отображения в рейтинговом блоке на главной странице.')
                    ->icon(Heroicon::OutlinedTrophy)
                    ->schema([
                        // 1-е место
                        Grid::make(2)->schema([
                            Select::make('top_team_1')
                                ->label('🥇 1-е место — команда')
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
                                ->label('🥈 2-е место — команда')
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
                                ->label('🥉 3-е место — команда')
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
                    ->icon(Heroicon::OutlinedUsers)
                    ->schema([
                        // 1-е место
                        Grid::make(2)->schema([
                            Select::make('top_participant_1')
                                ->label('🥇 1-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name ?: $u->name])
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
                                ->label('🥈 2-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name ?: $u->name])
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
                                ->label('🥉 3-е место — участник')
                                ->placeholder('Выберите участника')
                                ->options(fn () => User::orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (User $u) => [$u->id => $u->full_name ?: $u->name])
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
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'top_team_1'               => ['nullable', 'exists:teams,id'],
            'top_team_1_points'        => ['nullable', 'numeric', 'min:0'],
            'top_team_2'               => ['nullable', 'exists:teams,id'],
            'top_team_2_points'        => ['nullable', 'numeric', 'min:0'],
            'top_team_3'               => ['nullable', 'exists:teams,id'],
            'top_team_3_points'        => ['nullable', 'numeric', 'min:0'],
            'top_participant_1'        => ['nullable', 'exists:users,id'],
            'top_participant_1_points' => ['nullable', 'numeric', 'min:0'],
            'top_participant_2'        => ['nullable', 'exists:users,id'],
            'top_participant_2_points' => ['nullable', 'numeric', 'min:0'],
            'top_participant_3'        => ['nullable', 'exists:users,id'],
            'top_participant_3_points' => ['nullable', 'numeric', 'min:0'],
        ]);

        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $settings->set('home.top_teams', [
            ['id' => $this->top_team_1, 'points' => $this->top_team_1_points],
            ['id' => $this->top_team_2, 'points' => $this->top_team_2_points],
            ['id' => $this->top_team_3, 'points' => $this->top_team_3_points],
        ], 'home');

        $settings->set('home.top_participants', [
            ['id' => $this->top_participant_1, 'points' => $this->top_participant_1_points],
            ['id' => $this->top_participant_2, 'points' => $this->top_participant_2_points],
            ['id' => $this->top_participant_3, 'points' => $this->top_participant_3_points],
        ], 'home');

        $settings->forgetGroup('home');

        Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }
}
