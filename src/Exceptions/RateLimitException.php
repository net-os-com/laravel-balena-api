<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

use Throwable;

/**
 * The balena API is throttling this token (429).
 *
 * balena deliberately does not publish its rate limits, and instead returns a
 * Retry-After header so clients can adapt at runtime. That value is surfaced
 * here so callers can reschedule rather than guess.
 */
class RateLimitException extends BalenaException
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        string $message,
        int $status = 429,
        array $body = [],
        ?string $url = null,
        private readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $body, $url, $previous);
    }

    /**
     * Seconds to wait before retrying, when the API told us.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
