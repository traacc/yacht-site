<?php

declare(strict_types=1);

namespace App\Actions\RegattaEntry;

use App\Enums\CrewJoinRequestStatus;
use App\Filament\Resources\CrewJoinRequests\CrewJoinRequestResource;
use App\Mail\CrewJoinRequestSubmitted;
use App\Models\CrewJoinRequest;
use App\Models\RegattaEntry;
use App\Models\User;
use App\Notifications\CrewJoinRequestSubmittedNotification;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Отклик «Хочу в этот экипаж» на клубной регате.
 *
 * Экипаж открывает добор при подаче заявки (`open_for_join`) и указывает почту
 * для откликов. Об отклике узнают трое: экипаж — письмом на указанную почту,
 * автор заявки — уведомлением в ЛК, администрация — и тем и другим.
 */
final class SubmitCrewJoinRequestAction
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AdminRecipients $adminRecipients,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, message?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function handle(RegattaEntry $entry, array $data, ?User $actor = null): CrewJoinRequest
    {
        $entry->loadMissing(['regatta', 'team']);

        if (! $entry->isOpenForJoin()) {
            throw ValidationException::withMessages([
                'entry' => 'Этот экипаж не набирает участников.',
            ]);
        }

        $email = mb_strtolower(trim($data['email']));

        // Повторный отклик того же человека — уже поданный ждёт решения капитана.
        $alreadyApplied = $entry->crewJoinRequests()
            ->where('status', CrewJoinRequestStatus::Pending)
            ->where(function ($query) use ($email, $actor) {
                $query->whereRaw('LOWER(email) = ?', [$email]);

                if ($actor !== null) {
                    $query->orWhere('user_id', $actor->id);
                }
            })
            ->exists();

        if ($alreadyApplied) {
            throw ValidationException::withMessages([
                'email' => 'Вы уже откликнулись на этот экипаж — ждите ответа.',
            ]);
        }

        $request = DB::transaction(fn (): CrewJoinRequest => $entry->crewJoinRequests()->create([
            'user_id' => $actor?->id,
            'name' => trim($data['name']),
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => CrewJoinRequestStatus::Pending,
        ]));

        $this->notify($request->fresh(['regattaEntry.regatta', 'regattaEntry.team']));

        return $request;
    }

    /**
     * Рассылка об отклике. Сбой почты не должен отменять уже сохранённый
     * отклик — поэтому за пределами транзакции и под report().
     */
    private function notify(CrewJoinRequest $request): void
    {
        $entry = $request->regattaEntry;

        // Почта экипажа (её указали при открытии добора) + служебные адреса info@.
        $recipients = array_values(array_unique(array_filter([
            $entry->joinNotificationEmail(),
            ...$this->settings->adminNotificationEmails(),
        ])));

        if ($recipients !== []) {
            try {
                Mail::to($recipients)->send(new CrewJoinRequestSubmitted($request));
            } catch (\Exception $e) {
                report($e);
            }
        }

        $author = $entry->notifiableUser();

        $notification = new CrewJoinRequestSubmittedNotification(
            applicantName: $request->name,
            applicantContacts: trim($request->email.($request->phone ? ', '.$request->phone : '')),
            regattaName: (string) $entry->regatta?->name,
            teamName: $entry->team?->name,
            message: $request->message,
        );

        if ($author !== null) {
            $author->notify($notification);
        }

        $admins = $this->adminRecipients->forSection(CrewJoinRequestResource::class)
            // Автору заявки, если он же администратор, второе уведомление ни к чему.
            ->reject(fn (User $admin): bool => $admin->is($author));

        if ($admins->isNotEmpty()) {
            Notification::send($admins, $notification->forAdmin());
        }
    }
}
