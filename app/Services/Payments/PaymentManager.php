<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\PaymentProviderCode;
use App\Services\Payments\Providers\TestPaymentProvider;
use App\Services\SettingsService;

/**
 * Резолвит активный провайдер эквайринга по настройкам
 * (группа settings «payments», редактируется в админке).
 */
class PaymentManager
{
    public function __construct(
        private ?SettingsService $settings = null,
    ) {}

    /** Включена ли онлайн-оплата и готов ли активный провайдер. */
    public function isEnabled(): bool
    {
        if (! (bool) $this->settings()->get('payments.enabled', false)) {
            return false;
        }

        return $this->activeProvider() !== null;
    }

    /** Активный сконфигурированный провайдер; null — оплата недоступна. */
    public function activeProvider(): ?PaymentGateway
    {
        $code = PaymentProviderCode::tryFrom(
            (string) $this->settings()->get('payments.provider', ''),
        );

        if ($code === null) {
            return null;
        }

        $provider = $this->provider($code);

        return $provider->isConfigured() ? $provider : null;
    }

    /** Инстанс провайдера по коду (без проверки конфигурации). */
    public function provider(PaymentProviderCode $code): PaymentGateway
    {
        return match ($code) {
            PaymentProviderCode::Test => new TestPaymentProvider,
        };
    }

    private function settings(): SettingsService
    {
        return $this->settings ??= app(SettingsService::class);
    }
}
