<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;

class SmsCheck extends Command
{
    protected $signature = 'sms:check {phone? : Номер для тестового SMS, например +79991234567} {--text= : Текст сообщения}';

    protected $description = 'Проверяет связь с SMS-провайдером i-digital direct и, если указан номер, отправляет тестовое сообщение.';

    public function handle(SmsService $sms): int
    {
        $this->line('Проверка соединения с '.config('services.i_dgtl.base_url').' …');

        if (! $sms->ping()) {
            $this->error('API провайдера недоступно (ping не вернул PONG).');

            return self::FAILURE;
        }

        $this->info('Соединение есть: PONG.');

        if (! $sms->isConfigured()) {
            $this->error('Не заданы IDGTL_API_KEY и/или IDGTL_SENDER_NAME — отправка недоступна.');

            return self::FAILURE;
        }

        $phone = $this->argument('phone');

        if ($phone === null) {
            $this->line('Номер не указан — тестовое сообщение не отправлялось.');

            return self::SUCCESS;
        }

        $normalized = PhoneNumber::normalize((string) $phone);

        if ($normalized === null) {
            $this->error("Не удалось разобрать номер: {$phone}");

            return self::FAILURE;
        }

        $result = $sms->send(
            $normalized,
            (string) ($this->option('text') ?: 'Проверка отправки SMS с сайта.'),
        );

        if (! $result->ok) {
            $this->error('Отправка не удалась: '.$result->message());

            return self::FAILURE;
        }

        $this->info('Сообщение принято провайдером. messageUuid: '.(string) $result->messageUuid);

        return self::SUCCESS;
    }
}
