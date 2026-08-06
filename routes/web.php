<?php

use App\Actions\Carter30\SubmitRepairRequestAction;
use App\Actions\Chat\StartDirectConversationAction;
use App\Actions\Feedback\SubmitFeedbackAction;
use App\Actions\GenerateCalendarPdfAction;
use App\Actions\Notifications\UnsubscribeAction;
use App\Actions\Payment\ApplyPaymentResultAction;
use App\Actions\Payment\HandleWebhookAction;
use App\Actions\Question\SubmitUserQuestionAction;
use App\Actions\Regatta\DownloadRegattaDocumentsAction;
use App\Actions\Regatta\DownloadRegattaTeamsAction;
use App\Actions\Regatta\GenerateRegattaTeamsPdfAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Actions\RegattaResult\GenerateRegattaResultPdfAction;
use App\Actions\Service\SubmitServiceRequestAction;
use App\Actions\Team\GenerateTeamHistoryPdfAction;
use App\Actions\Voting\CastVoteAction;
use App\Actions\YachtRental\SubmitYachtRentalRequestAction;
use App\Enums\AdvertType;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\PaymentProviderCode;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RegattaStatus;
use App\Enums\RentalRequestStatus;
use App\Enums\ServiceType;
use App\Enums\VotingStatus;
use App\Filament\User\Pages\Messages as UserMessagesPage;
use App\Models\Advert;
use App\Models\Faq;
use App\Models\ForeignRegatta;
use App\Models\Gallery;
use App\Models\GiftCertificate;
use App\Models\News;
use App\Models\PaymentTransaction;
use App\Models\PersonalRating;
use App\Models\PressMention;
use App\Models\Regatta;
use App\Models\RegattaEntryCrew;
use App\Models\RepairCase;
use App\Models\Season;
use App\Models\Series;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamRating;
use App\Models\Tour;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voting;
use App\Models\Yacht;
use App\Services\AdvertBoard;
use App\Services\Chat\ChatAttachments;
use App\Services\FleetAvailability;
use App\Services\HelpDirectory;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\Providers\TestPaymentProvider;
use App\Services\RatingCalculator;
use App\Services\ServiceSubjectResolver;
use App\Services\SettingsService;
use App\Services\WeatherService;
use App\Services\YachtBooking;
use App\Services\YandexMapService;
use App\Support\ResponsiveMedia;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\MediaStream;

Route::get('/', function () {
    $latestNews = News::published()
        ->orderBy('published_at', 'desc')
        ->limit(3)
        ->get();

    // «Пресса о нас»: публикации сторонних изданий (ТЗ 3-го этапа, п. 9)
    $pressMentions = PressMention::published()
        ->with('media')
        ->recentFirst()
        ->limit(3)
        ->get();

    // Получаем фото для галереи с учётом настроек (рандом / сортировка / количество)
    $galleryPhotos = app(SettingsService::class)->getGalleryPhotos();

    // FAQ для главной страницы
    $faq = Faq::active()->ordered()->get();

    // Партнёры ассоциации (логотипы из настроек главной)
    $sponsors = collect((array) app(SettingsService::class)->get('home.sponsors', []))
        ->filter(fn ($s) => is_array($s) && ! empty($s['logo']))
        ->map(fn (array $s) => [
            'logo' => Storage::disk('public')->url($s['logo']),
            'name' => $s['name'] ?? null,
            'url' => $s['url'] ?? null,
        ])
        ->values();

    // Ближайшие дни рождения (как в админ-виджете)
    $birthdays = User::whereNotNull('birth_date')
        ->whereRaw("DATE_FORMAT(birth_date, '%m-%d') BETWEEN
            DATE_FORMAT(NOW(), '%m-%d') AND
            DATE_FORMAT(DATE_ADD(NOW(), INTERVAL 3 DAY), '%m-%d')"
        )
        ->orderByUpcomingBirthday()
        ->get();

    return view('pages.home', compact('latestNews', 'pressMentions', 'galleryPhotos', 'faq', 'birthdays', 'sponsors'));
})->name('home');
Route::get('/association/charter', function () {
    $documents = app(SettingsService::class)->documentLinks('charter.documents');

    return view('pages.association-info.charter', compact('documents'));
})->name('charter');
Route::get('/association/management', function () {
    $members = app(SettingsService::class)->get('management.members', []);

    // Нормализуем фото: генерируем публичные URL из путей storage
    $members = collect((array) $members)
        ->filter(fn (array $m) => ! empty($m['name']) && ! empty($m['position']))
        ->map(function (array $m): array {
            $photoPath = $m['photo'] ?? null;
            $photoUrl = null;

            if (is_string($photoPath) && $photoPath !== '') {
                $photoUrl = Storage::disk('public')->url($photoPath);
            }

            return [
                'name' => $m['name'] ?? '',
                'position' => $m['position'] ?? '',
                'description' => $m['description'] ?? '',
                'image' => $photoUrl,
                'responsibilities' => $m['responsibilities'] ?? [],
            ];
        })
        ->values()
        ->all();

    return view('pages.association-info.management', compact('members'));
})->name('management');
Route::get('/association/trustees', function () {
    $members = app(SettingsService::class)->get('trustees.members', []);

    $members = collect((array) $members)
        ->filter(fn (array $m) => ! empty($m['name']) && ! empty($m['position']))
        ->map(function (array $m): array {
            $photoPath = $m['photo'] ?? null;
            $photoUrl = null;

            if (is_string($photoPath) && $photoPath !== '') {
                $photoUrl = Storage::disk('public')->url($photoPath);
            }

            return [
                'name' => $m['name'] ?? '',
                'position' => $m['position'] ?? '',
                'description' => $m['description'] ?? '',
                'image' => $photoUrl,
                'responsibilities' => $m['responsibilities'] ?? [],
            ];
        })
        ->values()
        ->all();

    return view('pages.association-info.trustees', compact('members'));
})->name('trustees');
Route::view('/association/policy', 'pages/association-info/policy')->name('policy');
Route::view('/association/rules', 'pages/association-info/rules')->name('rules');
Route::get('/association/regulations', function () {
    $settings = app(SettingsService::class);

    return view('pages.association-info.regulations', [
        'documents' => $settings->documentLinks('regulations.documents'),
        'before_note' => $settings->get('regulations.before_note', ''),
        'provisions' => $settings->get('regulations.provisions', ''),
    ]);
})->name('regulations');
Route::get('/association/decisions', function () {
    $documents = app(SettingsService::class)->documentLinks('decisions.documents');

    return view('pages.association-info.decisions', compact('documents'));
})->name('decisions');
Route::get('/association/votings', function () {
    $activeVotings = Voting::where('status', VotingStatus::Active)
        ->with('options')
        ->withCount('votes')
        ->orderByDesc('starts_at')
        ->get();

    $closedVotings = Voting::where('status', VotingStatus::Closed)
        ->with(['options' => fn ($q) => $q->withCount('votes')])
        ->withCount('votes')
        ->orderByDesc('ends_at')
        ->get();

    // Варианты, за которые текущий пользователь уже проголосовал, сгруппированные по голосованию
    $userVotedOptionIds = auth()->check()
        ? Vote::where('user_id', auth()->id())
            ->whereIn('voting_id', $activeVotings->pluck('id'))
            ->get()
            ->groupBy('voting_id')
            ->map(fn ($votes) => $votes->pluck('voting_option_id')->all())
        : collect();

    return view('pages.association-info.votings', compact('activeVotings', 'closedVotings', 'userVotedOptionIds'));
})->name('votings');

