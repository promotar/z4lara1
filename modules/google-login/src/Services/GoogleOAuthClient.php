<?php

namespace Modules\GoogleLogin\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;

class GoogleOAuthClient
{
    private const AUTHORIZATION_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CERTIFICATES_URL = 'https://www.googleapis.com/oauth2/v1/certs';

    public function __construct(private readonly GoogleLoginSettings $settings)
    {
    }

    public function authorizationUrl(Request $request): string
    {
        $this->ensureAvailable();

        $state = Str::random(64);
        $nonce = Str::random(64);
        $request->session()->put([
            'google_login.oauth_state' => $state,
            'google_login.oauth_nonce' => $nonce,
            'google_login.oauth_started_at' => now()->timestamp,
        ]);

        $parameters = [
            'client_id' => $this->settings->clientId(),
            'redirect_uri' => route('google-login.auth.callback'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'nonce' => $nonce,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        if ($domain = $this->settings->hostedDomain()) {
            $parameters['hd'] = $domain;
        }

        return self::AUTHORIZATION_URL.'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array{sub: string, email: string, name: string, picture: ?string} */
    public function verifiedProfile(Request $request): array
    {
        $this->ensureAvailable();

        if ($request->filled('error')) {
            $message = $request->string('error')->toString() === 'access_denied'
                ? 'Google sign-in was cancelled.'
                : 'Google could not complete the sign-in request.';
            throw new GoogleLoginException($message);
        }

        $expectedState = (string) $request->session()->pull('google_login.oauth_state', '');
        $expectedNonce = (string) $request->session()->pull('google_login.oauth_nonce', '');
        $startedAt = (int) $request->session()->pull('google_login.oauth_started_at', 0);
        $receivedState = (string) $request->query('state', '');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            throw new GoogleLoginException('The Google sign-in session is invalid or expired. Please try again.');
        }

        if ($startedAt < now()->subMinutes(10)->timestamp || $expectedNonce === '') {
            throw new GoogleLoginException('The Google sign-in session expired. Please try again.');
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '' || strlen($code) > 4096) {
            throw new GoogleLoginException('Google did not return a valid authorization code.');
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => $this->settings->clientId(),
            'client_secret' => $this->settings->clientSecret(),
            'redirect_uri' => route('google-login.auth.callback'),
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw new GoogleLoginException('Google could not verify this sign-in. Please try again.');
        }

        $idToken = trim((string) $response->json('id_token', ''));
        if ($idToken === '') {
            throw new GoogleLoginException('Google did not return a valid identity token.');
        }

        $claims = $this->verifyIdToken($idToken, $expectedNonce);

        return [
            'sub' => (string) $claims['sub'],
            'email' => strtolower(trim((string) $claims['email'])),
            'name' => trim((string) ($claims['name'] ?? '')) ?: Str::before((string) $claims['email'], '@'),
            'picture' => $this->safePictureUrl($claims['picture'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function verifyIdToken(string $token, string $expectedNonce): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new GoogleLoginException('Google returned a malformed identity token.');
        }

        try {
            $header = json_decode($this->base64UrlDecode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
            $claims = json_decode($this->base64UrlDecode($parts[1]), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new GoogleLoginException('Google returned an unreadable identity token.');
        }

        if (! is_array($header) || ! is_array($claims) || ($header['alg'] ?? null) !== 'RS256') {
            throw new GoogleLoginException('Google returned an unsupported identity token.');
        }

        $keyId = trim((string) ($header['kid'] ?? ''));
        $certificate = $this->certificate($keyId);
        $signature = $this->base64UrlDecode($parts[2]);

        if (! function_exists('openssl_verify') || openssl_verify($parts[0].'.'.$parts[1], $signature, $certificate, OPENSSL_ALGO_SHA256) !== 1) {
            throw new GoogleLoginException('The Google identity signature could not be verified.');
        }

        $issuer = (string) ($claims['iss'] ?? '');
        $audiences = is_array($claims['aud'] ?? null) ? $claims['aud'] : [($claims['aud'] ?? '')];
        $clientId = (string) $this->settings->clientId();
        $now = now()->timestamp;

        if (! in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true)) {
            throw new GoogleLoginException('The Google identity issuer is invalid.');
        }

        if (! in_array($clientId, $audiences, true)) {
            throw new GoogleLoginException('This Google identity was issued for another application.');
        }

        if (count($audiences) > 1 && (string) ($claims['azp'] ?? '') !== $clientId) {
            throw new GoogleLoginException('This Google identity is not authorized for this application.');
        }

        if ((int) ($claims['exp'] ?? 0) < $now - 60 || (int) ($claims['iat'] ?? 0) > $now + 60) {
            throw new GoogleLoginException('The Google identity token expired or is not active yet.');
        }

        if (! hash_equals($expectedNonce, (string) ($claims['nonce'] ?? ''))) {
            throw new GoogleLoginException('The Google identity nonce is invalid. Please try again.');
        }

        if (trim((string) ($claims['sub'] ?? '')) === '' || trim((string) ($claims['email'] ?? '')) === '') {
            throw new GoogleLoginException('Google did not provide the required account identity.');
        }

        if (! filter_var($claims['email'], FILTER_VALIDATE_EMAIL) || ! $this->isTrue($claims['email_verified'] ?? false)) {
            throw new GoogleLoginException('Google did not provide a verified email address.');
        }

        if (($domain = $this->settings->hostedDomain()) !== null && strtolower((string) ($claims['hd'] ?? '')) !== $domain) {
            throw new GoogleLoginException('This Google account does not belong to the allowed organization.');
        }

        return $claims;
    }

    private function certificate(string $keyId): string
    {
        if ($keyId === '') {
            throw new GoogleLoginException('The Google identity signing key is missing.');
        }

        $certificates = $this->certificates();
        if (! isset($certificates[$keyId])) {
            Cache::forget('google-login.oauth-certificates');
            $certificates = $this->certificates();
        }

        $certificate = $certificates[$keyId] ?? null;
        if (! is_string($certificate) || ! str_contains($certificate, 'BEGIN CERTIFICATE')) {
            throw new GoogleLoginException('The Google identity signing key is unavailable. Please try again.');
        }

        return $certificate;
    }

    /** @return array<string, string> */
    private function certificates(): array
    {
        return Cache::remember('google-login.oauth-certificates', now()->addMinutes(55), function (): array {
            $response = Http::acceptJson()->timeout(10)->get(self::CERTIFICATES_URL);
            if (! $response->successful() || ! is_array($response->json())) {
                throw new GoogleLoginException('Google identity verification is temporarily unavailable.');
            }

            return collect($response->json())
                ->filter(fn ($certificate, $key): bool => is_string($key) && is_string($certificate))
                ->all();
        });
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        if ($decoded === false) {
            throw new GoogleLoginException('Google returned an invalid identity token encoding.');
        }

        return $decoded;
    }

    private function safePictureUrl(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) && str_starts_with(strtolower($value), 'https://')
            ? $value
            : null;
    }

    private function isTrue(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    private function ensureAvailable(): void
    {
        if (! $this->settings->available()) {
            throw new GoogleLoginException('Google Login is currently unavailable. Use email and password instead.');
        }
    }
}
