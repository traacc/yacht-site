<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\WorldNews\HostResolver;

/**
 * Подменяет DNS, чтобы тесты не зависели от сети: вымышленные хосты
 * (news.example.test и подобные) в реальном DNS не существуют, а
 * PublicUrlFetcher без резолва считает адрес недоступным.
 */
trait FakesHostResolution
{
    /** @param array<string, list<string>> $map Хост => его адреса. */
    protected function fakeHostResolution(array $map = [], string $default = '93.184.216.34'): void
    {
        $this->swap(HostResolver::class, new class($map, $default) extends HostResolver
        {
            /** @param array<string, list<string>> $map */
            public function __construct(private readonly array $map, private readonly string $default) {}

            /** @return list<string> */
            public function resolve(string $host): array
            {
                $host = trim($host, '[]');

                if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
                    return [$host];
                }

                return $this->map[$host] ?? [$this->default];
            }
        });
    }
}