Route::post('/association/votings/{voting}/vote', function (Request $request, Voting $voting) {
    // Ошибки складываем в именованный bag, чтобы они показывались у нужной карточки
    $bag = 'voting_'.$voting->id;

    $validated = $request->validateWithBag($bag, [
        'voting_option' => ['required'],
        'voting_option.*' => ['string'],
    ], [
        'voting_option.required' => 'Выберите вариант ответа.',
    ]);

    $optionIds = (array) $validated['voting_option'];

    try {
        app(CastVoteAction::class)->handle($voting, $optionIds, auth()->id());
    } catch (ValidationException $e) {
        return back()->withErrors($e->errors(), $bag);
    }

    return back()->with('vote_cast', $voting->id);
})->middleware('auth')->name('votings.vote');

// ──────────────────────────────────────────────
// Раздел «Carter 30» (ТЗ 3-го этапа, п. 5)
// ──────────────────────────────────────────────
Route::get('/carter30/history', function () {
    return view('pages.carter30.history', [
        'content' => app(SettingsService::class)->get('carter30.history', ''),
    ]);
})->name('carter30.history');

// Технический регламент: один источник контента с /association/regulations,
// две страницы — по ТЗ он должен быть и в разделе класса.
Route::get('/carter30/regulations', function () {
    $settings = app(SettingsService::class);

    return view('pages.carter30.regulations', [
        'documents' => $settings->documentLinks('regulations.documents'),
        'before_note' => $settings->get('regulations.before_note', ''),
        'provisions' => $settings->get('regulations.provisions', ''),
    ]);
})->name('carter30.regulations');

Route::get('/carter30/repair', function () {
    $settings = app(SettingsService::class);

    return view('pages.carter30.repair', [
        'intro' => $settings->get('carter30.repair.intro', ''),
        'documents' => $settings->documentLinks('carter30.repair.documents'),
        'cases' => RepairCase::published()->ordered()->with(['yacht', 'media'])->get(),
    ]);
})->name('carter30.repair');

Route::get('/carter30/repair/{case}', function (RepairCase $case) {
    abort_unless($case->is_published, 404);

    return view('pages.carter30.repair-case', compact('case'));
})->name('carter30.repair-case');

Route::get('/carter30/technical-help', function () {
    $directory = app(HelpDirectory::class);

    return view('pages.carter30.technical-help', [
        'categories' => $directory->categories(),
        'defaultCategory' => $directory->defaultCategory(),
        // Вводный текст общий со страницей «Помощь»: контент один, редактируется
        // в одном месте (HelpPageSettings).
        'beforeNote' => app(SettingsService::class)->get('help.before_note', ''),
    ]);
})->name('carter30.technical-help');

Route::post('/carter30/repair-request', function (Request $request) {
    $validated = $request->validate([
        'repair_case_id' => ['nullable', 'uuid', 'exists:repair_cases,id'],
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:50'],
        'email' => ['nullable', 'email', 'max:255'],
        'comment' => ['nullable', 'string', 'max:2000'],
        'privacy' => ['accepted'],
    ]);

    $case = isset($validated['repair_case_id'])
        ? RepairCase::find($validated['repair_case_id'])
        : null;

    app(SubmitRepairRequestAction::class)->handle(
        data: [
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'comment' => $validated['comment'] ?? null,
        ],
        case: $case,
        user: $request->user(),
    );

    if ($request->expectsJson()) {
        return response()->json(['message' => 'Заявка отправлена']);
    }

    return back()->with('repair_request_sent', true);
})->middleware('throttle:5,1')->name('carter30.repair-request');

// ── Доски объявлений (ТЗ п. 5: «Барахолка», «Продать яхту»; п. 8: биржи) ──────

/** Витрина доски: выборка и фильтры живут в App\Services\AdvertBoard. */
$advertBoard = function (AdvertType $type, Request $request) {
    $board = app(AdvertBoard::class);

    $filters = $request->only(AdvertBoard::FILTER_KEYS);

    return view('pages.adverts.board', [
        'type' => $type,
        'adverts' => $board->paginate($type, $filters),
        'categories' => $board->categories($type),
        'cities' => $board->cities($type),
        'regattas' => $board->regattas($type),
        'kindCounts' => $board->kindCounts($type),
        'filters' => $filters,
    ]);
};

Route::get('/carter30/marketplace', fn (Request $request) => $advertBoard(AdvertType::Marketplace, $request))
    ->name('carter30.marketplace');

Route::get('/carter30/yachts-for-sale', fn (Request $request) => $advertBoard(AdvertType::YachtSale, $request))
    ->name('carter30.yacht-sale');

/** Страница объявления: одна вьюха на все доски, тип задаёт хлебные крошки. */
$advertItem = function (AdvertType $type, Advert $advert) {
    abort_unless($advert->type === $type && $advert->isVisible(), 404);

    $advert->load(['author', 'category', 'yacht', 'media', 'regattas']);

    return view('pages.adverts.item', compact('advert'));
};

Route::get('/carter30/marketplace/{advert}', fn (Advert $advert) => $advertItem(AdvertType::Marketplace, $advert))
    ->name('carter30.marketplace-item');

Route::get('/carter30/yachts-for-sale/{advert}', fn (Advert $advert) => $advertItem(AdvertType::YachtSale, $advert))
    ->name('carter30.yacht-sale-item');

/**
 * Четыре биржи раздела «Соревнования» (ТЗ п. 8.2).
 *
 * Регистрируются циклом: у них, в отличие от досок Carter 30, путь и имя роута
 * не расходятся. Порядок — из AdvertType::competitionBoards(), тот же, что в меню.
 */
foreach (AdvertType::competitionBoards() as $competitionBoard) {
    Route::get(
        '/competitions/'.$competitionBoard->boardPath(),
        fn (Request $request) => $advertBoard($competitionBoard, $request),
    )->name($competitionBoard->routeName());

    Route::get(
        '/competitions/'.$competitionBoard->boardPath().'/{advert}',
        fn (Advert $advert) => $advertItem($competitionBoard, $advert),
    )->name($competitionBoard->itemRouteName());
}

/** «Написать автору» / «Отправить запрос»: заводит личную переписку и ведёт в неё. */
Route::post('/adverts/{advert}/contact', function (Advert $advert, Request $request) {
    try {
        $conversation = app(StartDirectConversationAction::class)->handle($request->user(), $advert);
    } catch (RuntimeException $e) {
        return back()->with('advert_contact_error', $e->getMessage());
    }

    return redirect()->to(UserMessagesPage::getUrl(
        parameters: ['conversation' => $conversation->getKey()],
        panel: 'user',
    ));
})->middleware(['auth', 'throttle:10,1'])->name('adverts.contact');

// ──────────────────────────────────────────────
// Раздел «Услуги» (ТЗ 3-го этапа, п. 7)
// ──────────────────────────────────────────────

