<?php

namespace App\Services\Proxmox;

use App\Exceptions\ProxmoxRequestException;
use App\Models\Node;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;

/**
 * Thin HTTP client for the Proxmox VE API (api2/json) using API-token auth.
 *
 * Authentication uses the PVE API token header:
 *   Authorization: PVEAPIToken=USER@REALM!TOKENID=SECRET
 *
 * Tokens are created on the Proxmox host with:
 *   pveum user add hypervm@pve
 *   pveum aclmod / -user hypervm@pve -role Administrator
 *   pveum user token add hypervm@pve panel --privsep 0
 */
class ProxmoxClient
{
    private Client $http;

    public function __construct(
        private readonly string $baseUri,
        private readonly string $tokenId,
        private readonly string $tokenSecret,
        private readonly bool $verifyTls = true,
        private readonly int $timeout = 15,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($this->baseUri, '/').'/',
            'timeout' => $this->timeout,
            'verify' => $this->verifyTls,
            'http_errors' => false,
            'headers' => [
                'Authorization' => sprintf('PVEAPIToken=%s=%s', $this->tokenId, $this->tokenSecret),
                'Accept' => 'application/json',
                'User-Agent' => 'HyperVM/1.0 (+https://github.com)',
            ],
        ]);
    }

    public static function forNode(Node $node): self
    {
        return new self(
            baseUri: $node->api_url,
            tokenId: $node->token_id,
            tokenSecret: (string) $node->token_secret,
            verifyTls: $node->verify_tls && config('hypervm.proxmox.verify_tls'),
            timeout: (int) config('hypervm.proxmox.timeout', 15),
        );
    }

    public function get(string $endpoint, array $query = []): mixed
    {
        return $this->request('GET', $endpoint, ['query' => $query]);
    }

    public function post(string $endpoint, array $payload = []): mixed
    {
        return $this->request('POST', $endpoint, ['form_params' => $payload]);
    }

    public function put(string $endpoint, array $payload = []): mixed
    {
        return $this->request('PUT', $endpoint, ['form_params' => $payload]);
    }

    public function delete(string $endpoint, array $query = []): mixed
    {
        return $this->request('DELETE', $endpoint, ['query' => $query]);
    }

    private function request(string $method, string $endpoint, array $options = []): mixed
    {
        $endpoint = ltrim($endpoint, '/');

        try {
            $response = $this->http->request($method, $endpoint, $options);
        } catch (RequestException|GuzzleException $e) {
            throw new ProxmoxRequestException(
                "Unable to reach the Proxmox API: {$e->getMessage()}",
                null,
                $endpoint,
                [],
                $e,
            );
        }

        return $this->decode($response, $endpoint);
    }

    private function decode(ResponseInterface $response, string $endpoint): mixed
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if ($status >= 400) {
            $message = is_array($decoded)
                ? ($decoded['message'] ?? $this->flattenErrors($decoded['errors'] ?? []) ?: $response->getReasonPhrase())
                : ($body !== '' ? $body : $response->getReasonPhrase());

            throw new ProxmoxRequestException(
                sprintf('Proxmox returned %d for %s: %s', $status, $endpoint, trim((string) $message)),
                $status,
                $endpoint,
                is_array($decoded) ? $decoded : [],
            );
        }

        if (! is_array($decoded)) {
            throw new ProxmoxRequestException("Malformed JSON returned by Proxmox for {$endpoint}.", $status, $endpoint);
        }

        return $decoded['data'] ?? null;
    }

    private function flattenErrors(array $errors): string
    {
        return collect($errors)->map(fn ($v, $k) => "{$k}: {$v}")->implode('; ');
    }
}
