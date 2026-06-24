<?php

use App\Actions\Feedback\SubmitFeedbackAction;
use App\Actions\GenerateCalendarPdfAction;
use App\Actions\Regatta\DownloadRegattaDocumentsAction;
use App\Actions\Regatta\DownloadRegattaTeamsAction;
use App\Actions\Regatta\GenerateRegattaTeamsPdfAction;
use App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction;
use App\Actions\RegattaResult\GenerateRegattaResultPdfAction;
use App\Actions\Team\GenerateTeamHistoryPdfAction;
use App\Actions\Voting\CastVoteAction;
use App\Enums\VotingStatus;
use App\Models\Gallery;
use App\Models\Help;
use App\Models\HelpCategory;
use App\Models\News;
use App\Models\PersonalRating;
use App\Models\Regatta;
use App\Models\RegattaEntryCrew;
use App\Models\Series;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamRating;
use App\Models\User;
use App\Models\Vote;
use App\Models\Voting;
use App\Models\Yacht;
use App\Services\RatingCalculator;
use App\Services\SettingsService;
use App\Services\WeatherService;
use App\Services\YandexMapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\Support\MediaStream;

Route::get('/', function () {
    $latestNews = News::published()
        ->orderBy('published_at', 'desc')
        ->limit(3)
        ->get();

    // Получаем фото для галереи с учётом настроек (рандом / сортировка / количество)
    $galleryPhotos = app(SettingsService::class)->getGalleryPhotos();

    // FAQ для главной страницы
    $faq = app(SettingsService::class)->get('home.faq', []);

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

    return view('pages.home', compact('latestNews', 'galleryPhotos', 'faq', 'birthdays', 'sponsors'));
})->name('home');
Route::get('/association/charter', function () {
    $documents = app(SettingsService::class)->get('charter.documents', []);

    // Нормализуем документы: генерируем публичные URL из путей storage
    $documents = collect((array) $documents)
        ->filter(fn (array $d) => ! empty($d['title']))
        ->map(function (array $d): array {
            $filePath = $d['file'] ?? null;
            $fileUrl = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title' => $d['title'] ?? '',
                'desc' => $d['desc'] ?? '',
                'file_url' => $fileUrl,
                'original_name' => $originalName,
            ];
        })
        ->values()
        ->all();

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
    $documents = $settings->get('regulations.documents', []);
    $before_note = $settings->get('regulations.before_note', '');

    // Нормализуем документы: генерируем публичные URL из путей storage
    $documents = collect((array) $documents)
        ->filter(fn (array $d) => ! empty($d['title']))
        ->map(function (array $d): array {
            $filePath = $d['file'] ?? null;
            $fileUrl = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title' => $d['title'] ?? '',
                'desc' => $d['desc'] ?? '',
                'file_url' => $fileUrl,
                'original_name' => $originalName,
            ];
        })
        ->values()
        ->all();

    return view('pages.association-info.regulations', compact('documents', 'before_note'));
})->name('regulations');
Route::get('/association/decisions', function () {
    $documents = app(SettingsService::class)->get('decisions.documents', []);

    // Нормализуем документы: генерируем публичные URL из путей storage
    $documents = collect((array) $documents)
        ->filter(fn (array $d) => ! empty($d['title']))
        ->map(function (array $d): array {
            $filePath = $d['file'] ?? null;
            $fileUrl = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title' => $d['title'] ?? '',
                'desc' => $d['desc'] ?? '',
                'file_url' => $fileUrl,
                'original_name' => $originalName,
            ];
        })
        ->values()
        ->all();

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
        ->with([
            'entries' => fn ($q) => $q->orderBy('submitted_at'),
            'entries.team.organizer',
            'entries.yacht',
            'entries.crew.teamMember.user',
            'season',
        ])
        ->orderBy('date_start', 'desc')
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
    if ($currentWeather && isset($currentWeather['hourly'])) {
        $hourly = array_combine(
            $currentWeather['hourly']['time'],
            $currentWeather['hourly']['temperature_2m']
        );

        $date = $regatta?->date_start;
        $datetime = (new DateTime($date))
            ->setTime(12, 0)
            ->format('Y-m-d\TH:i');
        $temp = $hourly[$datetime] ?? '—';
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

    // Расписание: группируем события (races) по дням
    $scheduleDays = $regatta->races
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
        'mapUrl'
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
    $yachts = Yacht::with(['user', 'documents', 'regattaEntries.regatta', 'regattaEntries.team', 'rentals.regatta'])
        ->where('approval_status', 'approved')
        ->orderBy('name')
        ->paginate(250);

    $yachtsJson = $yachts->map(fn (Yacht $yacht) => [
        'id' => $yacht->id,
        'name' => $yacht->name,
        'vfps_number' => $yacht->vfps_number,
        'owner' => [
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
        ])->values()->toArray(),
        'participation_count' => $yacht->regattaEntries->count(),
        'for_rent' => (bool) $yacht->for_rent,
        'rentals' => $yacht->for_rent
            ? $yacht->rentals->map(fn ($rental) => [
                'regatta' => $rental->regatta?->name ?? '—',
                'date_event' => $rental->regatta?->dateRange() ?? '—',
                'price' => $rental->price !== null
                    ? number_format((float) $rental->price, 0, '.', ' ').' ₽'
                    : 'по запросу',
            ])->values()->toArray()
            : [],
        'gallery' => $yacht->getMedia('gallery')->map(fn ($media) => [
            'url' => $media->getUrl(),
            'thumbnail' => $media->getUrl('thumb'),
            'name' => $media->name,
        ])->values()->toArray(),
        'params' => [
            ['label' => 'Класс',       'value' => $yacht->class ?? 'Carter 30'],
            ['label' => 'Парус №',     'value' => $yacht->vfps_number],
            ['label' => 'Год выпуска', 'value' => $yacht->year ?? '—'],
            ['label' => 'Место регистрации',      'value' => $yacht->reg_place ?? '—'],
            ['label' => 'Масса',   'value' => $yacht->current_mass_kg ?? '—'],
        ],
    ])->values()->toJson();

    return view('pages.yachts', compact('yachts', 'yachtsJson'));
})->name('yachts');
Route::get('/ratings', function () {
    $calculator = app(RatingCalculator::class);

    $teamBreakdownCache = [];
    $teamBreakdownFor = function ($season, $teamId) use (&$teamBreakdownCache, $calculator) {
        if (! $season || ! $teamId) {
            return [];
        }

        $teamBreakdownCache[$season->id] ??= $calculator->teamRegattaBreakdown($season);

        return $teamBreakdownCache[$season->id][$teamId] ?? [];
    };

    $teamRatings = TeamRating::with(['team.activeMembers', 'team.organizer', 'team.defaultYacht', 'season'])
        ->ranked()
        ->get()
        ->map(function ($r) use ($teamBreakdownFor) {
            $yacht = $r->team?->defaultYacht;

            return [
                'name' => $r->team?->name ?? '—',
                'total_points' => (float) $r->total_points,
                'rank' => $r->rank_position,
                'captain' => $r->team?->organizer?->name ?? '—',
                'captain_avatar' => $r->team?->organizer?->photo_url ? asset('storage/'.$r->team->organizer->photo_url) : null,
                'yacht' => $yacht
                    ? trim($yacht->name.($yacht->vfps_number ? ' ('.$yacht->vfps_number.')' : ''))
                    : '—',
                'regattas' => $teamBreakdownFor($r->season, $r->team_id),
                'members' => $r->team?->activeMembers
                    ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($m) => [
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

    $place = 1;
    $personalRatings = PersonalRating::with(['user', 'season'])
        ->ranked()
        ->get()
        ->map(fn ($r) => [
            'name' => $r->user?->name ?? '—',
            'total_points' => (float) $r->total_points,
            'birthday' => $r->user?->birth_date?->format('d.m.Y') ?? '—',
            'category' => $r->user?->sport_category?->getLabel() ?? '—',
            'avatar' => $r->user?->photo_url ? asset('storage/'.$r->user->photo_url) : null,
            'regattas' => $breakdownFor($r->season, $r->user_id),
        ])
        ->groupBy('total_points')
        ->map(function ($group) use (&$place) {
            return [
                'place' => $place++,
                'total_points' => $group->first()['total_points'],
                'participants' => $group->values()->toArray(),
            ];
        })
        ->values()
        ->toArray();

    return view('pages.ratings', compact('teamRatings', 'personalRatings'));
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
    $helpCategories = HelpCategory::with(['helps' => fn ($q) => $q->active()->orderBy('title')->with('media'),
    ])
        ->whereHas('helps', fn ($q) => $q->active())
        ->orderBy('title')
        ->get();

    $categories = $helpCategories->mapWithKeys(fn (HelpCategory $cat) => [
        $cat->slug => [
            'title' => $cat->title,
            'description' => $cat->description,
            'items' => $cat->helps->map(fn (Help $help) => [
                'id' => $help->id,
                'title' => $help->title,
                'desc' => $help->desc,
                'includes' => collect($help->includes ?? [])
                    ->map(fn ($inc) => is_array($inc) ? ($inc['item'] ?? '') : (string) $inc)
                    ->filter()
                    ->values()
                    ->all(),
                'name' => $help->specialist_name,
                'phone' => $help->specialist_phone,
                'email' => $help->specialist_email,
                'sphere' => $help->specialist_sphere,
                'city' => $help->specialist_city,
                'site' => $help->specialist_site,
                'contactType' => $help->contact_type,
                'gallery' => $help->getMedia('gallery')->map(fn ($m) => $m->getUrl())->values()->toArray(),
            ])->values()->toArray(),
        ],
    ])->toArray();

    // Определяем первый slug для активной категории по умолчанию
    $defaultCategory = $helpCategories->first()?->slug ?? '';

    $beforeNote = app(SettingsService::class)->get('help.before_note', '');

    return view('pages.help', compact('categories', 'defaultCategory', 'beforeNote'));
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

Route::view('dashboard', 'dashboard')
    ->middleware(['auth'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