/** Общие для всех лендингов данные: контент правится в ServicesPageSettings. */
$servicePage = function (ServiceType $type, array $extra = []) {
    $settings = app(SettingsService::class);
    $prefix = $type->settingsPrefix();

    $heroImage = $settings->get($prefix.'.hero_image');

    return array_merge([
        'type' => $type,
        'intro' => $settings->get($prefix.'.intro', ''),
        'heroImage' => is_string($heroImage) && $heroImage !== ''
            ? Storage::disk('public')->url($heroImage)
            : null,
    ], $extra);
};

Route::get('/services', function () {
    $settings = app(SettingsService::class);
    $heroImage = $settings->get('services.hub.hero_image');

    return view('pages.services.index', [
        'intro' => $settings->get('services.hub.intro', ''),
        'seoDescription' => $settings->get('services.hub.seo_description', ''),
        'heroImage' => is_string($heroImage) && $heroImage !== ''
            ? Storage::disk('public')->url($heroImage)
            : null,
        'services' => ServiceType::published(),
    ]);
})->name('services.index');

/**
 * Витрина бронирования яхт (ТЗ 3-го этапа, п. 7).
 *
 * Поиск, фильтры и пагинация серверные: ссылку с выбранными датами можно
 * переслать, страница работает без JS и индексируется. Каталог /yachts
 * остаётся реестром флота — здесь только те яхты, что сдаются в аренду.
 */
Route::get('/services/yacht-rental', function (Request $request) use ($servicePage) {
    $settings = app(SettingsService::class);
    $booking = app(YachtBooking::class);

    $filters = $request->only([
        'date_start', 'date_end', 'q', 'region', 'yacht_class', 'purpose', 'price_from', 'price_to', 'sort',
    ]);

    return view('pages.services.yacht-rental', $servicePage(ServiceType::YachtRental, [
        'search' => $booking->search($filters),
        'filters' => $filters,
        'regions' => $booking->regions(),
        'classes' => $booking->classes(),
        'purposes' => $booking->purposes(),
        'steps' => (array) $settings->get('services.yacht_rental.steps', []),
        'note' => $settings->get('services.yacht_rental.note', ''),
    ]));
})->name('services.yacht-rental');

/** Карточка бронирования: календарь занятости, расчёт периода и заявка. */
Route::get('/services/yacht-rental/{yacht}', function (Yacht $yacht, Request $request) {
    abort_unless($yacht->for_rent && $yacht->isApproved(), 404);

    $yacht->load(['media', 'rentals', 'optionValues.option', 'user']);

    $booking = app(YachtBooking::class);

    // Даты приходят из ссылки с витрины: пока пользователь листал каталог,
    // яхту могли забронировать — поэтому доступность проверяется заново.
    [$from, $to] = $booking->parseRange($request->query('date_start'), $request->query('date_end'));

    return view('pages.services.yacht-rental-item', [
        'type' => ServiceType::YachtRental,
        'yacht' => $yacht,
        'calendar' => $booking->calendar($yacht),
        'quote' => $booking->quote($yacht, $from, $to),
        'from' => $from,
        'to' => $to,
        'available' => $from !== null && $to !== null ? $booking->isAvailable($yacht, $from, $to) : null,
        'terms' => app(SettingsService::class)->get('services.yacht_rental.terms', ''),
        'others' => $booking->search([])['yachts']->getCollection()
            ->reject(fn (Yacht $other): bool => $other->is($yacht))
            ->take(3),
    ]);
})->name('services.yacht-rental-item');

Route::get('/services/fleet-rental', function (Request $request) use ($servicePage) {
    $settings = app(SettingsService::class);

    // Подбор считается на сервере: страница работает без JS и индексируется.
    $summary = app(FleetAvailability::class)->summary(
        $request->query('date_start'),
        $request->query('date_end'),
        $request->filled('count')
            ? (int) $request->query('count')
            : (int) $settings->get('services.fleet_rental.min_yachts', FleetAvailability::DEFAULT_NEEDED),
    );

    return view('pages.services.fleet-rental', $servicePage(ServiceType::FleetRental, [
        'summary' => $summary,
        'advantages' => (array) $settings->get('services.fleet_rental.advantages', []),
        'note' => $settings->get('services.fleet_rental.note', ''),
    ]));
})->name('services.fleet-rental');

Route::get('/services/events', function () use ($servicePage) {
    $settings = app(SettingsService::class);

    $showFleet = (bool) $settings->get('services.event.show_fleet', true);

    return view('pages.services.events', $servicePage(ServiceType::Event, [
        'formats' => (array) $settings->get('services.event.formats', []),
        'venues' => (array) $settings->get('services.event.venues', []),
        'gallery' => (array) $settings->get('services.event.gallery', []),
        'cases' => (array) $settings->get('services.event.cases', []),
        'fleetNote' => $settings->get('services.event.fleet_note', ''),
        // Флот берём из каталога, чтобы не расходиться со страницей /yachts.
        'fleet' => $showFleet ? app(FleetAvailability::class)->fleet() : collect(),
    ]));
})->name('services.events');

Route::get('/services/training', function () use ($servicePage) {
    $settings = app(SettingsService::class);

    return view('pages.services.training', $servicePage(ServiceType::Training, [
        'programs' => (array) $settings->get('services.training.programs', []),
        'gallery' => (array) $settings->get('services.training.gallery', []),
    ]));
})->name('services.training');

Route::get('/services/tours', function () use ($servicePage) {
    $settings = app(SettingsService::class);

    return view('pages.services.tours', $servicePage(ServiceType::Tour, [
        'upcoming' => Tour::published()->upcoming()->ordered()->with(['yacht', 'media'])->get(),
        // Прошедшие походы остаются на витрине: они и есть подтверждение опыта.
        'past' => Tour::published()->past()->recentFirst()->with('media')->get(),
        'included' => (array) $settings->get('services.tour.included', []),
        'note' => $settings->get('services.tour.note', ''),
        'gallery' => (array) $settings->get('services.tour.gallery', []),
    ]));
})->name('services.tours');

Route::get('/services/tours/{tour}', function (Tour $tour) {
    abort_unless($tour->is_published, 404);

    $tour->load(['yacht', 'media']);

    return view('pages.services.tour-item', [
        'tour' => $tour,
        'type' => ServiceType::Tour,
        'others' => Tour::published()->upcoming()->ordered()
            ->whereKeyNot($tour->getKey())
            ->with('media')
            ->take(3)
            ->get(),
    ]);
})->name('services.tour-item');

Route::get('/services/foreign-regattas', function () use ($servicePage) {
    $settings = app(SettingsService::class);

    return view('pages.services.foreign-regattas', $servicePage(ServiceType::ForeignRegatta, [
        'upcoming' => ForeignRegatta::published()->upcoming()->ordered()
            ->with(['charterYachts', 'media'])
            ->get(),
        // Прошедшие регаты остаются на витрине: они и есть подтверждение опыта.
        'past' => ForeignRegatta::published()->past()->recentFirst()->with('media')->get(),
        'included' => (array) $settings->get('services.foreign_regatta.included', []),
        'note' => $settings->get('services.foreign_regatta.note', ''),
        'gallery' => (array) $settings->get('services.foreign_regatta.gallery', []),
    ]));
})->name('services.foreign-regattas');

