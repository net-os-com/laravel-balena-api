<?php

declare(strict_types=1);

namespace NetOs\Balena\Query;

use Closure;
use Generator;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Http\PineResponse;
use NetOs\Balena\Query\Filter\Expression;
use NetOs\Balena\Query\Filter\Operator;

/**
 * A fluent builder over balena's Pine (OData) query language.
 *
 * Immutable: every builder method returns a clone, so a partially configured
 * query can be shared and branched without surprises.
 */
class PineQuery
{
    /** @var array<int, string> */
    private array $selects = [];

    /** @var array<string, self|null> */
    private array $expands = [];

    /** @var array<int, Expression> */
    private array $filters = [];

    /** @var array<int, string> */
    private array $orders = [];

    private ?int $top = null;

    private ?int $skip = null;

    /**
     * @param  class-string|null  $dto  Hydration target; null returns raw arrays.
     * @param  string  $fieldPrefix  Alias prefix applied to field names inside a relation traversal.
     * @param  int  $depth  Nesting level, used to pick a unique traversal alias.
     */
    public function __construct(
        private readonly BalenaClient $client,
        private readonly string $resource,
        private readonly ?string $dto = null,
        private readonly string $fieldPrefix = '',
        private readonly int $depth = 0,
    ) {}

    // ---------------------------------------------------------------- shaping

    public function select(string ...$fields): static
    {
        $clone = clone $this;
        $clone->selects = array_values(array_unique([...$clone->selects, ...$fields]));

        return $clone;
    }

    /**
     * Include a linked resource. Pass a callback to shape the expansion:
     *
     *   ->expand('belongs_to__application', fn ($q) => $q->select('app_name'))
     */
    public function expand(string $relation, ?Closure $callback = null): static
    {
        $clone = clone $this;
        $clone->expands[$relation] = $callback === null
            ? null
            : $this->resolveSub(new self($this->client, $relation), $callback);

        return $clone;
    }

    /**
     * Run a sub-query callback and keep whatever it produced.
     *
     * Builder methods return clones, so `fn ($q) => $q->where(...)` yields a
     * new instance rather than mutating the one passed in. Callbacks that
     * return nothing are still honoured for the direct-mutation case.
     */
    private function resolveSub(self $builder, Closure $callback): self
    {
        $result = $callback($builder);

        return $result instanceof self ? $result : $builder;
    }

    public function orderBy(string $field, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);

        if (! in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException("Order direction must be asc or desc, got [{$direction}].");
        }

        $clone = clone $this;
        $clone->orders[] = $this->prefix($field).' '.$direction;

