<?php

declare(strict_types=1);

namespace NetOs\Balena\Http;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use NetOs\Balena\Exceptions\AuthenticationException;
use NetOs\Balena\Exceptions\AuthorizationException;
use NetOs\Balena\Exceptions\BalenaException;
use NetOs\Balena\Exceptions\RateLimitException;
use NetOs\Balena\Exceptions\ResourceNotFoundException;
use NetOs\Balena\Exceptions\ServerException;
use NetOs\Balena\Exceptions\ValidationException;
use Throwable;

/**
 * The single point of contact with the balena API.
 *
 * Two entry points exist because balena versions only part of its surface:
 * resource endpoints live under the version prefix (/v7/device), while
 * /user/v1/whoami and the /supervisor/* proxy sit at the host root.
 */
class BalenaClient
{
    public function __construct(
        private readonly Factory $http,
        private string $token,
        private string $baseUrl,
        private string $version = 'v7',
        private int $timeout = 15,
        private int $retryTimes = 3,
    ) {}

    /**
     * Use a different token for subsequent calls, e.g. a session token
     * belonging to the logged-in user.
     */
    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    /**
     * Point at a different installation, e.g. self-hosted openBalena.
     */
    public function withBaseUrl(string $baseUrl): static
    {
        $clone = clone $this;
        $clone->baseUrl = $baseUrl;

        return $clone;
    }