Route::get('/services/foreign-regattas/{regatta}', function (ForeignRegatta $regatta) {
    abort_unless($regatta->is_published, 404);

    $regatta->load(['charterYachts', 'media']);

    return view('pages.services.foreign-regatta-item', [
        'regatta' => $regatta,
        'type' => ServiceType::ForeignRegatta,
        'others' => ForeignRegatta::published()->upcoming()->ordered()
            ->whereKeyNot($regatta->getKey())
            ->with(['charterYachts', 'media'])
            ->take(3)
            ->get(),
    ]);
})->name('services.foreign-regatta-item');

Route::get('/services/gift-certificates', function () use ($servicePage) {
    $settings = app(SettingsService::class);

    return view('pages.services.gift-certificates', $servicePage(ServiceType::GiftCertificate, [
        // Отдельных страниц у сертификатов нет: каталог целиком на витрине.
        'certificates' => GiftCertificate::published()->ordered()->with('media')->get(),
        'steps' => (array) $settings->get('services.gift_certificate.steps', []),
        'note' => $settings->get('services.gift_certificate.note', ''),
        'gallery' => (array) $settings->get('services.gift_certificate.gallery', []),
    ]));
})->name('services.gift-certificates');

/** Заявка на услугу: одна форма на все подразделы, поля задаёт ServiceType. */
Route::post('/services/{type}/request', function (ServiceType $type, Request $request) {
    abort_unless($type->acceptsRequests(), 404);

    $rules = [
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:50'],
        'email' => [$type->requiresEmail() ? 'required' : 'nullable', 'email', 'max:255'],
        'comment' => ['nullable', 'string', 'max:2000'],
        'privacy' => ['accepted'],
    ];

    if ($type->usesDateRange()) {
        $required = $type->requiresDateRange() ? 'required' : 'nullable';
        $rules['date_start'] = [$required, 'date'];
        $rules['date_end'] = [$required, 'date', 'after_or_equal:date_start'];
    }

    if ($type->usesQuantity()) {
        $rules['quantity'] = ['nullable', 'integer', 'min:1', 'max:500'];
    }

    if ($type->subjectModel() !== null) {
        // Без `exists`: существование и допустимость объекта проверяет
        // резолвер, иначе получаем два источника правды и разные коды ответа.
        $rules['subject_id'] = ['nullable', 'uuid'];
    }

    // Объект резолвим до валидации: от него зависят варианты части полей
    // (у зарубежной регаты — её варианты участия и свободные яхты). Негодный
    // id резолвер отсекает сам, 404/422 приходит раньше правил формы.
    $subjectId = $request->input('subject_id');
    $subject = app(ServiceSubjectResolver::class)
        ->resolve($type, is_string($subjectId) ? $subjectId : null);

    // Подписи полей берём из подраздела: иначе пользователь видит в ошибке
    // «payload.charter yacht» вместо «Яхта».
    $attributes = ['privacy' => 'согласие с политикой конфиденциальности'];

    foreach ($type->formFields($subject) as $key => $field) {
        $attributes['payload.'.$key] = mb_strtolower($field['label']);
    }

    $validated = $request->validate($rules + $type->payloadRules($subject), attributes: $attributes);

    app(SubmitServiceRequestAction::class)->handle(
        type: $type,
        data: $validated,
        user: $request->user(),
        subject: $subject,
    );

    if ($request->expectsJson()) {
        return response()->json(['message' => 'Заявка отправлена']);
    }

    return back()->with('service_request_sent', true);
})->middleware('throttle:5,1')->name('services.request');

Route::get('/regattas/calendar/pdf', function (Request $request) {
    $year = $request->integer('year', (int) now()->format('Y'));

    return app(GenerateCalendarPdfAction::class)->execute($year);
})->name('regattas.calendar.pdf');