        return $clone;
    }

    public function orderByDesc(string $field): static
    {
        return $this->orderBy($field, 'desc');
    }

    public function take(int $count): static
    {
        $clone = clone $this;
        $clone->top = $count;

        return $clone;
    }

    public function limit(int $count): static
    {
        return $this->take($count);
    }

    public function skip(int $count): static
    {
        $clone = clone $this;
        $clone->skip = $count;

        return $clone;
    }

    public function offset(int $count): static
    {
        return $this->skip($count);
    }

    // --------------------------------------------------------------- filtering

    /**
     * where('is_online', true)
     * where('device_name', Operator::Contains, 'sensor')
     * where(fn ($q) => $q->where(...)->orWhere(...))   // parenthesised group
     */
    public function where(string|Closure $field, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if ($field instanceof Closure) {
            return $this->whereGroup($field, $boolean);
        }

        [$operator, $value] = $this->normaliseOperator($operator, $value, func_num_args() >= 3);

        return $this->addExpression(
            Expression::comparison($this->prefix($field), $operator, $value, $boolean)
        );
    }

    public function orWhere(string|Closure $field, mixed $operator = null, mixed $value = null): static
    {
        if ($field instanceof Closure) {
            return $this->whereGroup($field, 'or');
        }

        [$operator, $value] = $this->normaliseOperator($operator, $value, func_num_args() >= 3);

        return $this->addExpression(
            Expression::comparison($this->prefix($field), $operator, $value, 'or')
        );
    }

    /**
     * @return array{0: Operator, 1: mixed}
     */
    private function normaliseOperator(mixed $operator, mixed $value, bool $hasOperator): array
    {
        if (! $hasOperator) {
            return [Operator::Equals, $operator];
        }

        return [
            $operator instanceof Operator ? $operator : Operator::fromString((string) $operator),
            $value,
        ];
    }

    /**
     * An already-compiled OData fragment, for anything this builder cannot express.
     */
    public function whereRaw(string $clause, string $boolean = 'and'): static
    {
        return $this->addExpression(Expression::raw($clause, $boolean));
    }

    /**
     * @param  array<int, mixed>  $values
     */
    public function whereIn(string $field, array $values, string $boolean = 'and'): static
    {
        if ($values === []) {
            // An empty set matches nothing; say so rather than emitting no filter.
            return $this->whereRaw('1 eq 0', $boolean);
        }

        return $this->whereGroup(function (self $query) use ($field, $values): void {
            foreach ($values as $index => $value) {
                $query->filters[] = Expression::comparison(
                    $this->prefix($field),
                    Operator::Equals,
                    $value,
                    $index === 0 ? 'and' : 'or',
                );
            }
        }, $boolean);
    }

    public function whereNull(string $field, string $boolean = 'and'): static
    {
        return $this->where($field, Operator::Equals, null, $boolean);
    }

    public function whereNotNull(string $field, string $boolean = 'and'): static
    {
        return $this->where($field, Operator::NotEquals, null, $boolean);
    }

    /**
     * Filter on a linked resource, e.g.
     *
     *   ->whereRelation('belongs_to__application', fn ($q) => $q->where('slug', 'org/fleet'))
     *
     * compiles to belongs_to__application/any(a:a/slug eq 'org/fleet').
     */
    public function whereRelation(string $relation, Closure $callback, string $boolean = 'and'): static
    {
        $alias = chr(ord('a') + $this->depth);

        $related = new self(
            client: $this->client,
            resource: $relation,
            dto: null,
            fieldPrefix: $alias.'/',
            depth: $this->depth + 1,
        );

        $related = $this->resolveSub($related, $callback);

        if ($related->filters === []) {
            throw new InvalidArgumentException(
                "whereRelation('{$relation}') produced no conditions. Return the query from the "
                ."callback, for example fn (\$q) => \$q->where('slug', 'org/fleet')."
            );
        }

        return $this->whereRaw(
            $this->prefix($relation).'/any('.$alias.':'.Expression::compile($related->filters).')',
            $boolean,
        );
    }

    private function whereGroup(Closure $callback, string $boolean): static
    {
        $group = $this->resolveSub(
            new self($this->client, $this->resource, null, $this->fieldPrefix, $this->depth),
            $callback,
        );

        $expression = Expression::group($group->filters, $boolean);

        if ($expression === null) {
            throw new InvalidArgumentException(
                'A where() group produced no conditions. Return the query from the callback, '
                ."for example fn (\$q) => \$q->where('is_online', true)."
            );
        }

        return $this->addExpression($expression);
    }

    private function addExpression(Expression $expression): static
    {
        $clone = clone $this;
        $clone->filters[] = $expression;

        return $clone;
    }

    private function prefix(string $field): string
    {
        return $this->fieldPrefix.$field;
    }

    // --------------------------------------------------------------- compiling

    /**
     * The OData parameters this query compiles to.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        $query = [];

        if ($this->selects !== []) {
            $query['$select'] = implode(',', $this->selects);
        }

        if ($this->filters !== []) {
            $query['$filter'] = Expression::compile($this->filters);
        }

        if ($this->expands !== []) {
            $query['$expand'] = $this->compileExpands();
        }

        if ($this->orders !== []) {
            $query['$orderby'] = implode(',', $this->orders);
        }

        if ($this->top !== null) {
            $query['$top'] = $this->top;
        }

        if ($this->skip !== null) {
            $query['$skip'] = $this->skip;
        }

        return $query;
    }

    private function compileExpands(): string
    {
        $compiled = [];

        foreach ($this->expands as $relation => $query) {
            if ($query === null) {
                $compiled[] = $relation;

                continue;
            }

            $options = [];

            foreach ($query->toQuery() as $key => $value) {
                $options[] = "{$key}={$value}";
            }

            $compiled[] = $options === []
                ? $relation
                : $relation.'('.implode(';', $options).')';
        }

        return implode(',', $compiled);
    }

    /**
     * The URL this query would request, without requesting it.
     */
    public function toUrl(): string
    {
        return $this->client->pineUrl($this->resource, $this->toQuery());
    }

    // --------------------------------------------------------------- executing

    /**
     * @return Collection<int, mixed>
     */
    public function get(): Collection
    {
        return $this->hydrateAll($this->response()->records());
    }

    public function first(): mixed
    {
        $record = $this->take(1)->response()->first();

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * Fetch one resource by id, UUID, or composite key.
     *
     * @param  int|string|array<string, mixed>  $key
     */
    public function find(int|string|array $key): mixed
    {
        $query = array_diff_key($this->toQuery(), array_flip(['$filter', '$top', '$skip', '$orderby']));

        $record = $this->client
            ->pine('GET', ResourceKey::make($key)->appliedTo($this->resource), $query)
            ->first();

        return $record === null ? null : $this->hydrate($record);
    }

    /**
     * Rows as decoded arrays, skipping DTO hydration.
     *
     * @return array<int, array<string, mixed>>
     */
    public function raw(): array
    {
        return $this->response()->records();
    }

    public function response(): PineResponse
    {
        return $this->client->pine('GET', $this->resource, $this->toQuery());
    }

    public function count(): int
    {
        $query = array_intersect_key($this->toQuery(), array_flip(['$filter']));

        return $this->client->pineCount($this->resource.'/$count', $query);
    }

    public function exists(): bool
    {
        return $this->take(1)->select('id')->response()->isEmpty() === false;
    }

    /**
     * Walk the full result set, paging with $top/$skip.
     *
     * @return Generator<int, mixed>
     */
    public function lazy(int $chunkSize = 100): Generator
    {
        $offset = $this->skip ?? 0;

        while (true) {
            $records = $this->take($chunkSize)->skip($offset)->response()->records();

            foreach ($records as $record) {
                yield $this->hydrate($record);
            }

            if (count($records) < $chunkSize) {
                return;
            }

            $offset += $chunkSize;
        }
    }

    /**
     * Page through the full result set, handing each page to the callback.
     * Return false from the callback to stop early.
     */
    public function chunk(int $size, Closure $callback): void
    {
        $offset = $this->skip ?? 0;

        while (true) {
            $records = $this->take($size)->skip($offset)->response()->records();

            if ($records === []) {
                return;
            }

            if ($callback($this->hydrateAll($records)) === false) {
                return;
            }

            if (count($records) < $size) {
                return;
            }

            $offset += $size;
        }
    }

    /**
     * Update every row matching this query's filter.
     *
     * Deliberately named so it can never be reached by accident: a filtered
     * PATCH against a collection rewrites every match, and the balena docs
     * warn there is nothing to stop that. A filter is required.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function patchMany(array $attributes): void
    {
        if ($this->filters === []) {
            throw new InvalidArgumentException(
                'patchMany() requires a filter; without one it would update every row of '
                ."[{$this->resource}]."
            );
        }

        if ($attributes === []) {
            throw new InvalidArgumentException('patchMany() requires at least one attribute to set.');
        }

        $this->client->pine(
            'PATCH',
            $this->resource,
            array_intersect_key($this->toQuery(), array_flip(['$filter'])),
            $attributes,
        );
    }

    /**
     * Delete every row matching this query's filter. Same guard as patchMany().
     */
    public function deleteMany(): void
    {
        if ($this->filters === []) {
            throw new InvalidArgumentException(
                'deleteMany() requires a filter; without one it would delete every row of '
                ."[{$this->resource}]."
            );
        }

        $this->client->pine(
            'DELETE',
            $this->resource,
            array_intersect_key($this->toQuery(), array_flip(['$filter'])),
        );
    }

    // --------------------------------------------------------------- hydration

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return Collection<int, mixed>
     */
    private function hydrateAll(array $records): Collection
    {
        return new Collection(array_map(fn (array $record): mixed => $this->hydrate($record), $records));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function hydrate(array $record): mixed
    {
        if ($this->dto === null) {
            return $record;
        }

        /** @var callable(array<string, mixed>): mixed $factory */
        $factory = [$this->dto, 'fromArray'];

        return $factory($record);
    }
}
