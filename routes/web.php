<?php

use App\Actions\Feedback\SubmitFeedbackAction;
use App\Actions\GenerateCalendarPdfAction;
use App\Models\Gallery;
use App\Models\News;
use App\Models\Regatta;
use App\Models\Team;
use App\Models\Yacht;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $latestNews = News::published()
        ->orderBy('published_at', 'desc')
        ->limit(3)
        ->get();

    // Получаем фото для галереи с учётом настроек (рандом / сортировка / количество)
    $galleryPhotos = app(SettingsService::class)->getGalleryPhotos();

    return view('pages.home', compact('latestNews', 'galleryPhotos'));
})->name('home');
Route::get('/association/charter', function () {
    $documents = app(SettingsService::class)->get('charter.documents', []);

    // Нормализуем документы: генерируем публичные URL из путей storage
    $documents = collect((array) $documents)
        ->filter(fn (array $d) => ! empty($d['title']))
        ->map(function (array $d): array {
            $filePath = $d['file'] ?? null;
            $fileUrl  = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl  = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title'         => $d['title'] ?? '',
                'desc'          => $d['desc'] ?? '',
                'file_url'      => $fileUrl,
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
            $photoUrl  = null;

            if (is_string($photoPath) && $photoPath !== '') {
                $photoUrl = Storage::disk('public')->url($photoPath);
            }

            return [
                'name'            => $m['name'] ?? '',
                'position'        => $m['position'] ?? '',
                'description'     => $m['description'] ?? '',
                'image'           => $photoUrl,
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
            $photoUrl  = null;

            if (is_string($photoPath) && $photoPath !== '') {
                $photoUrl = Storage::disk('public')->url($photoPath);
            }

            return [
                'name'            => $m['name'] ?? '',
                'position'        => $m['position'] ?? '',
                'description'     => $m['description'] ?? '',
                'image'           => $photoUrl,
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
            $fileUrl  = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl  = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title'         => $d['title'] ?? '',
                'desc'          => $d['desc'] ?? '',
                'file_url'      => $fileUrl,
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
            $fileUrl  = null;
            $originalName = null;

            if (is_string($filePath) && $filePath !== '') {
                $fileUrl  = Storage::disk('public')->url($filePath);
                $originalName = basename($filePath);
            }

            return [
                'title'         => $d['title'] ?? '',
                'desc'          => $d['desc'] ?? '',
                'file_url'      => $fileUrl,
                'original_name' => $originalName,
            ];
        })
        ->values()
        ->all();

    return view('pages.association-info.decisions', compact('documents'));
})->name('decisions');

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
    // Учитываем pending + approved, чтобы кнопка «Подать заявку» скрывалась сразу после подачи
    $userIsEntered = false;
    if (auth()->check()) {
        $enteredTeamIds = $regatta->entries()
            ->whereIn('status', ['pending', 'approved'])
            ->pluck('team_id');
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
            'birthday' => $member->birth_date?->format('d.m.Y') ?? '',
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

    // Необходимые документы для участия в регате
    $requiredEntryDocuments = app(\App\Actions\RegattaEntry\UpdateRegattaEntryRequiredDocumentsAction::class)
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
            ? '/storage/' . $r->background_image
            : asset('images/regatas/reg_preview.png'),
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
        'external_id' => $team->getFormattedExternalIdAttribute(),
        'name' => $team->name,
        'description' => $team->description ?? '',
        'photo' => $team->picture ? Storage::url($team->picture) : asset('images/news/news_1.png'),
        'created_at' => $team->created_at?->format('d.m.Y') ?? '—',
        'status' => $team->is_archived ? 'Неактивная' : 'Активная',
        'status_class' => $team->is_archived ? 'inactive' : 'active',
        'captain' => $team->organizer?->full_name ?? '—',
        'rating' => $team->ratings->where('rating_type', 'team')->sortByDesc(fn ($r) => $r->season?->year ?? 0)->first()?->rank_position ?? '—',
        'participation_count' => $team->regattaEntries->count(),
        'members' => $team->activeMembers->map(fn ($m) => [
            'name' => $m->full_name,
            'birthday' => $m->birth_date?->format('d.m.Y') ?? '',
            'category' => $m->sport_category?->getLabel() ?? '',
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
    $yachts = Yacht::with(['user', 'documents', 'regattaEntries.regatta', 'regattaEntries.team'])
        ->where('approval_status', 'approved')
        ->orderBy('name')
        ->paginate(12);

    $yachtsJson = $yachts->map(fn (Yacht $yacht) => [
        'id' => $yacht->id,
        'name' => $yacht->name,
        'vfps_number' => $yacht->vfps_number,
        'owner' => [
            'name' => $yacht->user?->full_name ?? '—',
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
Route::get('/gallery', function () {
    $galleries = Gallery::published()
        ->with('season', 'regatta')
        ->ordered()
        ->get()
        ->groupBy(fn (Gallery $g) => $g->season?->year ?? $g->date?->year ?? now()->year);

    return view('pages.gallery', compact('galleries'));
})->name('gallery');
Route::get('/help', function () {
    $helpCategories = \App\Models\HelpCategory::with(['helps' => fn ($q) =>
        $q->active()->orderBy('title')
    ])
        ->whereHas('helps', fn ($q) => $q->active())
        ->orderBy('title')
        ->get();

    $categories = $helpCategories->mapWithKeys(fn (\App\Models\HelpCategory $cat) => [
        $cat->slug => [
            'title' => $cat->title,
            'items' => $cat->helps->map(fn (\App\Models\Help $help) => [
                'id'          => $help->id,
                'title'       => $help->title,
                'desc'        => $help->desc,
                'includes'    => collect($help->includes ?? [])
                    ->map(fn ($inc) => is_array($inc) ? ($inc['item'] ?? '') : (string) $inc)
                    ->filter()
                    ->values()
                    ->all(),
                'name'        => $help->specialist_name,
                'phone'       => $help->specialist_phone,
                'email'       => $help->specialist_email,
                'sphere'      => $help->specialist_sphere,
                'city'        => $help->specialist_city,
                'contactType' => $help->contact_type,
            ])->values()->toArray(),
        ],
    ])->toArray();

    // Определяем первый slug для активной категории по умолчанию
    $defaultCategory = $helpCategories->first()?->slug ?? '';

    return view('pages.help', compact('categories', 'defaultCategory'));
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
        //'captchaToken' => ['required', new YandexCaptcha()],
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