Route::get('/competitions', function () {
    $regattas = Regatta::with([
        'results.items' => fn ($q) => $q->orderBy('final_position'),
        'results.items.team.organizer',
        'results.items.team.activeMembers',
        'results.items.yacht',
        'season',
    ])
        ->where(function ($query) {
            $query->where('date_end', '<', now())
                ->orWhere(function ($q) {
                    $q->where('date_start', '<=', now())
                        ->where('date_end', '>=', now());
                });
        })
        ->orderBy('date_end', 'asc')
        ->get()
        ->each(fn ($r) => $r->setRelation('resultItems', $r->results->flatMap->items));

    // Серии регат с входящими в них регатами (отсортированы по дате старта).
    $series = Series::query()
        ->whereHas('regattas')
        ->with([
            'season',
            'regattas' => fn ($q) => $q->orderBy('date_start')->orderBy('time_start'),
        ])
        ->get()
        ->sortByDesc(fn ($s) => $s->season?->year)
        ->values();

    return view('pages.regattas', compact('regattas', 'series'));
})->name('competitions');
Route::get('/regattas/entries', function () {
    // Все заявки по каждой регате (любой статус), сгруппированные по регате.
    $regattas = Regatta::query()
        ->whereHas('entries')
        ->where('date_end', '>=', now()->format('Y-m-d'))
        ->with([
            'entries' => fn ($q) => $q->orderBy('submitted_at'),
            'entries.team.organizer',
            'entries.yacht',
            'entries.crew.teamMember.user',
            'season',
        ])
        ->orderBy('date_start', 'asc')
        ->get();

    return view('pages.regatta-entries', compact('regattas'));
})->name('regatta-entries');
Route::get('/regattas/{regatta}', function (Regatta $regatta) {
    $regatta->loadMissing([
        'approvedEntries.team.organizer',
        'approvedEntries.team.activeMembers',
        'approvedEntries.crew.teamMember.user',
        'approvedEntries.yacht',
        'results.items.team.organizer',
        'results.items.team.activeMembers',
        'races',
        'documents',
        'season',
    ]);

    $regatta->setRelation('resultItems', $regatta->results->flatMap->items);

    $entries = $regatta->approvedEntries;

    $currentWeather = $regatta?->coordinates
        ? app(WeatherService::class)->getWeather(
            lat: (float) $regatta->coordinates[0],
            lon: (float) $regatta->coordinates[1],
        )
        : null;

    $mapUrl = $regatta?->coordinates
        ? app(YandexMapService::class)->makeUrl(
            lat: (float) $regatta->coordinates[0],
            lon: (float) $regatta->coordinates[1],
        )
        : null;

    $temp = '—';
    if ($currentWeather && isset($currentWeather['current'])) {
        $temp = $currentWeather['current']['temperature_2m'] ?? '—';
    }

    // Проверяем, является ли текущий юзер участником уже заявленной команды
    // Учитываем pending + approved, чтобы кнопка «Подать заявку» скрывалась сразу после подачи
    $userIsEntered = false;
    if (auth()->check()) {
        $enteredTeamIds = $regatta->entries()
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('team_id');
        $userIsEntered = TeamMember::query()
            ->where('user_id', auth()->id())
            ->whereIn('team_id', $enteredTeamIds)
            ->exists();
    }

    // Проверяем, состоит ли текущий юзер в экипаже (RegattaEntryCrew) для этой регаты
    $userIsInCrew = false;
    if (auth()->check()) {
        $userIsInCrew = RegattaEntryCrew::whereHas('teamMember', fn ($q) => $q->where('user_id', auth()->id()))
            ->whereHas('regattaEntry', fn ($q) => $q->where('regatta_id', $regatta->id)
                ->whereNotIn('status', ['withdrawn', 'rejected']))
            ->exists();
    }

    // Данные для Alpine.js модального окна состава команд
    $entriesJson = $entries->map(fn ($entry) => [
        'team_name' => $entry->team?->name ?? '',
        'crew' => $entry->crew
            ->sortBy(fn ($crewMember) => ($crewMember->role === 'captain' ? '0' : '1')
                .mb_strtolower((string) ($crewMember->teamMember?->user?->name ?? '')))
            ->map(fn ($crewMember) => [
                'name' => $crewMember->teamMember?->user?->short_name ?? '',
                'birthday' => $crewMember->teamMember?->user?->birth_date?->format('d.m.Y') ?? '',
                'rank' => $crewMember->teamMember?->user?->sport_category?->getLabel() ?? '',
                'is_captain' => $crewMember->role === 'captain',
            ])->values()->toArray(),
    ])->values()->toArray();

    // Расписание: мероприятия регаты + гонки, сгруппированные по дням
    $scheduleDays = $regatta->scheduleEvents
        // ->concat($regatta->races)
        ->sortBy('event_datetime') // 1. Гарантируем хронологический порядок событий
        ->groupBy(function ($event) {
            // 2. Используем isoFormat для поддержки русского языка (Carbon)
            return $event->event_datetime ? $event->event_datetime->isoFormat('D MMMM') : 'Дата не указана';
        })
        ->map(fn ($events, $dateLabel) => [
            'date' => $dateLabel,
            'events' => $events->map(fn ($e) => [
                'time' => $e->event_datetime ? $e->event_datetime->isoFormat('HH:mm') : '',
                'title' => $e->name,
                'description' => $e->description ?? '',
            ])->values()->toArray(),
        ])
        ->values();

    // Документы
    $documents = $regatta->documents;

    // Есть ли опубликованная галерея этой регаты — чтобы показать кнопку «Смотреть фото»
    $hasRegattaGallery = Gallery::published()
        ->where('regatta_id', $regatta->id)
        ->exists();

    // Необходимые документы для участия в регате
    $requiredEntryDocuments = app(UpdateRegattaEntryRequiredDocumentsAction::class)
        ->getRequiredList();

    // Другие регаты того же сезона (для слайдера)
    $otherRegattas = Regatta::query()
        ->where('season_id', $regatta->season_id)
        ->where('id', '!=', $regatta->id)
        ->orderBy('date_start')
        ->limit(10)
        ->get();

    $otherRegattasData = $otherRegattas->map(fn ($r) => [
        'title' => $r->name,
        'date' => $r->dateRange(),
        'city' => $r->location,
        'location' => $r->water_area,
        'img' => $r->background_image
            ? '/storage/'.$r->background_image
            : asset('images/news/news_1.webp'),
        'url' => route('competition-details', $r),
        'statusLabel' => $r->regatta_status->getLabel(),
    ])->values()->toArray();

    return view('pages.regatta-show', compact(
        'regatta',
        'entries',
        'entriesJson',
        'scheduleDays',
        'documents',
        'requiredEntryDocuments',
        'otherRegattas',
        'otherRegattasData',
        'userIsEntered',
        'userIsInCrew',
        'temp',
        'mapUrl',
        'hasRegattaGallery'
    ));
})->name('competition-details');
Route::get('/regatta/{regatta}/download-documents', function (Regatta $regatta) {
    return app(DownloadRegattaDocumentsAction::class)->execute($regatta);
})->name('regatta.documents.download');
Route::get('/regatta/{regatta}/download-teams', function (Regatta $regatta) {
    return app(DownloadRegattaTeamsAction::class)->execute($regatta);
})->name('regatta.teams.download');
Route::get('/regatta/{regatta}/download-teams-pdf', function (Regatta $regatta) {
    return app(GenerateRegattaTeamsPdfAction::class)->execute($regatta);
})->name('regatta.teams.pdf');
Route::get('/regatta/{regatta}/download-results-pdf', function (Regatta $regatta) {
    $regattaResult = $regatta->results()->first();
    abort_unless($regattaResult, 404, 'Нет результатов для скачивания');

    return app(GenerateRegattaResultPdfAction::class)->execute($regattaResult);
})->name('regatta.results.pdf');
Route::get('/team/{team}/download-history', function (Team $team) {
    return app(GenerateTeamHistoryPdfAction::class)->execute($team);
})->name('team.history.pdf');
Route::view('/teams', 'pages.teams')->name('teams');
Route::get('/yachts', function () {
    $yachts = Yacht::with([
        'user', 'documents', 'regattaEntries.regatta', 'regattaEntries.team', 'rentals', 'optionValues.option',
        'rentalRequests' => fn ($query) => $query->where('status', RentalRequestStatus::Approved)->whereNotNull('desired_date'),
    ])
        ->where('approval_status', 'approved')
        ->orderBy('name')
        ->paginate(250);

    $yachtsJson = $yachts->map(fn (Yacht $yacht) => [
        'id' => $yacht->id,
        'name' => $yacht->name,
        'vfps_number' => $yacht->vfps_number,
        'owner' => [
            'id' => $yacht->user?->id,
            'name' => $yacht->user?->short_name ?? '—',
            'phone' => $yacht->user?->phone ?? '—',
            'email' => $yacht->user?->email ?? '—',
            'photo_url' => $yacht->user?->photo_url
                ? asset('storage/'.$yacht->user->photo_url)
                : asset('images/yachts/owner.png'),
            'external_id' => $yacht->user?->formatted_external_id ?? '—',
            'registered_at' => $yacht->user?->created_at?->format('d.m.Y') ?? '—',
        ],
        'class' => $yacht->class ?? 'Carter 30',
        'project' => $yacht->project ?? '—',
        'year' => $yacht->year ?? '—',
        'reg_place' => $yacht->reg_place ?? '—',
        'home_region' => $yacht->home_region ?? '—',
        'mooring_place' => $yacht->mooring_place ?? '—',
        'gims_number' => $yacht->gims_number ?? '—',
        'sail_type_label' => match ($yacht->sail_type) {
            'dacron' => 'Дакрон',
            'laminate' => 'Ламинат',
            'mixed' => 'Смешанный',
            default => '—',
        },
        'current_mass_kg' => $yacht->current_mass_kg ?? '—',
        'has_orc_cert' => ! empty($yacht->orc_cert_url),
        'registered_at' => $yacht->created_at?->format('Y') ?? '—',
        'documents' => $yacht->documents->map(fn ($doc) => [
            'title' => $doc->title,
            'desc' => $doc->updated_at
                                ? 'Актуальная редакция от '.$doc->updated_at->format('d F Y')
                                : '',
            'url' => $doc->url ? Storage::disk('public')->url($doc->url) : '#',
        ])->values()->toArray(),
        'participation' => $yacht->regattaEntries->map(fn ($entry) => [
            'regatta' => $entry->regatta?->name ?? '—',
            'date_event' => $entry->regatta?->dateRange() ?? '—',
            'team' => $entry->team?->name ?? '—',
            'date_registration' => $entry->submitted_at?->format('d.m.Y') ?? '—',
            'status' => $entry->status,
            'regatta_finished' => $entry->regatta?->regatta_status === RegattaStatus::Finished,
        ])->values()->toArray(),
        'participation_count' => $yacht->regattaEntries->count(),
        'for_rent' => (bool) $yacht->for_rent,
        'rentals' => $yacht->for_rent
            ? $yacht->rentals->map(fn ($rental) => [
                'date_range' => $rental->date_start && $rental->date_end
                    ? $rental->date_start->format('d.m.Y').' — '.$rental->date_end->format('d.m.Y')
                    : '—',
                'start' => $rental->date_start?->format('Y-m-d'),
                'end' => $rental->date_end?->format('Y-m-d'),
                'has_price' => $rental->price_event !== null || $rental->price_pro !== null,
                'price_event' => $rental->price_event !== null
                    ? number_format((float) $rental->price_event, 0, '.', ' ').' ₽/день'
                    : 'по запросу',
                'price_pro' => $rental->price_pro !== null
                    ? number_format((float) $rental->price_pro, 0, '.', ' ').' ₽/день'
                    : 'по запросу',
                'price_event_raw' => $rental->price_event !== null ? (float) $rental->price_event : null,
                'price_pro_raw' => $rental->price_pro !== null ? (float) $rental->price_pro : null,
            ])->values()->toArray()
            : [],
        // Даты одобренных заявок на аренду — помечаются в календаре как «Занято»
        // (разворачиваем весь период desired_date … desired_date_end в отдельные дни)
        'booked_dates' => $yacht->for_rent
            ? $yacht->rentalRequests->flatMap(function ($request) {
                if (! $request->desired_date) {
                    return [];
                }
                $end = $request->desired_date_end ?? $request->desired_date;
                if ($end->lt($request->desired_date)) {
                    $end = $request->desired_date;
                }

                return collect(CarbonPeriod::create($request->desired_date, $end))
                    ->map(fn ($date) => $date->format('Y-m-d'));
            })->unique()->values()->toArray()
            : [],
        'gallery' => $yacht->getMedia('gallery')->map(function ($media) {
            $urls = ResponsiveMedia::urls($media);

            return [
                'url' => $urls['src'],
                'webp' => $urls['webp'] ?? null,
                'avif' => $urls['avif'] ?? null,
                'thumbnail' => $media->getUrl('thumb'),
                'name' => $media->name,
            ];
        })->values()->toArray(),
        'interior_gallery' => $yacht->getMedia('interior_gallery')->map(function ($media) {
            $urls = ResponsiveMedia::urls($media);

            return [
                'url' => $urls['src'],
                'webp' => $urls['webp'] ?? null,
                'avif' => $urls['avif'] ?? null,
                'thumbnail' => $media->getUrl('thumb'),
                'name' => $media->name,
            ];
        })->values()->toArray(),
        'params' => [
            ['label' => 'Класс',       'value' => $yacht->class ?? 'Carter 30'],
            ['label' => 'Парус №',     'value' => $yacht->vfps_number],
            ['label' => 'Год выпуска', 'value' => $yacht->year ?? '—'],
            ['label' => 'Место регистрации',      'value' => $yacht->reg_place ?? '—'],
            ['label' => 'Регион базирования',      'value' => $yacht->home_region ?? '—'],
            ['label' => 'Место стоянки',      'value' => $yacht->mooring_place ?? '—'],
            ['label' => 'Масса',   'value' => $yacht->current_mass_kg ?? '—'],
        ],
        'options' => $yacht->optionValues
            ->sortBy([
                fn ($value) => $value->option->sort_order,
                fn ($value) => $value->option->label,
            ])
            ->map(fn ($value) => [
                'label' => $value->option->label,
                'value' => $value->label,
            ])->values()->toArray(),
        'suitable_for' => $yacht->suitable_for ?? [],
    ])->values()->toJson();

    return view('pages.yachts', compact('yachts', 'yachtsJson'));
})->name('yachts');
Route::get('/ratings', function () {
    $calculator = app(RatingCalculator::class);

    // Рейтинг показываем за один сезон — последний, по которому есть данные.
    // Без фильтра строки разных сезонов склеиваются и участники дублируются.
    $ratingSeason = Season::query()
        ->where('year', '<=', now()->year)
        ->where(fn ($q) => $q->whereHas('teamRatings')->orWhereHas('personalRatings'))
        ->orderByDesc('year')
        ->first();

    $teamBreakdownCache = [];
    $teamBreakdownFor = function ($season, $teamId) use (&$teamBreakdownCache, $calculator) {
        if (! $season || ! $teamId) {
            return [];
        }

        $teamBreakdownCache[$season->id] ??= $calculator->teamRegattaBreakdown($season);

        return $teamBreakdownCache[$season->id][$teamId] ?? [];
    };

    $teamRatings = TeamRating::with(['team.activeMembers', 'team.organizer', 'team.defaultYacht', 'season'])
        ->where('season_id', $ratingSeason?->id)
        ->ranked()
        ->get()
        ->map(function ($r) use ($teamBreakdownFor) {
            $yacht = $r->team?->defaultYacht;

            return [
                'name' => $r->team?->name ?? '—',
                'total_points' => (float) $r->total_points,
                'rank' => $r->rank_position,
                'captain' => $r->team?->organizer?->name ?? '—',
                'captain_id' => $r->team?->organizer?->id,
                'captain_avatar' => $r->team?->organizer?->photo_url ? asset('storage/'.$r->team->organizer->photo_url) : null,
                'yacht' => $yacht
                    ? trim($yacht->name.($yacht->vfps_number ? ' ('.$yacht->vfps_number.')' : ''))
                    : '—',
                'regattas' => $teamBreakdownFor($r->season, $r->team_id),
                'members' => $r->team?->activeMembers
                    ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'name' => $m->name,
                        'birthday' => $m->birth_date?->format('d.m.Y') ?? '—',
                        'category' => $m->sport_category?->getLabel() ?? '—',
                        'avatar' => $m->photo_url ? asset('storage/'.$m->photo_url) : null,
                    ])->values()->toArray() ?? [],
            ];
        })->values()->toArray();

    $breakdownCache = [];
    $breakdownFor = function ($season, $userId) use (&$breakdownCache, $calculator) {
        if (! $season) {
            return [];
        }

        $breakdownCache[$season->id] ??= $calculator->personalRegattaBreakdown($season);

        return $breakdownCache[$season->id][$userId] ?? [];
    };

    $personalRatings = PersonalRating::with(['user', 'season'])
        ->where('season_id', $ratingSeason?->id)
        ->ranked()
        ->get()
        ->map(fn ($r) => [
            'id' => $r->user?->id,
            'name' => $r->user?->name ?? '—',
            'rank' => $r->rank_position,
            'total_points' => (float) $r->total_points,
            'birthday' => $r->user?->birth_date?->format('d.m.Y') ?? '—',
            'category' => $r->user?->sport_category?->getLabel() ?? '—',
            'avatar' => $r->user?->photo_url ? asset('storage/'.$r->user->photo_url) : null,
            'regattas' => $breakdownFor($r->season, $r->user_id),
        ])
        // Место берём из rank_position (то же, что в карточке участника): равные
        // очки получают одно место, следующее место пропускается.
        ->groupBy('rank')
        ->map(function ($group) {
            return [
                'place' => $group->first()['rank'],
                'total_points' => $group->first()['total_points'],
                'participants' => $group->values()->toArray(),
            ];
        })
        ->values()
        ->toArray();

    $ratingSeasonYear = $ratingSeason?->year;

    return view('pages.ratings', compact('teamRatings', 'personalRatings', 'ratingSeasonYear'));
})->name('ratings');
Route::get('/series/results', function () {
    $calculator = app(RatingCalculator::class);

    // Состав команд для всплывающего окна (капитан, яхта, участники).
    $teamDetails = Team::with(['activeMembers', 'organizer', 'defaultYacht'])
        ->get()
        ->mapWithKeys(fn ($team) => [$team->id => [
            'name' => $team->name,
            'captain' => $team->organizer?->name ?? '—',
            'captain_avatar' => $team->organizer?->photo_url ? asset('storage/'.$team->organizer->photo_url) : null,
            'yacht' => $team->defaultYacht
                ? trim($team->defaultYacht->name.($team->defaultYacht->vfps_number ? ' ('.$team->defaultYacht->vfps_number.')' : ''))
                : '—',
            'members' => $team->activeMembers
                ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->map(fn ($m) => [
                    'name' => $m->name,
                    'birthday' => $m->birth_date?->format('d.m.Y') ?? '—',
                    'category' => $m->sport_category?->getLabel() ?? '—',
                    'avatar' => $m->photo_url ? asset('storage/'.$m->photo_url) : null,
                ])->values()->toArray() ?? [],
        ]]);

    // Серии, в регатах которых есть опубликованные результаты.
    $series = Series::query()
        ->whereHas('regattas.results.items')
        ->with('season')
        ->get()
        ->map(function ($s) use ($calculator, $teamDetails) {
            $standings = $calculator->seriesTeamStandings($s);

            // Дополняем строки таблицы данными команды для модального окна.
            $standings['standings'] = array_map(function ($row) use ($teamDetails) {
                $row['team'] = $teamDetails[$row['team_id']] ?? [
                    'name' => $row['name'],
                    'captain' => '—',
                    'captain_avatar' => null,
                    'yacht' => '—',
                    'members' => [],
                ];
                $row['team']['total_points'] = $row['total'];

                return $row;
            }, $standings['standings']);

            return [
                'name' => $s->name,
                'description' => $s->description,
                'season' => $s->season?->year,
                'url' => route('series-details', $s),
                'standings' => $standings,
            ];
        })
        ->filter(fn ($s) => ! empty($s['standings']['standings']))
        ->sortByDesc('season')
        ->values();

    return view('pages.series-results', compact('series'));
})->name('series-results');
Route::get('/series/{series}', function (Series $series) {
    $series->loadMissing([
        'season',
        'regattas' => fn ($q) => $q->orderBy('date_start')->orderBy('time_start'),
    ]);

    return view('pages.series-show', compact('series'));
})->name('series-details');
Route::get('/gallery', function () {
    $galleries = Gallery::published()
        ->with('season', 'regatta')
        ->ordered()
        ->get()
        ->groupBy(fn (Gallery $g) => $g->season?->year ?? $g->date?->year ?? now()->year);

    return view('pages.gallery', compact('galleries'));
})->name('gallery');

