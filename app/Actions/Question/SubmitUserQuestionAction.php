<?php

declare(strict_types=1);

namespace App\Actions\Question;

use App\Filament\Resources\UserQuestionResource;
use App\Mail\UserQuestionSubmitted;
use App\Models\User;
use App\Models\UserQuestion;
use App\Services\Notifications\AdminRecipients;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Пользователь задал вопрос администрации (модалка «Задать вопрос»).
 *
 * Помимо сохранения вопроса уведомляет администрацию двумя путями: письмом на
 * адреса из настроек сайта и «колокольчиком» в админ-панели. Сбой отправки
 * письма не должен ронять запрос — вопрос уже сохранён и виден в админке.
 */
class SubmitUserQuestionAction
{
    public function __construct(
        private readonly AdminRecipients $recipients,
        private readonly SettingsService $settings,
    ) {}

    public function handle(User $user, string $question): UserQuestion
    {
        $userQuestion = UserQuestion::create([
            'user_id' => $user->getKey(),
            'question' => $question,
        ]);

        $this->mailAdmins($userQuestion);
        $this->notifyPanel($userQuestion);

        return $userQuestion;
    }

    /** Письмо на общий адрес администрации («почта info» в терминах ТЗ). */
    private function mailAdmins(UserQuestion $question): void
    {
        $emails = $this->settings->adminNotificationEmails();

        if ($emails === []) {
            return;
        }

        try {
            Mail::to($emails)->send(new UserQuestionSubmitted($question));
        } catch (\Exception $e) {
            report($e);
        }
    }

    /**
     * Колокольчик в админ-панели.
     *
     * Намеренно не через UserNotification: тот фильтрует каналы личными
     * настройками категорий пользователя, а служебное уведомление о новом
     * вопросе не должно глушиться галочкой в личном кабинете. Плюс письмо
     * на общий адрес уже отправлено выше — дублировать его на личные адреса
     * сотрудников незачем.
     */
    private function notifyPanel(UserQuestion $question): void
    {
        $admins = $this->recipients->forSection(UserQuestionResource::class);

        if ($admins->isEmpty()) {
            return;
        }

        Notification::make()
            ->title('Новый вопрос от пользователя')
            ->body(Str::limit($question->question, 200))
            ->icon('heroicon-o-chat-bubble-left-right')
            ->actions([
                Action::make('open')
                    ->label('Открыть')
                    // Панель указываем явно: страница вопросов живёт в админ-панели,
                    // а уведомление создаётся в контексте публичного сайта.
                    ->url(UserQuestionResource::getUrl(panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($admins);
    }
}
