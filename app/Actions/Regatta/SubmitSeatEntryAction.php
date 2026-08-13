<?php

declare(strict_types=1);

namespace App\Actions\Regatta;

use App\Enums\ParticipationKind;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\RegattaEntrySource;
use App\Mail\RegattaEntrySubmitted;
use App\Models\Regatta;
use App\Models\RegattaEntry;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Заявка на регулярную или выездную регату: экипажем или индивидуально.
 *
 * От клубной заявки (@see SubmitRegattaEntryAction) отличается тем, что здесь
 * нет ни команды, ни своей яхты: лодку выставляет ассоциация, а экипаж бывает
 * сборным. Поэтому участники описываются контактами, а не `team_members`,
 * и заявка обязательно проходит через администратора — статус `pending`.
 */
final class SubmitSeatEntryAction
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string}  $applicant  Контакты заявителя (он же рулевой экипажа).
     * @param  list<array{name: string, email?: ?string, phone?: ?string}>  $crew  Остальные участники — только для заявки экипажем.
     *
     * @throws ValidationException
     */
    public function handle(
        Regatta $regatta,
        ParticipationKind $kind,
        array $applicant,
        array $crew = [],
        ?User $actor = null,
    ): RegattaEntry {
        if (! $regatta->isOpenForRegistration()) {
            throw ValidationException::withMessages([
                'regatta' => 'Заявки на эту регату больше не принимаются.',
            ]);
        }

        if ($kind === ParticipationKind::Individual && ! $regatta->allowsIndividualEntry()) {
            throw ValidationException::withMessages([
                'participation_kind' => 'На клубную регату заявляются экипажем.',
            ]);
        }

        // Индивидуальная заявка — это один человек; список экипажа игнорируем,
        // чтобы форма не могла протащить лишних людей мимо оплаты мест.
        $members = $kind === ParticipationKind::Individual
            ? []
            : array_values(array_filter($crew, fn (array $member): bool => filled($member['name'] ?? null)));

        // Заявитель всегда в экипаже — он рулевой, поэтому +1 к числу мест.
        $peopleCount = count($members) + 1;
        $limit = $regatta->maxCrewSize();

        if ($limit !== null && $peopleCount > $limit) {
            throw ValidationException::withMessages([
                'crew' => "В экипаже не может быть больше {$limit} чел.",
            ]);
        }

        if ($actor !== null && $regatta->entries()->where('user_id', $actor->id)->whereIn('status', ['pending', 'approved'])->exists()) {
            throw ValidationException::withMessages([
                'regatta' => 'Вы уже подали заявку на эту регату.',
            ]);
        }

        $entry = DB::transaction(function () use ($regatta, $kind, $applicant, $members, $actor, $peopleCount): RegattaEntry {
            $entry = RegattaEntry::create([
                'regatta_id' => $regatta->id,
                'team_id' => null,
                'yacht_id' => null,
                'participation_kind' => $kind,
                'user_id' => $actor?->id,
                // Через администратора: он подтверждает оплату и сажает людей на лодки.
                'status' => 'pending',
                'source' => $actor !== null
                    ? RegattaEntrySource::PersonalCabinet
                    : RegattaEntrySource::QuickRequest,
                'submitted_at' => now(),
            ]);

            $entry->crew()->create([
                'user_id' => $actor?->id,
                'full_name' => trim($applicant['name']),
                'email' => $applicant['email'] ?? null,
                'phone' => $applicant['phone'] ?? null,
                'role' => 'captain',
            ]);

            foreach ($members as $member) {
                $entry->crew()->create([
                    'full_name' => trim($member['name']),
                    'email' => $member['email'] ?? null,
                    'phone' => $member['phone'] ?? null,
                    'role' => 'main',
                ]);
            }

            $amount = $regatta->entryPrice($kind, $peopleCount);

            // Цены может не быть (организатор их не задал) — тогда счёт выставит
            // администратор вручную, заявка от этого не блокируется.
            if ($amount !== null) {
                $entry->paymentRegistries()->create([
                    'name' => $kind === ParticipationKind::Individual
                        ? "Место в регате — {$regatta->name}"
                        : "Лодка в регате — {$regatta->name}",
                    'amount' => $amount,
                    'purpose' => PaymentPurpose::EntryFee,
                    'status' => PaymentStatus::Pending,
                ]);
            }

            return $entry;
        });

        $this->notifyAdmins($entry);

        return $entry;
    }

    /** Сбой почты не отменяет поданную заявку — она уже ждёт админа в очереди. */
    private function notifyAdmins(RegattaEntry $entry): void
    {
        $recipients = $this->settings->adminNotificationEmails();

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new RegattaEntrySubmitted($entry));
        } catch (\Exception $e) {
            report($e);
        }
    }
}