// Скачать все фотографии галереи одним ZIP-архивом.
Route::get('/gallery/{gallery}/download', function (Gallery $gallery) {
    abort_unless($gallery->is_published, 404);

    $media = $gallery->getMedia('images');
    abort_if($media->isEmpty(), 404);

    $fileName = Str::slug($gallery->name ?: 'gallery').'.zip';

    return MediaStream::create($fileName)->addMedia($media);
})->name('gallery.download');
Route::get('/help', function () {
    $directory = app(HelpDirectory::class);
    $categories = $directory->categories();

    // Определяем первый slug для активной категории по умолчанию
    $defaultCategory = $directory->defaultCategory();

    $settings = app(SettingsService::class);
    $beforeNote = $settings->get('help.before_note', '');
    // Вкладка «Помощь по сайту» — единый документ из настроек (HelpPageSettings).
    $siteGuide = $settings->get('help.site_guide', '');

    // FAQ для вкладки «Для пользователей» (те же вопросы, что и на главной)
    $faq = Faq::active()->ordered()->get();

    return view('pages.help', compact('categories', 'defaultCategory', 'beforeNote', 'siteGuide', 'faq'));
})->name('help');
Route::get('/news', function () {
    $news = News::published()
        ->with('author')
        ->latest('published_at')
        ->paginate(12);

    return view('pages.news', compact('news'));
})->name('news');

