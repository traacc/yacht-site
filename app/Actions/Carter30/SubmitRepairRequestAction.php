<?php

declare(strict_types=1);

namespace App\Actions\Carter30;

use App\Filament\Resources\RepairRequests\RepairRequestResource;
use App\Mail\RepairRequested;
use App\Models\RepairCase;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

/**
 * Кнопка «Хотите такой ремонт?» раздела «Carter 30».
 *
 * ТЗ 3-го этапа требует запрос в отдел заказов с темой, называющей конкретный
 * кейс. Дублируем уведомление колокольчиком в админ-панели: заявка, живущая
 * только в почтовом ящике, теряется. Сбой отправки письма не должен ронять
 * запрос — заявка уже сохранена и видна в админке.
 */
class SubmitRepairRequestAction
{
    public function __construct(
        private readonly AdminRecipients $recipients,
        private readonly SettingsService $settings,
    ) {}

    /**
     * @param  array{name: string, phone: string, email?: string|null, comment?: string|null, source?: string|null}  $data
     */
    public function handle(array $data, ?RepairCase $case = null, ?User $user = null): RepairRequest
    {
        $repairRequest = RepairRequest::create([
            'repair_case_id' => $case?->getKey(),
            'user_id' => $user?->getKey(),
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'comment' => $data['comment'] ?? null,
            'source' => $data['source'] ?? request()->header('Referer', 'unknown'),
        ]);

        // Связи проставляем вручную: и письмо, и уведомление обращаются к ним,
        // а перезагружать только что созданную запись незачем.
        $repairRequest->setRelation('repairCase', $case);
        $repairRequest->setRelation('user', $user);

        $this->mailOrderDepartment($repairRequest);
        $this->notifyPanel($repairRequest);

        return $repairRequest;
    }

    private function mailOrderDepartment(RepairRequest $repairRequest): void
    {
        try {
            Mail::to($this->settings->orderEmail())->send(
                new RepairRequested($repairRequest, $this->adminUrl()),
            );
        } catch (\Exception $e) {
            report($e);
        }
    }

    private function notifyPanel(RepairRequest $repairRequest): void
    {
        $admins = $this->recipients->forSection(RepairRequestResource::class);

        if ($admins->isEmpty()) {
            return;
        }

        $case = $repairRequest->repairCase?->title;

        Notification::make()
            ->title('Новая заявка на ремонт')
            ->body($case !== null && $case !== ''
                ? "{$repairRequest->name}, {$repairRequest->phone} — кейс «{$case}»"
                : "{$repairRequest->name}, {$repairRequest->phone}")
            ->icon('heroicon-o-wrench-screwdriver')
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
        return RepairRequestResource::getUrl(panel: 'admin');
    }
}
