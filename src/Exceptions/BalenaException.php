<?php

declare(strict_types=1);

namespace NetOs\Balena\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base class for every error raised by the balena API.
 *
 * Carries enough context to debug a failed call without re-running it: the
 * HTTP status, the decoded response body and the URL that produced it.
 */
class BalenaException extends RuntimeException
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly array $body = [],
        public readonly ?string $url = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
