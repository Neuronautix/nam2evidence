<?php

declare(strict_types=1);

namespace App\Service\NamCore;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Thin curl client for the optional Python validator sidecar (pyshacl + pyarrow).
 *
 * Every method degrades gracefully: if the sidecar is not configured (env
 * VALIDATOR_URL empty) or is unreachable, it returns null and the caller falls
 * back to the PHP-native path (SemanticValidator / CSV). This keeps the stack
 * usable with or without the sidecar container — no hard dependency on it.
 */
final class ValidatorSidecarClient
{
    public function __construct(
        #[Autowire('%env(default::VALIDATOR_URL)%')]
        private readonly string $baseUrl = '',
    ) {}

    public function isConfigured(): bool
    {
        return trim($this->baseUrl) !== '';
    }

    /**
     * @param array<string,mixed> $jsonld
     * @return array<string,mixed>|null normalized SHACL report, or null on failure
     */
    public function validate(array $jsonld, ?string $shapesTtl = null): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }
        $body = array_filter(['jsonld' => $jsonld, 'shapes_ttl' => $shapesTtl], static fn($v) => $v !== null);
        $raw = $this->post('/validate', json_encode($body, JSON_UNESCAPED_SLASHES) ?: '{}', 15);
        if ($raw === null) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, list<array<string,mixed>>> $tables
     * @return string|null raw ZIP bytes of *.parquet, or null on failure
     */
    public function parquet(array $tables): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }
        return $this->post('/parquet', json_encode(['tables' => $tables], JSON_UNESCAPED_SLASHES) ?: '{}', 30, binary: true);
    }

    private function post(string $path, string $json, int $timeout, bool $binary = false): ?string
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . $path);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $result = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $status >= 300) {
            return null;
        }
        return is_string($result) ? $result : null;
    }
}
