<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FlashCallService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

class FlashCallCheck extends Command
{
    protected $signature = 'flashcall:check {phone? : Номер для тестового звонка, например +79991234567}';

    protected $description = 'Проверяет доступ к сервису «Звонок» (zvonok.com) и, если указан номер, заказывает тестовый звонок Flash Call.';

    public function handle(FlashCallService $flashCall): int
    {
        if (! $flashCall->isConfigured()) {
            $this->error('Не заданы ZVONOK_PUBLIC_KEY и/или ZVONOK_CAMPAIGN_ID — подтверждение звонком недоступно.');

            return self::FAILURE;
        }

        $this->line('Проверка доступа к '.config('services.zvonok.base_url').' …');

        $balance = $flashCall->balance();

        if ($balance === null) {
            $this->error('API недоступно или ключ доступа не принят (подробности — в логе).');

            return self::FAILURE;
        }

        $this->info("Ключ принят, остаток на счету: {$balance}.");

        $phone = $this->argument('phone');

        if ($phone === null) {
            $this->line('Номер не указан — тестовый звонок не заказывался.');

            return self::SUCCESS;
        }

        $normalized = PhoneNumber::international((string) $phone);

        if ($normalized === null) {
            $this->error("Не удалось разобрать номер: {$phone}");

            return self::FAILURE;
        }

        $result = $flashCall->call($normalized);

        if (! $result->ok) {
            $this->error('Звонок не заказан: '.$result->message());

            return self::FAILURE;
        }

        // Код показываем только здесь: команду запускает администратор,
        // проверяющий, что звонок доходит и цифры совпадают.
        $this->info('Звонок заказан. Код (последние цифры номера): '.(string) $result->pincode()
            .', call_id: '.(string) $result->callId());

        return self::SUCCESS;
    }
}
