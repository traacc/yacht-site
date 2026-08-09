<?php

declare(strict_types=1);

namespace App\Services\WorldNews;

/**
 * Нормализует URL источника для устойчивой дедупликации материалов.
 */
final class UrlCanonicalizer
{
    private const TRACKING_PARAMETERS = [
        'fbclid',
        'gclid',
        'yclid',
        '_openstat',
    ];

    public function canonicalize(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        if ($host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $isDefaultPort = ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);

        $authority = $host;
        if ($port !== null && ! $isDefaultPort) {
            $authority .= ":{$port}";
        }

        $path = (string) ($parts['path'] ?? '');
        $path = $path === '' ? '/' : $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        $query = $this->canonicalQuery((string) ($parts['query'] ?? ''));

        return "{$scheme}://{$authority}{$path}".($query !== '' ? "?{$query}" : '');
    }

    public function fingerprint(string $url): ?string
    {
        $canonical = $this->canonicalize($url);

        return $canonical === null ? null : hash('sha256', $canonical);
    }

    private function canonicalQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        parse_str($query, $parameters);

        foreach (array_keys($parameters) as $key) {
            $normalizedKey = strtolower((string) $key);

            if (str_starts_with($normalizedKey, 'utm_')
                || in_array($normalizedKey, self::TRACKING_PARAMETERS, true)) {
                unset($parameters[$key]);
            }
        }

        $this->sortRecursively($parameters);

        return http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @param array<array-key, mixed> $values */
    private function sortRecursively(array &$values): void
    {
        ksort($values);

        foreach ($values as &$value) {
            if (is_array($value)) {
                $this->sortRecursively($value);
            }
        }
    }
}
