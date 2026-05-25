<?php

use App\Actions\Feedback\SubmitFeedbackAction;
use App\Models\News;
use App\Models\Regatta;
use App\Models\Team;
use App\Models\Yacht;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $latestNews = News::published()
        ->orderBy('published_at', 'desc')
        ->limit(3)
        ->get();

    return view('pages.home', compact('latestNews'));
})->name('home');
Route::view('/association/charter', 'pages/association-info/charter')->name('charter');
Route::view('/association/management', 'pages/association-info/management')->name('management');
Route::view('/association/trustees', 'pages/association-info/trustees')->name('trustees');
Route::view('/association/policy', 'pages/association-info/policy')->name('policy');
Route::view('/association/rules', 'pages/association-info/rules')->name('rules');
Route::view('/association/regulations', 'pages/association-info/regulations')->name('regulations');
Route::view('/association/decisions', 'pages/association-info/decisions')->name('decisions');
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
        ->orderBy('date_end', 'desc')
        ->get()
        ->each(fn ($r) => $r->setRelation('resultItems', $r->results->flatMap->items));

        return view('pages.regattas', compact('regattas'));
})->name('competitions');
Route::get('/regattas/{regatta}', function (Regatta $regatta) {
    $regatta->loadMissing([
        'approvedEntries.team.organizer',
        'approvedEntries.team.activeMembers',
        'approvedEntries.yacht',
        'results.items.team.organizer',
        'results.items.team.activeMembers',
        'races',
        'documents',
        'season',
    ]);

    $regatta->setRelation('resultItems', $regatta->results->flatMap->items);

    $entries = $regatta->approvedEntries;

    // Проверяем, является ли текущий юзер участником уже заявленной команды
    $userIsEntered = false;
    if (auth()->check()) {
        $enteredTeamIds = $entries->pluck('team_id');
        $userIsEntered = \App\Models\TeamMember::query()
            ->where('user_id', auth()->id())
            ->whereIn('team_id', $enteredTeamIds)
            ->exists();
    }

    // Данные для Alpine.js модального окна состава команд
    $entriesJson = $entries->map(fn ($entry) => [
        'team_name' => $entry->team?->name ?? '',
        'crew' => $entry->team?->activeMembers?->map(fn ($member) => [
            'name' => $member->full_name ?? '',
            'birthday' => $member->birth_date?->format('Y-m-d') ?? '',
            'rank' => $member->sport_category ?? '',
        ])->values()->toArray() ?? [],
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
                            ? asset($r->background_image)
                            : asset('images/news/news_1.png'),
        'url' => route('competition-details', $r),
        'statusLabel' => $r->isUpcoming() ? 'Планируемые' : ($r->isFinished() ? 'Завершены' : 'Идут'),
    ])->values()->toArray();

    return view('pages.regatta-show', compact(
        'regatta',
        'entries',
        'entriesJson',
        'scheduleDays',
        'documents',
        'otherRegattas',
        'otherRegattasData',
        'userIsEntered'
    ));
})->name('competition-details');
Route::get('/teams', function () {
    $teams = Team::with([
        'organizer',
        'activeMembers',
        'regattaEntries.regatta',
        'regattaEntries.yacht',
        'regattaResultItems.regattaResult',
        'ratings.season',
    ])
        ->orderBy('name')
        ->paginate(12);

    $teamsJson = $teams->map(fn (Team $team) => [
        'id' => $team->id,
        'name' => $team->name,
        'description' => $team->description ?? '',
        'photo' => $team->picture ? Storage::url($team->picture) : asset('images/news/news_1.png'),
        'status' => $team->is_archived ? 'Неактивная' : 'Активная',
        'status_class' => $team->is_archived ? 'inactive' : 'active',
        'captain' => $team->organizer?->full_name ?? '—',
        'rating' => $team->ratings->where('rating_type', 'team')->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—',
        'participation_count' => $team->regattaEntries->count(),
        'members' => $team->activeMembers->map(fn ($m) => [
            'name' => $m->full_name,
            'birthday' => $m->birth_date?->format('Y-m-d') ?? '',
            'category' => $m->sport_category ?? '',
        ])->values()->toArray(),
        'years' => $team->regattaEntries
            ->pluck('regatta.date_start')
            ->filter()
            ->map->year
            ->unique()
            ->sortDesc()
            ->values()
            ->toArray(),
        'participation' => $team->regattaEntries->map(fn ($entry) => [
            'regatta' => $entry->regatta?->name ?? '—',
            'yacht' => $entry->yacht?->name ?? '—',
            'date_event' => $entry->regatta?->dateRange() ?? '—',
            'date_registration' => $entry->submitted_at?->format('d.m.Y') ?? '—',
            'year' => $entry->regatta?->date_start?->year,
            'status' => $entry->status,
            'place' => $team->regattaResultItems
                ->firstWhere('regattaResult.regatta_id', $entry->regatta_id)
                ?->final_position ?? null,
        ])->values()->toArray(),
        'gallery' => [],
    ])->values();

    return view('pages.teams', compact('teams', 'teamsJson'));
})->name('teams');
Route::get('/yachts', function () {
    $yachts = Yacht::with(['documents', 'regattaEntries.regatta', 'regattaEntries.team'])
        ->where('approval_status', 'approved')
        ->orderBy('name')
        ->paginate(12);

    $yachtsJson = $yachts->map(fn (Yacht $yacht) => [
        'id' => $yacht->id,
        'name' => $yacht->name,
        'vfps_number' => $yacht->vfps_number,
        'owner_name' => $yacht->owner_name ?? '—',
        'owner_phone' => $yacht->owner_phone ?? '—',
        'owner_email' => $yacht->owner_email ?? '—',
        'owner_photo' => $yacht->owner_photo
                                    ? asset('storage/'.$yacht->owner_photo)
                                    : asset('images/yachts/owner.png'),
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
        'has_orc_cert' => !empty($yacht->orc_cert_url),
        'registered_at' => $yacht->created_at?->format('Y') ?? '—',
        'documents' => $yacht->documents->map(fn ($doc) => [
            'title' => $doc->title,
            'desc' => $doc->updated_at
                                ? 'Актуальная редакция от '.$doc->updated_at->format('d F Y')
                                : '',
            'url' => $doc->url ? \Illuminate\Support\Facades\Storage::disk('public')->url($doc->url) : '#',
        ])->values()->toArray(),
        'participation' => $yacht->regattaEntries->map(fn ($entry) => [
            'regatta' => $entry->regatta?->name ?? '—',
            'date_event' => $entry->regatta?->dateRange() ?? '—',
            'team' => $entry->team?->name ?? '—',
            'date_registration' => $entry->submitted_at?->format('d.m.Y') ?? '—',
            'status' => $entry->status,
        ])->values()->toArray(),
        'participation_count' => $yacht->regattaEntries->count(),
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
    $teamRatings = \App\Models\Rating::with(['team.activeMembers', 'season'])
        ->team()
        ->ranked()
        ->get()
        ->map(fn ($r) => [
            'name'         => $r->team?->name ?? '—',
            'total_points' => (float) $r->total_points,
            'rank'         => $r->rank_position,
            'members'      => $r->team?->activeMembers?->map(fn ($m) => [
                'name'     => $m->full_name,
                'birthday' => $m->birth_date?->format('d.m.Y') ?? '—',
                'category' => $m->sport_category ?? '—',
            ])->values()->toArray() ?? [],
        ])->values()->toArray();

    $personalRatings = \App\Models\Rating::with(['user', 'season'])
        ->personal()
        ->ranked()
        ->get()
        ->map(fn ($r) => [
            'name'         => $r->user?->full_name ?? '—',
            'total_points' => (float) $r->total_points,
            'rank'         => $r->rank_position,
            'birthday'     => $r->user?->birth_date?->format('d.m.Y') ?? '—',
            'category'     => $r->user?->sport_category ?? '—',
            'email'        => $r->user?->email ?? '—',
        ])->values()->toArray();

    return view('pages.ratings', compact('teamRatings', 'personalRatings'));
})->name('ratings');
Route::view('/gallery', 'pages/gallery')->name('gallery');
Route::view('/help', 'pages/help')->name('help');
Route::get('/news', function () {
    $news = News::published()
        ->with('author')
        ->latest('published_at')
        ->paginate(12);

    return view('pages.news', compact('news'));
})->name('news');

Route::get('/news/{news}', function (News $news) {
    abort_unless($news->isPublished(), 404);

    $news->load('author');

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
