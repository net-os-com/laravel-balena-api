<?php

declare(strict_types=1);

namespace NetOs\Balena\Http;

/**
 * The decoded body of a Pine (OData) response.
 *
 * Pine wraps collections in a top-level "d" key. The balena reference does not
 * show a response body anywhere, so this class accepts both the wrapped and
 * unwrapped shapes rather than betting on one. If the envelope ever changes,
 * this is the only file that needs to know.
 */
final readonly class PineResponse
{
    /**
     * @param  array<array-key, mixed>  $payload
     */
    private function __construct(private array $payload) {}

    /**
     * @param  array<array-key, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self($payload);
    }

    /**
     * The rows this response carries, unwrapped from the envelope.
     *
     * @return array<int, array<string, mixed>>
     */
    public function records(): array
    {
        $records = array_key_exists('d', $this->payload) && is_array($this->payload['d'])
            ? $this->payload['d']
            : $this->payload;

        if ($records === []) {
            return [];
        }

        // A single resource lookup returns one object rather than a list.
        if (! array_is_list($records)) {
            return [$records];
        }

        /** @var array<int, array<string, mixed>> $records */
        return array_values(array_filter($records, 'is_array'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        return $this->records()[0] ?? null;
    }

    /**
     * The body exactly as balena returned it, envelope included.
     *
     * @return array<array-key, mixed>
     */
    public function raw(): array
    {
        return $this->payload;
    }

    public function isEmpty(): bool
    {
        return $this->records() === [];
    }
}
