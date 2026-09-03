<?php

declare(strict_types=1);

namespace NetOs\Balena\Data;

/**
 * The identity behind the current token, as returned by /user/v1/whoami.
 */
final readonly class User
{
    /**
     * @param  array<string, mixed>  $raw
     */
    private function __construct(
        public ?int $id,
        public ?string $username,
        public ?string $email,
        public array $raw = [],
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            id: Cast::int($attributes['id'] ?? null),
            username: Cast::string($attributes['username'] ?? null),
            email: Cast::string($attributes['email'] ?? null),
            raw: $attributes,
        );
    }
}
