<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RegattaStatus;
use App\Models\Regatta;
use App\Services\RegattaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegattaPostponeTest extends TestCase
{
    use RefreshDatabase;

    private RegattaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RegattaService::class);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    private function makeUpcomingRegatta(array $overrides = []): Regatta
    {
        // RegattaFactory создаёт Season автоматически через Season::factory()
        return Regatta::factory()->create(array_merge([
            'date_start'     => Carbon::tomorrow(),
            'date_end'       => Carbon::tomorrow()->addDays(2),
            'regatta_status' => RegattaStatus::Upcoming,
        ], $overrides));
    }

    /** Снимок со статусом «Перенесена», созданный для данной регаты. */
    private function snapshotFor(Regatta $regatta): ?Regatta
    {
        return Regatta::where('postponed_to_regatta_id', $regatta->id)->first();
    }

    // ──────────────────────────────────────────────
    // postpone()
    // ──────────────────────────────────────────────

    public function test_postpone_moves_original_regatta_to_new_date(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $result = $this->service->postpone($regatta, $newDate);

        // Возвращается то же самое событие
        $this->assertSame($regatta->id, $result->id);
        $this->assertTrue($newDate->isSameDay($result->fresh()->date_start));
    }

    public function test_postpone_preserves_duration_on_original(): void
    {
        $regatta = $this->makeUpcomingRegatta([
            'date_start' => Carbon::tomorrow(),
            'date_end'   => Carbon::tomorrow()->addDays(3),
        ]);
        $newDate = Carbon::now()->addDays(30);

        $this->service->postpone($regatta, $newDate);

        $expectedEnd = (clone $newDate)->addDays(3);
        $this->assertTrue($expectedEnd->isSameDay($regatta->fresh()->date_end));
    }

    public function test_postpone_keeps_original_status_upcoming(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame(RegattaStatus::Upcoming, $regatta->fresh()->regatta_status);
    }

    public function test_postpone_keeps_original_external_id(): void
    {
        $regatta    = $this->makeUpcomingRegatta();
        $externalId = $regatta->external_id;

        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame($externalId, $regatta->fresh()->external_id);
    }

    public function test_postpone_creates_snapshot_with_postponed_status(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $snapshot = $this->snapshotFor($regatta);

        $this->assertNotNull($snapshot);
        $this->assertNotSame($regatta->id, $snapshot->id);
        $this->assertSame(RegattaStatus::Postponed, $snapshot->regatta_status);
    }

    public function test_postpone_snapshot_keeps_old_dates(): void
    {
        $oldStart = Carbon::tomorrow();
        $oldEnd   = Carbon::tomorrow()->addDays(2);
        $regatta  = $this->makeUpcomingRegatta([
            'date_start' => $oldStart,
            'date_end'   => $oldEnd,
        ]);

        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $snapshot = $this->snapshotFor($regatta);
        $this->assertTrue($oldStart->isSameDay($snapshot->date_start));
        $this->assertTrue($oldEnd->isSameDay($snapshot->date_end));
    }

    public function test_postpone_snapshot_links_to_original_with_date(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $this->service->postpone($regatta, $newDate);

        $snapshot = $this->snapshotFor($regatta);
        $this->assertSame($regatta->id, $snapshot->postponed_to_regatta_id);
        $this->assertTrue($newDate->isSameDay($snapshot->postponed_to_date));
    }

    public function test_postpone_snapshot_has_unique_external_id(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $snapshot = $this->snapshotFor($regatta);
        $this->assertNotNull($snapshot->external_id);
        $this->assertNotSame($regatta->external_id, $snapshot->external_id);
    }

    public function test_postpone_creates_exactly_one_new_regatta(): void
    {
        $regatta     = $this->makeUpcomingRegatta();
        $countBefore = Regatta::count();

        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame($countBefore + 1, Regatta::count());
    }

    public function test_postpone_is_idempotent_on_second_call(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $first = $this->service->postpone($regatta, $newDate);
        $regatta->refresh();
        $countAfterFirst = Regatta::count();

        // Регата уже стоит на нужной дате — снимок не должен создаваться
        $second = $this->service->postpone($regatta, $newDate);

        $this->assertSame($countAfterFirst, Regatta::count());
        $this->assertSame($first->id, $second->id);
    }

    public function test_postpone_throws_for_active_regatta(): void
    {
        $regatta = $this->makeUpcomingRegatta([
            'date_start' => Carbon::yesterday(),
            'date_end'   => Carbon::tomorrow(),
        ]);

        $this->expectException(\LogicException::class);
        $this->service->postpone($regatta, Carbon::now()->addDays(30));
    }

    public function test_postpone_copies_name_to_snapshot(): void
    {
        $regatta = $this->makeUpcomingRegatta(['name' => 'Кубок Балтики 2026']);
        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame('Кубок Балтики 2026', $this->snapshotFor($regatta)->name);
    }

    public function test_postpone_copies_season_to_snapshot(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame($regatta->season_id, $this->snapshotFor($regatta)->season_id);
    }
}
