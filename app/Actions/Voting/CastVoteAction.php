<?php

declare(strict_types=1);

namespace App\Actions\Voting;

use App\Enums\VotingStatus;
use App\Models\Voting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CastVoteAction
{
    /**
     * Записывает голос пользователя за выбранные варианты.
     * Повторная отправка перезаписывает предыдущий выбор пользователя.
     *
     * @param  array<int, string>  $optionIds  выбранные voting_option.id
     */
    public function handle(Voting $voting, array $optionIds, string $userId): void
    {
        if ($voting->status !== VotingStatus::Active) {
            throw ValidationException::withMessages([
                'voting_option' => 'Это голосование сейчас неактивно.',
            ]);
        }

        if ($voting->ends_at && $voting->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'voting_option' => 'Срок голосования истёк.',
            ]);
        }

        // Оставляем только варианты, реально принадлежащие этому голосованию
        $validOptionIds = $voting->options()
            ->whereIn('id', $optionIds)
            ->pluck('id')
            ->all();

        if (empty($validOptionIds)) {
            throw ValidationException::withMessages([
                'voting_option' => 'Выберите вариант ответа.',
            ]);
        }

        if (! $voting->allow_multiple) {
            $validOptionIds = [$validOptionIds[0]];
        }

        DB::transaction(function () use ($voting, $validOptionIds, $userId): void {
            // Перезаписываем выбор: удаляем прежние голоса пользователя в этом голосовании
            $voting->votes()->where('user_id', $userId)->delete();

            foreach ($validOptionIds as $optionId) {
                $voting->votes()->create([
                    'voting_option_id' => $optionId,
                    'user_id'          => $userId,
                ]);
            }
        });
    }
}
