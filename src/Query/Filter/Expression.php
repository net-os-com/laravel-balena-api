<?php

declare(strict_types=1);

namespace NetOs\Balena\Query\Filter;

/**
 * A single compiled clause within an OData $filter, plus the boolean that
 * joins it to whatever preceded it.
 *
 * Clauses are compiled eagerly so the builder never has to hold half-built
 * state, and so a group is just another clause.
 */
final readonly class Expression
{
    private function __construct(
        public string $clause,
        public string $boolean,
    ) {}

    public static function comparison(
        string $field,
        Operator $operator,
        mixed $value,
        string $boolean = 'and',
    ): self {
        $literal = ValueFormatter::format($value);

        $clause = $operator->isFunction()
            ? "{$operator->value}({$field},{$literal})"
            : "{$field} {$operator->value} {$literal}";

        return new self($clause, $boolean);
    }

    /**
     * An already-compiled fragment, e.g. a relation traversal.
     */
    public static function raw(string $clause, string $boolean = 'and'): self
    {
        return new self($clause, $boolean);
    }

    /**
     * Wrap several clauses in parentheses so their booleans cannot leak into
     * the surrounding filter.
     *
     * @param  array<int, self>  $expressions
     */
    public static function group(array $expressions, string $boolean = 'and'): ?self
    {
        if ($expressions === []) {
            return null;
        }

        return new self('('.self::compile($expressions).')', $boolean);
    }

    /**
     * Join clauses into a filter string. The first clause's boolean is dropped,
     * since nothing precedes it.
     *
     * @param  array<int, self>  $expressions
     */
    public static function compile(array $expressions): string
    {
        $expressions = array_values($expressions);
        $filter = '';

        foreach ($expressions as $index => $expression) {
            $filter .= $index === 0
                ? $expression->clause
                : " {$expression->boolean} {$expression->clause}";
        }

        return $filter;
    }
}
