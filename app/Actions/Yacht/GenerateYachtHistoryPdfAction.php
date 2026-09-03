<?php

namespace App\Actions\Yacht;

use App\Enums\RegattaStatus;
use App\Models\RegattaResultItem;
use App\Models\Yacht;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

final class GenerateYachtHistoryPdfAction
{
    /**
     * Сформировать PDF с историей яхты: паспортные данные,
     * владелец и участие в регатах с занятыми местами.
     */
    public function execute(Yacht $yacht): Response
    {
        $yacht->load([
            // Заявки на удалённые (soft-deleted) регаты пропускаем: связь у них
            // отдаёт null, и в PDF попадала строка из одних прочерков.
            'regattaEntries' => fn ($query) => $query->whereHas('regatta'),
            'user', 'regattaEntries.regatta', 'regattaEntries.team',
        ]);

        // Места берём из итоговых протоколов: связи «яхта → результаты»
        // на модели нет, поэтому выбираем строки напрямую по yacht_id.
        $places = RegattaResultItem::query()
            ->where('yacht_id', $yacht->id)
            ->with('regattaResult')
            ->get()
            ->keyBy(fn (RegattaResultItem $item) => $item->regattaResult?->regatta_id);

        $participation = $yacht->regattaEntries
            ->sortByDesc(fn ($entry) => $entry->regatta?->date_start)
            ->map(fn ($entry) => [
                'regatta' => $entry->regatta?->name ?? '—',
                'date_event' => $entry->regatta?->dateRange() ?: '—',
                'team' => $entry->team?->name ?? '—',
                'date_registration' => $entry->submitted_at?->format('d.m.Y') ?? '—',
                'status' => $this->statusLabel(
                    $entry->status,
                    $entry->regatta?->regatta_status === RegattaStatus::Finished,
                ),
                'place' => $places->get($entry->regatta_id)?->final_position ?? '—',
            ])->values();

        $pdf = Pdf::loadView('pdf.yacht-history', [
            'yacht' => $yacht,
            'owner' => $yacht->user?->short_name ?? $yacht->owner_name ?? '—',
            'participation' => $participation,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', false);

        $safeName = preg_replace('/[^\w\s\-а-яё]/ui', '', (string) $yacht->name);
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)) ?: 'yacht';

        return $pdf->download("{$safeName}_history.pdf");
    }

    /**
     * Подписи статусов повторяют витрину /yachts (participation_status),
     * чтобы PDF не расходился с таблицей на странице.
     */
    private function statusLabel(?string $status, bool $regattaFinished): string
    {
        return match ($status) {
            'submitted', 'pending' => 'Заявка подана',
            'approved' => $regattaFinished ? 'Участвовала' : 'Участвует',
            'completed', 'finished' => 'Завершено',
            'rejected' => 'Отклонена',
            'withdrawn' => 'Отозвана',
            default => $status ?? '—',
        };
    }
}
