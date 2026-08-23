<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Contracts\ServiceSubject;
use App\Enums\ServiceForm;
use App\Enums\ServiceRequestStatus;
use App\Enums\ServiceType;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Mail\ServiceRequested;
use App\Mail\ServiceRequestReceived;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;

/**
 * Заявка из раздела «Услуги» (ТЗ 3-го этапа, п. 7).
 *
 * ТЗ требует три адресата: письмо в отдел заказов с темой, называющей услугу,
 * колокольчик в админ-панели и счётчик на дашборде (последний — виджет
 * RequestsOverview, читающий эти же записи). Сбой отправки письма не должен
 * ронять запрос: заявка уже сохранена и видна в админке.
 */
class SubmitServiceRequestAction
{
    public function __construct(
        private readonly AdminRecipients $recipients,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     phone: string,
     *     email?: string|null,
     *     comment?: string|null,
     *     date_start?: string|null,
     *     date_end?: string|null,
     *     quantity?: int|string|null,
     *     payload?: array<string, mixed>,
     *     source?: string|null,
     * }  $data
     * @param  Model|null  $subject  Объект заявки: яхта, тур, регата, сертификат.
     * @param  ServiceForm  $form  форма-источник: от неё зависит белый список payload
     */
    public function handle(
        ServiceType $type,
        array $data,
        ?User $user = null,
        ?Model $subject = null,
        ServiceForm $form = ServiceForm::Modal,
    ): ServiceRequest {
        $serviceRequest = ServiceRequest::create([
            'type' => $type,
            'status' => ServiceRequestStatus::New,
            'user_id' => $user?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'comment' => $data['comment'] ?? null,
            'date_start' => $type->usesDateRange() ? ($data['date_start'] ?? null) : null,
            'date_end' => $type->usesDateRange() ? ($data['date_end'] ?? null) : null,
            'quantity' => $type->usesQuantity() && ($data['quantity'] ?? null) !== null
                ? (int) $data['quantity']
                : null,
            'payload' => $this->cleanPayload($type, (array) ($data['payload'] ?? []), $subject, $form),
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
        ]);

        // Связи проставляем вручную: и письмо, и уведомление обращаются к ним,
        // а перезагружать только что созданную запись незачем.
        $serviceRequest->setRelation('user', $user);
        $serviceRequest->setRelation('subject', $subject);

        $this->mailOrderDepartment($serviceRequest);
        $this->mailCustomer($serviceRequest);
        $this->notifyPanel($serviceRequest);

        return $serviceRequest;
    }

    /**
     * Оставляем в payload только поля, реально показанные формой.
     *
     * Без белого списка в json попадёт произвольное содержимое запроса —
     * данные приходят из открытой формы на публичном сайте. Список берём с
     * учётом объекта заявки: поля, которых у него нет (например, выбор яхты у
     * регаты без свободного чартера), не должны просачиваться в payload.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function cleanPayload(
        ServiceType $type,
        array $raw,
        ?Model $subject = null,
        ServiceForm $form = ServiceForm::Modal,
    ): array {
        $payload = [];

        foreach ($type->formFields($subject instanceof ServiceSubject ? $subject : null, $form) as $key => $field) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }

            // Поле, привязанное к другому, сохраняем только при выполненном
            // условии: выбранную и затем отменённую яхту в заявке видеть незачем.
            $condition = $field['visible_when'] ?? null;

            if ($condition !== null && ($raw[$condition[0]] ?? null) !== $condition[1]) {
                continue;
            }

            $value = $raw[$key];

            if ($field['type'] === 'checkbox') {
                $payload[$key] = (bool) $value;

                continue;
            }

            $value = trim((string) $value);

            if ($value !== '') {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private function mailOrderDepartment(ServiceRequest $serviceRequest): void
    {
        try {
            Mail::to($this->settings->orderEmail())->send(
                new ServiceRequested($serviceRequest, $this->adminUrl()),
            );
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Копия заявки заказчику: ТЗ требует уведомлять обе стороны.
     *
     * Email в большинстве подразделов необязателен, поэтому письмо уходит
     * только когда его оставили. Сбой отправки, как и с письмом в отдел
     * заказов, не должен ронять уже сохранённую заявку.
     */
    private function mailCustomer(ServiceRequest $serviceRequest): void
    {
        if (blank($serviceRequest->email)) {
            return;
        }

        try {
            Mail::to($serviceRequest->email)->send(new ServiceRequestReceived($serviceRequest));
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function notifyPanel(ServiceRequest $serviceRequest): void
    {
        $admins = $this->recipients->forSection(ServiceRequestResource::class);

        if ($admins->isEmpty()) {
            return;
        }

        // Уведомление шлём напрямую через Filament, а не через
        // App\Notifications\UserNotification: иначе личные настройки категорий
        // получателя глушат служебное уведомление админ-панели.
        // Объект заявки (конкретный поход) важнее дат: даты у него свои.
        $details = array_filter([
            $serviceRequest->subjectLabel(),
            $serviceRequest->dateRangeLabel(),
            $serviceRequest->quantityLabel(),
        ]);

        Notification::make()
            ->title('Новая заявка: '.$serviceRequest->type->label())
            ->body("{$serviceRequest->name}, {$serviceRequest->phone}"
                .($details === [] ? '' : ' — '.implode(', ', $details)))
            ->icon($serviceRequest->type->icon())
            ->actions([
                Action::make('open')
                    ->label('Открыть')
                    // Панель указываем явно: заявки живут в админ-панели,
                    // а уведомление создаётся в контексте публичного сайта.
                    ->url($this->adminUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }

    private function adminUrl(): string
    {
        return ServiceRequestResource::getUrl(panel: 'admin');
    }
}