    /**
     * Call a versioned Pine resource endpoint.
     *
     * @param  array<string, string|int>  $query
     * @param  array<string, mixed>  $body
     */
    public function pine(string $method, string $resource, array $query = [], array $body = []): PineResponse
    {
        $path = trim($this->version, '/').'/'.ltrim($resource, '/');

        $response = $this->send($method, $path, $query, $body);

        $decoded = $response->json();

        return PineResponse::fromArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * Call an unversioned endpoint at the host root: /user/v1/whoami or the
     * /supervisor/* proxy.
     *
     * @param  array<string, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function call(string $method, string $path, array $payload = []): array
    {
        $response = $this->send($method, ltrim($path, '/'), [], $payload);

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * The raw count returned by an OData $count endpoint, which is a bare
     * integer rather than a JSON document.
     *
     * @param  array<string, string|int>  $query
     */
    public function pineCount(string $resource, array $query = []): int
    {
        $path = trim($this->version, '/').'/'.ltrim($resource, '/');

        return (int) trim($this->send('GET', $path, $query)->body());
    }

    public function version(): string
    {
        return $this->version;
    }

    /**
     * The URL a Pine call would hit, without making it.
     *
     * The balena documentation warns that nothing prevents widespread,
     * irreversible mistakes and advises testing with GET first. Being able to
     * read a request back before running a destructive one is part of that.
     *
     * @param  array<string, string|int>  $query
     */
    public function pineUrl(string $resource, array $query = []): string
    {
        $path = trim($this->version, '/').'/'.ltrim($resource, '/');

        return rtrim($this->baseUrl, '/').'/'.$path.$this->queryString($query);
    }

    /**
     * @param  array<string, string|int>  $query
     * @param  array<string, mixed>  $body
     */
    private function send(string $method, string $path, array $query = [], array $body = []): Response
    {
        $method = strtoupper($method);
        $url = rtrim($this->baseUrl, '/').'/'.$path.$this->queryString($query);

        try {
            $response = $method === 'GET'
                ? $this->request()->get($url)
                : $this->request()->send($method, $url, $body === [] ? [] : ['json' => $body]);
        } catch (ConnectionException $exception) {
            throw new BalenaException(
                "Could not reach the balena API at [{$url}]: {$exception->getMessage()}",
                previous: $exception,
                url: $url,
            );
        }

        if ($response->failed()) {
            throw $this->exceptionFor($response, $url);
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        $request = $this->http
            ->withToken($this->token)
            ->acceptJson()
            ->timeout($this->timeout);

        if ($this->retryTimes > 1) {
            $request = $request->retry(
                $this->retryTimes,
                $this->retryDelay(),
                $this->shouldRetry(),
                throw: false,
            );
        }

        return $request;
    }

    /**
     * balena does not publish its rate limits and returns Retry-After so
     * clients can adapt at runtime, so we wait exactly as long as we are told
     * and fall back to a linear backoff when the header is absent.
     */
    private function retryDelay(): callable
    {
        return function (int $attempt, Throwable $exception): int {
            $retryAfter = $exception instanceof RequestException
                ? $exception->response->header('Retry-After')
                : null;

            if (is_numeric($retryAfter)) {
                return (int) ((float) $retryAfter * 1000);
            }

            return $attempt * 1000;
        };
    }

    /**
     * Retry throttling and server faults only. A 4xx caused by the request
     * itself will not fix itself on a second attempt.
     */
    private function shouldRetry(): callable
    {
        return function (Throwable $exception): bool {
            if (! $exception instanceof RequestException) {
                return $exception instanceof ConnectionException;
            }

            $status = $exception->response->status();

            return $status === 429 || $status >= 500;
        };
    }

    /**
     * Balena parses OData syntax before decoding literal values. Keep option
     * names and expression punctuation literal, while encoding data and spaces.
     * Inputs are unencoded OData expressions (as produced by PineQuery).
     *
     * @param  array<string, string|int>  $query
     */
    private function queryString(array $query): string
    {
        if ($query === []) {
            return '';
        }

        $parts = [];

        foreach ($query as $key => $value) {
            $name = str_replace('%24', '$', rawurlencode($key));
            $encoded = in_array($key, ['$select', '$filter', '$expand', '$orderby'], true)
                ? $this->encodeExpression((string) $value)
                : rawurlencode((string) $value);

            $parts[] = $name.'='.$encoded;
        }

        return '?'.implode('&', $parts);
    }

    /**
     * Preserve grammar only outside single-quoted literals. Doubled apostrophes
     * are OData's escape sequence: splitting on each quote leaves an empty
     * outside segment between them, so literal contents stay encoded.
     *
     * Never decode existing percent sequences: a literal "%26" must remain
     * data, rather than becoming a query separator after decoding at the API.
     */
    private function encodeExpression(string $expression): string
    {
        $segments = explode("'", $expression);

        foreach ($segments as $index => $segment) {
            $segments[$index] = rawurlencode($segment);

            if ($index % 2 === 0) {
                $segments[$index] = strtr($segments[$index], [
                    '%24' => '$',
                    '%2C' => ',',
                    '%28' => '(',
                    '%29' => ')',
                    '%2F' => '/',
                    '%3A' => ':',
                    '%3B' => ';',
                    '%3D' => '=',
                    '%2A' => '*',
                    '%40' => '@',
                ]);
            }
        }

        return implode("'", $segments);
    }

    private function exceptionFor(Response $response, string $url): BalenaException
    {
        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : [];
        $status = $response->status();
        $message = $this->messageFor($response, $body);

        if ($status === 429) {
            $retryAfter = $response->header('Retry-After');

            return new RateLimitException(
                $message,
                $status,
                $body,
                $url,
                is_numeric($retryAfter) ? (int) $retryAfter : null,
            );
        }

        $class = match (true) {
            $status === 401 => AuthenticationException::class,
            $status === 403 => AuthorizationException::class,
            $status === 404 => ResourceNotFoundException::class,
            $status === 400, $status === 422 => ValidationException::class,
            $status >= 500 => ServerException::class,
            default => BalenaException::class,
        };

        return new $class($message, $status, $body, $url);
    }

    /**
     * balena is inconsistent about error shapes: sometimes a JSON object,
     * sometimes plain text. Take whatever is most specific.
     *
     * @param  array<array-key, mixed>  $body
     */
    private function messageFor(Response $response, array $body): string
    {
        foreach (['message', 'error'] as $key) {
            $value = $body[$key] ?? null;

            if (is_string($value) && $value !== '') {
                return $value;
            }

            if (is_array($value) && is_string($value['message'] ?? null) && $value['message'] !== '') {
                return $value['message'];
            }
        }

        $text = trim($response->body());

        if ($text !== '') {
            return Str::limit($text, 500);
        }

        return "The balena API returned HTTP {$response->status()}.";
    }
}
