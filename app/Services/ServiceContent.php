<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ServiceType;

/**
 * Названия и описания подразделов «Услуг», переопределяемые в админке.
 *
 * Название подраздела встречается в меню, в карточках хаба, в шапке лендинга,
 * в письмах и в админке, поэтому переопределение живёт не в шаблонах, а рядом
 * с самим подразделом: ServiceType::label() и его соседи спрашивают текст
 * здесь. Пустая настройка означает «оставить текст по умолчанию»
 * (@see ServiceType::defaultLabel()), а не пустую строку на сайте.
 *
 * Регистрируется синглтоном ради мемоизации: меню и карточки дёргают label()
 * для каждого подраздела, а вся группа настроек читается одним запросом.
 */
class ServiceContent
{
    /** Ключи лендингов лежат в той же группе настроек, что и остальной раздел. */
    private const GROUP = 'services';

    private const HUB_TITLE = 'Услуги';

    private const HUB_TAGLINE = 'Аренда яхт и флота, мероприятия на воде и обучение судовождению';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function __construct(private readonly SettingsService $settings) {}

    public function title(ServiceType $type): string
    {
        return $this->override($type, 'title') ?? $type->defaultLabel();
    }

    public function shortDescription(ServiceType $type): string
    {
        return $this->override($type, 'short_description') ?? $type->defaultShortDescription();
    }

    public function tagline(ServiceType $type): string
    {
        return $this->override($type, 'tagline') ?? $type->defaultTagline();
    }

    public function seoDescription(ServiceType $type): string
    {
        return $this->override($type, 'seo_description') ?? $type->defaultSeoDescription();
    }

    /** Название раздела целиком — пункт меню «Услуги» и заголовок хаба. */
    public function hubTitle(): string
    {
        return $this->text('services.hub.title') ?? self::HUB_TITLE;
    }

    public function hubTagline(): string
    {
        return $this->text('services.hub.tagline') ?? self::HUB_TAGLINE;
    }

    private function override(ServiceType $type, string $key): ?string
    {
        return $this->text($type->settingsPrefix().'.'.$key);
    }

    /** Непустая настройка или null — чтобы вызывающий подставил текст по умолчанию. */
    private function text(string $key): ?string
    {
        $value = $this->values()[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        return $this->values ??= $this->settings->getGroup(self::GROUP);
    }
}
