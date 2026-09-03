<?php

declare(strict_types=1);

namespace NetOs\Balena\Query\Filter;

use InvalidArgumentException;

/**
 * Comparison operators supported inside an OData $filter.
 *
 * Relation traversal is deliberately absent: that is PineQuery::whereRelation()'s
 * job, which emits the documented `rel/any(a:a/field eq 'x')` form.
 */
enum Operator: string
{
    case Equals = 'eq';
    case NotEquals = 'ne';
    case GreaterThan = 'gt';
    case GreaterThanOrEqual = 'ge';
    case LessThan = 'lt';
    case LessThanOrEqual = 'le';

    /**
     * Substring match. Rendered as a function call rather than an infix
     * operator: contains(field,'value').
     *
     * NOTE: unverified against a live balena instance. Older OData revisions
     * spell this substringof('value',field).
     */
    case Contains = 'contains';

    public function isFunction(): bool
    {
        return $this === self::Contains;
    }

    public static function fromString(string $operator): self
    {
        return self::tryFrom(strtolower($operator))
            ?? match ($operator) {
                '=', '==' => self::Equals,
                '!=', '<>' => self::NotEquals,
                '>' => self::GreaterThan,
                '>=' => self::GreaterThanOrEqual,
                '<' => self::LessThan,
                '<=' => self::LessThanOrEqual,
                default => throw new InvalidArgumentException(
                    "Unsupported filter operator [{$operator}]."
                ),
            };
    }
}
