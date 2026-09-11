<?php

declare(strict_types=1);

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Привязать к пользователям заявки на восстановление пароля, сохранённые без связи.
     *
     * При подаче пользователь искался только по email, а у авторегистрированных
     * участников в профиле технический адрес — их заявки остались без ФИО.
     * Ищем так же, как теперь при подаче (PasswordResetRequest::matchUser()):
     * по email, затем по единственному совпадению нормализованного телефона.
     * Логика продублирована намеренно, чтобы миграция не зависела от модели.
     */
    public function up(): void
    {
        $requests = DB::table('password_reset_requests')
            ->whereNull('user_id')
            ->get(['id', 'email', 'phone']);

        if ($requests->isEmpty()) {
            return;
        }

        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->get(['id', 'email', 'phone']);

        foreach ($requests as $request) {
            $byEmail = $users->filter(
                static fn (object $user): bool => $user->email !== null
                    && mb_strtolower($user->email) === mb_strtolower($request->email),
            );

            $digits = PhoneNumber::normalize($request->phone);
            $byPhone = $digits === null ? collect() : $users->filter(
                static fn (object $user): bool => PhoneNumber::normalize($user->phone) === $digits,
            );

            $match = match (true) {
                $byEmail->count() === 1 => $byEmail->first(),
                $byPhone->count() === 1 => $byPhone->first(),
                default => null,
            };

            if ($match !== null) {
                DB::table('password_reset_requests')
                    ->where('id', $request->id)
                    ->update(['user_id' => $match->id]);
            }
        }
    }

    /**
     * Необратима: какие связи были пустыми до миграции, не сохраняется.
     */
    public function down(): void {}
};
