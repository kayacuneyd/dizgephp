<?php

declare(strict_types=1);

namespace Dizge\Http;

/**
 * Resolves the real client IP/scheme from X-Forwarded-* headers, but only when the immediate
 * TCP peer (REMOTE_ADDR) is itself an operator-configured trusted proxy. Nothing is trusted by
 * default: an app deployed with no DIZGE_TRUSTED_PROXIES configuration ignores these headers
 * entirely, so a client can never spoof its own IP by sending them directly.
 */
final class TrustedProxy
{
    /** @return list<string> CIDR ranges (e.g. "127.0.0.1/32", "::1/128") from a comma-separated env var. */
    public static function fromEnv(string $variable = 'DIZGE_TRUSTED_PROXIES'): array
    {
        $configured = getenv($variable);
        if ($configured === false || trim($configured) === '') return [];
        return array_values(array_filter(
            array_map('trim', explode(',', $configured)),
            static fn (string $cidr): bool => $cidr !== '',
        ));
    }

    /** @param list<string> $cidrs */
    public static function isTrusted(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if (self::matches($ip, $cidr)) return true;
        }
        return false;
    }

    /**
     * @param list<string> $trustedCidrs
     * Returns the left-most (original client) address from X-Forwarded-For when $remoteAddr is
     * a trusted proxy; otherwise returns $remoteAddr unchanged.
     */
    public static function resolveClientIp(string $remoteAddr, ?string $forwardedFor, array $trustedCidrs): string
    {
        if ($trustedCidrs === [] || $forwardedFor === null || $forwardedFor === '') return $remoteAddr;
        if (!self::isTrusted($remoteAddr, $trustedCidrs)) return $remoteAddr;
        $client = trim(explode(',', $forwardedFor)[0]);
        return filter_var($client, FILTER_VALIDATE_IP) !== false ? $client : $remoteAddr;
    }

    private static function matches(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = str_contains($cidr, '/') ? explode('/', $cidr, 2) : [$cidr, null];
        $ipBinary = @inet_pton($ip);
        $subnetBinary = @inet_pton($subnet);
        if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) return false;
        $totalBits = strlen($ipBinary) * 8;
        $bits = $bits === null ? $totalBits : (int) $bits;
        if ($bits < 0 || $bits > $totalBits) return false;
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) return false;
        if ($remainder === 0) return true;
        $mask = chr((0xFF << (8 - $remainder)) & 0xFF);
        return (substr($ipBinary, $bytes, 1) & $mask) === (substr($subnetBinary, $bytes, 1) & $mask);
    }
}
