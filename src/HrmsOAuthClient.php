<?php

declare(strict_types=1);

namespace Ljndi\HrmsOAuth;

use GuzzleHttp\Client;
use InvalidArgumentException;
use RuntimeException;

final class HrmsOAuthClient
{
    private Client $http;

    public function __construct(
        private readonly string $issuer,
        private readonly string $clientId,
        private readonly string $redirectUri,
        private readonly ?string $clientSecret = null,
        ?Client $http = null
    ) {
        if (trim($issuer) === '' || trim($clientId) === '' || trim($redirectUri) === '') {
            throw new InvalidArgumentException('issuer, clientId and redirectUri are required.');
        }

        if (filter_var($issuer, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('issuer must be a valid absolute URL.');
        }

        if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('redirectUri must be a valid absolute URL.');
        }

        $this->http = $http ?? new Client([
            'timeout' => 20,
            'connect_timeout' => 10,
            'http_errors' => false,
        ]);
    }

    /**
     * Build an OAuth authorization request using PKCE S256.
     *
     * @param array<int, string> $scopes
     * @return array{url:string,state:string,code_verifier:string,code_challenge:string}
     */
    public function authorizationRequest(
        array $scopes = ['openid', 'profile', 'email', 'employment:read', 'roles:read'],
        ?string $state = null,
        ?string $nonce = null
    ): array {
        $state ??= $this->randomUrlSafe(32);
        $codeVerifier = $this->randomUrlSafe(64);
        $codeChallenge = rtrim(
            strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'),
            '='
        );

        $parameters = [
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'scope' => implode(' ', array_values(array_unique($scopes))),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        if ($nonce !== null && trim($nonce) !== '') {
            $parameters['nonce'] = $nonce;
        }

        return [
            'url' => $this->endpoint('/oauth/authorize').'?'.http_build_query($parameters),
            'state' => $state,
            'code_verifier' => $codeVerifier,
            'code_challenge' => $codeChallenge,
        ];
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ]);
    }

    /** @return array<string, mixed> */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest([
            'grant_type' => 'refresh_token',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'refresh_token' => $refreshToken,
        ]);
    }

    /** @return array<string, mixed> */
    public function userinfo(string $accessToken): array
    {
        $response = $this->http->get($this->endpoint('/oauth/userinfo'), [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);

        return $this->decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    public function revoke(string $token): void
    {
        $response = $this->http->post($this->endpoint('/oauth/revoke'), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => array_filter([
                'token' => $token,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
        ]);

        $this->decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    /** @return array<string, mixed> */
    public function discovery(): array
    {
        $response = $this->http->get(
            $this->endpoint('/.well-known/oauth-authorization-server'),
            ['headers' => ['Accept' => 'application/json']]
        );

        return $this->decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function tokenRequest(array $parameters): array
    {
        $parameters = array_filter(
            $parameters,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        );

        $response = $this->http->post($this->endpoint('/oauth/token'), [
            'headers' => ['Accept' => 'application/json'],
            'form_params' => $parameters,
        ]);

        return $this->decodeResponse($response->getStatusCode(), (string) $response->getBody());
    }

    /** @return array<string, mixed> */
    private function decodeResponse(int $status, string $body): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The HRMS identity service returned an invalid JSON response.');
        }

        if ($status < 200 || $status >= 300) {
            $message = $decoded['error_description']
                ?? $decoded['error']['message']
                ?? $decoded['message']
                ?? 'The HRMS identity request failed.';

            throw new RuntimeException((string) $message, $status);
        }

        return $decoded;
    }

    private function endpoint(string $path): string
    {
        return rtrim($this->issuer, '/').'/'.ltrim($path, '/');
    }

    private function randomUrlSafe(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