Route::get('/news/{news}', function (News $news) {
    abort_unless($news->isPublished(), 404);

    $news->load(['author', 'media']);

    $otherNews = News::published()
        ->where('id', '!=', $news->id)
        ->latest('published_at')
        ->limit(3)
        ->get();

    return view('pages.news-details', compact('news', 'otherNews'));
})->name('news-details');

// «Пресса о нас» — публикации сторонних изданий (ТЗ 3-го этапа, п. 9).
Route::get('/press', function () {
    $mentions = PressMention::published()
        ->with('media')
        ->recentFirst()
        ->paginate(12);

    return view('pages.press', compact('mentions'));
})->name('press');

Route::get('/press/{pressMention}', function (PressMention $pressMention) {
    // Страница есть только у публикаций с перепечаткой текста: без него
    // показывать нечего, карточка в списке ведёт сразу на сайт издания.
    abort_unless($pressMention->is_published && $pressMention->hasContent(), 404);

    $pressMention->load('media');

    $otherMentions = PressMention::published()
        ->with('media')
        ->where('id', '!=', $pressMention->id)
        ->recentFirst()
        ->limit(3)
        ->get();

    return view('pages.press-details', compact('pressMention', 'otherMentions'));
})->name('press-details');

Route::post('/feedback', function (Request $request) {
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'message' => ['nullable', 'string', 'max:2000'],
        // 'captchaToken' => ['required', new YandexCaptcha()],
    ]);

    app(SubmitFeedbackAction::class)->handle(
        $validated,
        auth()->id()
    );

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Спасибо! Ваша заявка успешно отправлена.']);
    }

    return back()->with('feedback_sent', true);
})->name('feedback.submit');

