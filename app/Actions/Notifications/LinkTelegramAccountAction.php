<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use App\Services\Telegram\TelegramUpdateHandler;
use Illuminate\Support\Facades\DB;

/**
 * Погашает токен из команды /start и привязывает чат Telegram к пользователю.
 *
 * @see TelegramUpdateHandler
 */
final class LinkTelegramAccountAction
{
    /**
     * @param  array{chat_id: string, username?: ?string, first_name?: ?string}  $chat
     * @return User|null null, если токен не найден, истёк или уже использован
     */
    public function handle(string $plainToken, array $chat): ?User
    {
        $token = TelegramLinkToken::query()
            ->where('token_hash', TelegramLinkToken::hashToken($plainToken))
            ->usable()
            ->first();

        if ($token === null) {
            return null;
        }

        return DB::transaction(function () use ($token, $chat): User {
            /** @var User $user */
            $user = $token->user()->firstOrFail();

            // Один чат Telegram — один аккаунт на сайте: старую привязку снимаем.
            TelegramAccount::query()
                ->where('chat_id', $chat['chat_id'])
                ->where('user_id', '!=', $user->getKey())
                ->delete();

            TelegramAccount::updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'chat_id' => $chat['chat_id'],
                    'username' => $chat['username'] ?? null,
                    'first_name' => $chat['first_name'] ?? null,
                    'linked_at' => now(),
                    'blocked_at' => null,
                ],
            );

            $token->forceFill(['used_at' => now()])->save();

            // Прочие непогашенные токены пользователя больше не нужны.
            TelegramLinkToken::query()
                ->where('user_id', $user->getKey())
                ->whereKeyNot($token->getKey())
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            $user->unsetRelation('telegramAccount');

            return $user;
        });
    }
}
