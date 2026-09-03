<?php

declare(strict_types=1);

namespace NetOs\Balena\Enums;

/**
 * The taggable balena resources.
 *
 * Tags share a key/value shape across all three, so one TagResource covers
 * them the same way VariableResource covers the variable resources.
 */
enum TagKind: string
{
    case Device = 'device_tag';
    case Fleet = 'application_tag';
    case Release = 'release_tag';

    public function resource(): string
    {
        return $this->value;
    }

    /**
     * The field linking a tag back to whatever owns it.
     */
    public function ownerField(): string
    {
        return match ($this) {
            self::Device => 'device',
            self::Fleet => 'application',
            self::Release => 'release',
        };
    }
}
