<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

/**
 * Разрешает имя хоста в список IP-адресов.
 *
 * Вынесено отдельным классом, чтобы PublicUrlFetcher можно было проверять
 * тестами без обращения к реальному DNS.
 */
class HostResolver
{
    /** @return list<string> */
    public function resolve(string $host): array
    {
        $host = trim($host, '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
