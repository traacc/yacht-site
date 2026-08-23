<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Finance\StoreGeneratedFinancialReportAction;
use App\Enums\FinancialDateBasis;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentSettlement;
use App\Exports\FinancialReportExport;
use App\Filament\Concerns\RestrictsToPaymentRoles;
use App\Filament\Resources\FinancialReports\FinancialReportResource;
use App\Services\Finance\FinancialReportBuilder;
use App\Services\Finance\PeriodReport;
use App\Services\Finance\PeriodReportFilters;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Финансовый отчёт за период (ТЗ 3-го этапа, п. 4.5): приходы «от кого и за что»,
 * отдельный учёт раздела «Услуги», итог и выгрузка в Excel.
 *
 * Расходная часть выводится нулём: реестр расходов (п. 4.4) сумм не хранит.
 *
 * Доступ — только администратор и бухгалтер (@see RestrictsToPaymentRoles),
 * поэтому страница исключена из настраиваемой матрицы прав.
 */
class FinancialPeriodReport extends Page
{
    use RestrictsToPaymentRoles;

    /** Сколько строк приходов показывать в предпросмотре (полный список — в Excel). */
    public const PREVIEW_LIMIT = 100;

    protected string $view = 'filament.pages.financial-period-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?string $navigationLabel = 'Отчёт за период';

    protected static ?string $title = 'Финансовый отчёт за период';

    protected static ?int $navigationSort = 22;

    protected static string|UnitEnum|null $navigationGroup = 'Финансы';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Параметры, по которым построен показанный отчёт. */
    public array $appliedFilters = [];

    /** Отчёт текущего запроса — не сериализуется между обращениями Livewire. */
    private ?PeriodReport $report = null;

    public function mount(): void
    {
        $this->form->fill([
            'from' => Carbon::now()->startOfYear()->toDateString(),
            'until' => Carbon::now()->toDateString(),
            'date_basis' => FinancialDateBasis::PaidAt->value,
            'only_confirmed' => true,
            'settlement' => null,
            'purposes' => [],
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('generate')
                ->footer([
                    Actions::make($this->getFormActions())->key('form-actions'),
                ]),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Параметры отчёта')
                    ->description('Отчёт строится по реестру платежей. Расходы пока не учитываются — в реестре расходов нет сумм и групп.')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('from')
                            ->label('Период с')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->maxDate(fn (): string => Carbon::now()->toDateString()),
                        DatePicker::make('until')
                            ->label('Период по')
                            ->required()
                            ->native(false)
                            ->displayFormat('d.m.Y')
                            ->afterOrEqual('from'),
                        Select::make('date_basis')
                            ->label('База даты')
                            ->options(FinancialDateBasis::class)
                            ->default(FinancialDateBasis::PaidAt->value)
                            ->required()
                            ->native(false)
                            ->helperText('По дате оплаты — когда деньги поступили; по дате подтверждения — когда приход акцептован бухгалтером.'),
                        Toggle::make('only_confirmed')
                            ->label('Только подтверждённые приходы')
                            ->default(true)
                            ->helperText('Выключите, чтобы увидеть все платежи периода, кроме отменённых.'),
                        Select::make('settlement')
                            ->label('Форма расчёта')
                            ->options(PaymentSettlement::class)
                            ->placeholder('Наличные и безналичные')
                            ->native(false),
                        Select::make('purposes')
                            ->label('Назначения платежей')
                            ->options(PaymentPurpose::class)
                            ->multiple()
                            ->placeholder('Все назначения')
                            ->native(false),
                    ]),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('generate')
                ->label('Сформировать отчёт')
                ->color('primary')
                ->submit('generate'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportXlsx')
                ->label('Выгрузка в Excel')
                ->icon(Heroicon::ArrowDownTray)
                ->color('white')
                ->visible(fn (): bool => $this->appliedFilters !== [])
                ->action(fn (): ?StreamedResponse => $this->export()),
            Action::make('storeReport')
                ->label('Сохранить в отчёты')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('gray')
                ->visible(fn (): bool => $this->appliedFilters !== [])
                ->schema([
                    TextInput::make('name')
                        ->label('Название отчёта')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => 'Финансовый отчёт за период '
                            .$this->filters()?->periodLabel()),
                ])
                ->modalHeading('Сохранить отчёт в раздел «Финансовые отчёты»')
                ->modalDescription('Книга Excel будет прикреплена к новой записи раздела.')
                ->modalSubmitActionLabel('Сохранить')
                ->action(function (array $data): void {
                    $report = $this->buildReport();

                    if ($report === null) {
                        return;
                    }

                    app(StoreGeneratedFinancialReportAction::class)->handle($report, $data['name']);

                    Notification::make()
                        ->title('Отчёт сохранён')
                        ->body('Файл прикреплён к записи в разделе «Финансовые отчёты».')
                        ->success()
                        ->actions([
                            Action::make('open')
                                ->label('Открыть раздел')
                                ->url(FinancialReportResource::getUrl())
                                ->button(),
                        ])
                        ->send();
                }),
        ];
    }

    /** Применить параметры формы: дальше отчёт строится по $appliedFilters. */
    public function generate(): void
    {
        $this->appliedFilters = $this->form->getState();
        $this->report = null;
    }

    public function export(): ?StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $report = $this->buildReport();

        if ($report === null) {
            return null;
        }

        return app(FinancialReportExport::class)->download($report);
    }

    /** Построенный отчёт для шаблона страницы. */
    public function buildReport(): ?PeriodReport
    {
        $filters = $this->filters();

        if ($filters === null) {
            return null;
        }

        return $this->report ??= app(FinancialReportBuilder::class)->build(
            $filters,
            auth()->user()?->name,
        );
    }

    /** Первые строки приходов — предпросмотр перед выгрузкой. */
    public function previewRows(): Collection
    {
        $filters = $this->filters();

        if ($filters === null) {
            return new Collection;
        }

        return app(FinancialReportBuilder::class)
            ->rowsQuery($filters)
            ->limit(self::PREVIEW_LIMIT)
            ->get();
    }

    private function filters(): ?PeriodReportFilters
    {
        return $this->appliedFilters === []
            ? null
            : PeriodReportFilters::fromArray($this->appliedFilters);
    }
}
