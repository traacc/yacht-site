<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SportCategory;
use App\Models\RegattaEntry;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Экипаж заявки на регату.
 *
 * Показывает только состав экипажа конкретной заявки (в отличие от карточки
 * команды {@see TeamCardModal}, где выводится вся информация о команде).
 *
 * Открывается из любого места событием 'open-entry-crew' с id заявки:
 *   - в обычном JS:  onclick="Livewire.dispatch('open-entry-crew', { entryId: '...' })"
 *
 * Регистрируется один раз в общем layout (resources/views/layouts/public.blade.php).
 */
class EntryCrewModal extends Component
{
    public bool $isOpen = false;

    /** Подготовленные для отображения данные экипажа (null = окно закрыто). */
    public ?array $entry = null;

    /** Порядок вывода ролей: капитан → основной состав → запасные. */
    private const ROLE_ORDER = ['captain' => 0, 'main' => 1, 'reserve' => 2];

    private const ROLE_LABELS = [
        'captain' => 'Капитан',
        'main' => 'Основной состав',
        'reserve' => 'Запасной',
    ];

    #[On('open-entry-crew')]
    public function open(string $entryId): void
    {
        $entry = RegattaEntry::with(['yacht', 'crew.teamMember.user', 'crew.user'])->find($entryId);

        if (! $entry instanceof RegattaEntry) {
            return;
        }

        $crew = $entry->crew
            ->sortBy(fn ($member) => self::ROLE_ORDER[$member->role] ?? 99)
            ->map(function ($member) {
                // Сборный экипаж: человек привязан к аккаунту напрямую или
                // заявлен контактами — карточка открывается только для аккаунта.
                $user = $member->teamMember?->user ?? $member->user;

                return [
                    'id' => $user?->id,
                    'name' => $user?->short_name ?? $member->displayName(),
                    'avatar' => $user?->photo_url ? asset('storage/'.$user->photo_url) : null,
                    'role' => self::ROLE_LABELS[$member->role] ?? $member->role,
                    'rank' => SportCategory::labelOrNone($user?->sport_category),
                ];
            })
            ->values()
            ->toArray();

        $this->entry = [
            'yacht' => $entry->yacht?->name ?? '—',
            'crew' => $crew,
        ];

        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->entry = null;
    }

    public function render()
    {
        return view('livewire.entry-crew-modal');
    }
}