// Запрос на аренду яхты: модалка в каталоге /yachts и форма бронирования
// на витрине /services/yacht-rental.
Route::post('/yachts/{yacht}/rental-request', function (Request $request, Yacht $yacht) {
    abort_unless($yacht->for_rent, 404);

    $validated = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'phone' => ['required', 'string', 'max:20'],
        'email' => ['nullable', 'email', 'max:255'],
        'desired_date' => ['nullable', 'date'],
        'desired_date_end' => ['nullable', 'date', 'after_or_equal:desired_date'],
        'comment' => ['nullable', 'string', 'max:2000'],
        // Условия аренды по ТЗ принимаются галочкой; время согласия
        // сохраняется в заявке.
        'agreement' => ['accepted'],
    ], attributes: ['agreement' => 'согласие с условиями аренды']);

    // Даты могли быть заняты, пока заполняли форму: подтверждать бронь на уже
    // занятый период нечестно по отношению и к клиенту, и к владельцу.
    if (! empty($validated['desired_date'])) {
        [$from, $to] = app(YachtBooking::class)->parseRange(
            $validated['desired_date'],
            $validated['desired_date_end'] ?? null,
        );

        if ($from !== null && $to !== null && ! app(YachtBooking::class)->isAvailable($yacht, $from, $to)) {
            throw ValidationException::withMessages([
                'desired_date' => 'Эти даты уже заняты. Выберите другой период.',
            ]);
        }
    }

    app(SubmitYachtRentalRequestAction::class)->handle(
        $yacht,
        $validated,
        auth()->id()
    );

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Спасибо! Ваш запрос на аренду отправлен.']);
    }

    return back()->with('rental_request_sent', true);
})->name('yacht-rental.request');

// Вложение из переписки чата поддержки.
// Файлы лежат на приватном диске, поэтому отдаются только участникам диалога
// и операторам поддержки. URL стабильный (не подписанный): лента обновляется
// опросом раз в 5 секунд, и подписанная ссылка заставляла бы браузер
// перекачивать все картинки заново.
Route::get('/chat/attachments/{media:uuid}', function (Media $media, Request $request, ChatAttachments $attachments) {
    $conversion = $attachments->resolveConversion($request->query('p'));

    abort_unless($attachments->allows($media, $request->user(), $conversion), 403);

    // Конверсия могла ещё не сгенерироваться очередью — отдаём оригинал.
    if ($conversion !== null && ! $media->hasGeneratedConversion($conversion)) {
        $conversion = null;
    }

    $path = $conversion === null
        ? $media->getPathRelativeToRoot()
        : $media->getPathRelativeToRoot($conversion);

    $disk = $conversion === null ? $media->disk : ($media->conversions_disk ?? $media->disk);

    abort_unless(Storage::disk($disk)->exists($path), 404);

    // Тип берём из записи медиатеки (он определён сниффингом при загрузке), а не
    // из имени файла. Не-картинки отдаём вложением: показывать в браузере
    // присланный пользователем файл небезопасно.
    $mime = $conversion === null ? (string) $media->mime_type : 'image/jpeg';
    $isImage = str_starts_with($mime, 'image/');

    $headers = [
        'Content-Type' => $mime,
        'Cache-Control' => 'private, max-age=600',
        'X-Content-Type-Options' => 'nosniff',
    ];

    return $isImage
        ? Storage::disk($disk)->response($path, $media->file_name, $headers)
        : Storage::disk($disk)->download($path, $media->file_name, $headers);
})->middleware('auth')->name('chat.attachment');

// Вопрос администрации от зарегистрированного пользователя
// (модальное окно на главной и в разделе «Помощь»)
Route::post('/questions', function (Request $request) {
    $validated = $request->validate([
        'question' => ['required', 'string', 'max:2000'],
        'privacy' => ['accepted'],
    ], attributes: [
        'question' => 'вопрос',
        'privacy' => 'согласие с политикой конфиденциальности',
    ]);

    app(SubmitUserQuestionAction::class)->handle(
        $request->user(),
        $validated['question'],
    );

    if ($request->wantsJson()) {
        return response()->json(['message' => 'Спасибо! Ваш вопрос отправлен администрации.']);
    }

    return back()->with('question_sent', true);
})->middleware(['auth', 'throttle:5,1'])->name('questions.store');

// ──────────────────────────────────────────────
// Онлайн-оплата (эквайринг)
// ──────────────────────────────────────────────

// Страница результата, куда провайдер возвращает плательщика.
// Если вебхук ещё не пришёл — синхронно сверяем статус у провайдера.
Route::get('/payments/{transaction}/return', function (PaymentTransaction $transaction) {
    if ($transaction->status === PaymentTransactionStatus::Pending && $transaction->external_id !== null) {
        $provider = app(PaymentManager::class)->provider($transaction->provider);

        if ($provider->isConfigured()) {
            $result = $provider->getPayment($transaction->external_id);

            if ($result !== null) {
                app(ApplyPaymentResultAction::class)->handle($transaction, $result);
                $transaction->refresh();
            }
        }
    }

    return view('pages.payment-return', ['transaction' => $transaction]);
})->name('payments.return');

// Симулятор «страницы оплаты» тестового провайдера. Доступен только
// по временной подписанной ссылке и только при активном тестовом провайдере.
Route::get('/payments/test/{transaction}/pay', function (PaymentTransaction $transaction) {
    abort_unless(app(PaymentManager::class)->activeProvider() instanceof TestPaymentProvider, 404);
    abort_unless($transaction->provider === PaymentProviderCode::Test, 404);

    return view('pages.payment-test-simulator', [
        'transaction' => $transaction,
        'confirmUrl' => URL::temporarySignedRoute(
            'payments.test.confirm',
            now()->addHour(),
            ['transaction' => $transaction->id],
        ),
    ]);
})->middleware('signed')->name('payments.test.pay');

// Колбэк симулятора: формирует payload «вебхука» и прогоняет его через
// тот же путь верификации и обработки, что и настоящий вебхук провайдера.
Route::post('/payments/test/{transaction}/confirm', function (Request $request, PaymentTransaction $transaction) {
    abort_unless(app(PaymentManager::class)->activeProvider() instanceof TestPaymentProvider, 404);
    abort_unless($transaction->provider === PaymentProviderCode::Test, 404);

    $succeeded = $request->input('outcome') === 'success';

    $payload = json_encode([
        'external_id' => $transaction->external_id,
        'status' => $succeeded ? PaymentTransactionStatus::Succeeded->value : PaymentTransactionStatus::Canceled->value,
        'failure_reason' => $succeeded ? null : 'Плательщик отказался от оплаты',
    ], JSON_UNESCAPED_UNICODE);

    $webhook = Request::create(
        route('api.payments.webhook', ['provider' => PaymentProviderCode::Test->value]),
        'POST',
        [],
        [],
        [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TEST_SIGNATURE' => TestPaymentProvider::sign($payload),
        ],
        $payload,
    );

    app(HandleWebhookAction::class)->handle(PaymentProviderCode::Test, $webhook);

    return redirect()->route('payments.return', $transaction);
})->middleware('signed')->name('payments.test.confirm');

// Отписка по ссылке из письма. Аутентификация — подпись ссылки, вход не нужен
// (пользователь мог открыть письмо в другом браузере).
Route::get('unsubscribe/{user}/{category}/{channel?}', function (
    User $user,
    string $category,
    ?string $channel,
    UnsubscribeAction $unsubscribe,
) {
    $notificationCategory = NotificationCategory::tryFrom($category);
    $notificationChannel = $channel !== null ? NotificationChannel::tryFrom($channel) : null;

    abort_if($notificationCategory === null, 404);

    $unsubscribe->handle($user, $notificationCategory, $notificationChannel);

    return view('pages.unsubscribed', [
        'category' => $notificationCategory,
        'channel' => $notificationChannel,
    ]);
})->middleware(['signed', 'throttle:10,1'])->name('notifications.unsubscribe');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
