<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP-загрузка внешних адресов, полученных от AI-модели, с защитой от SSRF.
 *
 * Ссылки на источники приходят из недоверенного ответа модели, а страницы
 * источников могут увести редиректом куда угодно. Поэтому каждый переход
 * проверяется отдельно: адрес должен резолвиться в публичный IP, к которому
 * запрос и прибивается — иначе между проверкой и запросом DNS мог бы
 * подмениться (DNS rebinding).
 */
final class PublicUrlFetcher
{
    private const MAX_REDIRECTS = 5;

    /**
     * Диапазоны, не покрытые фильтрами PHP: CGNAT, broadcast,
     * IPv4-mapped и 6to4/Teredo, через которые доступен тот же localhost.
     */
    private const EXTRA_BLOCKED = [
        '100.64.0.0/10',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '198.18.0.0/15',
        '255.255.255.255/32',
        '::ffff:0:0/96',
        '2002::/16',
        '2001::/32',
        '64:ff9b::/96',
    ];

    public function __construct(
        private readonly HostResolver $resolver,
        private readonly UrlCanonicalizer $urls,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = [], int $connectTimeout = 5, int $timeout = 15): ?Response
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $address = $this->publicAddress($current);

            if ($address === null) {
                return null;
            }

            try {
                $response = Http::withHeaders($headers)
                    ->connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->withoutRedirecting()
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$address['pin']]]])
                    ->get($current);
            } catch (Throwable) {
                return null;
            }

            if (! $response->redirect()) {
                return $response;
            }

            $location = trim((string) $response->header('Location'));
            $next = $location === '' ? null : $this->urls->resolve($current, $location);

            if ($next === null) {
                return null;
            }

            $current = $next;
        }

        return null;
    }

    public function isPublic(string $url): bool
    {
        return $this->publicAddress($url) !== null;
    }

    /**
     * @return array{ip: string, pin: string}|null Проверенный адрес и строка
     *                                             CURLOPT_RESOLVE для привязки соединения.
     */
    private function publicAddress(string $url): ?array
    {
        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = (string) $parts['host'];
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        $addresses = $this->resolver->resolve($host);

        if ($addresses === []) {
            return null;
        }

        // Достаточно одного непубличного адреса, чтобы отказать: иначе хост
        // с парой A-записей мог бы увести запрос во внутреннюю сеть.
        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                return null;
            }
        }

        return [
            'ip' => $addresses[0],
            'pin' => sprintf('%s:%d:%s', trim($host, '[]'), $port, $addresses[0]),
        ];
    }

    private function isPublicAddress(string $address): bool
    {
        $valid = filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );

        if ($valid === false) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED as $range) {
            if ($this->inRange($address, $range)) {
                return false;
            }
        }

        return true;
    }

    private function inRange(string $address, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $packedAddress = @inet_pton($address);
        $packedSubnet = @inet_pton($subnet);

        if ($packedAddress === false || $packedSubnet === false
            || strlen($packedAddress) !== strlen($packedSubnet)) {
            return false;
        }

        $bits = (int) $bits;
        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if ($wholeBytes > 0 && strncmp($packedAddress, $packedSubnet, $wholeBytes) !== 0) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = ~((1 << (8 - $remainingBits)) - 1) & 0xFF;

        return (ord($packedAddress[$wholeBytes]) & $mask) === (ord($packedSubnet[$wholeBytes]) & $mask);
    }
}
