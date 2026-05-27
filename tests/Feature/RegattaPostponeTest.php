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

    // ──────────────────────────────────────────────
    // postpone()
    // ──────────────────────────────────────────────

    public function test_postpone_creates_new_regatta_with_new_date(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $newRegatta = $this->service->postpone($regatta, $newDate);

        $this->assertNotNull($newRegatta);
        $this->assertNotSame($regatta->id, $newRegatta->id);
        $this->assertTrue($newDate->isSameDay($newRegatta->date_start));
    }

    public function test_postpone_preserves_duration_in_new_regatta(): void
    {
        $regatta = $this->makeUpcomingRegatta([
            'date_start' => Carbon::tomorrow(),
            'date_end'   => Carbon::tomorrow()->addDays(3),
        ]);
        $newDate = Carbon::now()->addDays(30);

        $newRegatta = $this->service->postpone($regatta, $newDate);

        $expectedEnd = (clone $newDate)->addDays(3);
        $this->assertTrue($expectedEnd->isSameDay($newRegatta->date_end));
    }

    public function test_postpone_sets_new_regatta_status_to_upcoming(): void
    {
        $regatta    = $this->makeUpcomingRegatta();
        $newRegatta = $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame(RegattaStatus::Upcoming, $newRegatta->fresh()->regatta_status);
    }

    public function test_postpone_marks_original_regatta_as_postponed(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $this->service->postpone($regatta, $newDate);

        $regatta->refresh();
        $this->assertSame(RegattaStatus::Postponed, $regatta->regatta_status);
    }

    public function test_postpone_sets_postponed_to_date_on_original(): void
    {
        $regatta = $this->makeUpcomingRegatta();
        $newDate = Carbon::now()->addDays(30);

        $this->service->postpone($regatta, $newDate);

        $regatta->refresh();
        $this->assertTrue($newDate->isSameDay($regatta->postponed_to_date));
    }

    public function test_postpone_links_original_to_new_regatta(): void
    {
        $regatta    = $this->makeUpcomingRegatta();
        $newRegatta = $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $regatta->refresh();
        $this->assertSame($newRegatta->id, $regatta->postponed_to_regatta_id);
    }

    public function test_postpone_new_regatta_has_unique_external_id(): void
    {
        $regatta    = $this->makeUpcomingRegatta();
        $newRegatta = $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertNotNull($newRegatta->external_id);
        $this->assertNotSame($regatta->external_id, $newRegatta->external_id);
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

        $first           = $this->service->postpone($regatta, $newDate);
        $regatta->refresh();
        $countAfterFirst = Regatta::count();

        // Повторный вызов с той же регатой — новая регата не должна создаваться
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

    public function test_postpone_copies_name_to_new_regatta(): void
    {
        $regatta    = $this->makeUpcomingRegatta(['name' => 'Кубок Балтики 2026']);
        $newRegatta = $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame('Кубок Балтики 2026', $newRegatta->name);
    }

    public function test_postpone_copies_season_to_new_regatta(): void
    {
        $regatta    = $this->makeUpcomingRegatta();
        $newRegatta = $this->service->postpone($regatta, Carbon::now()->addDays(30));

        $this->assertSame($regatta->season_id, $newRegatta->season_id);
    }
}
