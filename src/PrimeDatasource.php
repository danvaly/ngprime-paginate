<?php

namespace Danvaly\PrimeDatasource;

use Closure;
use Illuminate\Database\Query\Expression;

class PrimeDatasource
{
    public function toDatasource($filters = [])
    {
        return $this->paginate('paginate', $filters, function (array $items, $paginator) {
            $datasource = new Datasource($items);
            $datasource->total = $paginator->total();
            $datasource->per_page = $paginator->perPage();
            $datasource->current_page = $paginator->currentPage();
            $datasource->last_page = $paginator->lastPage();
            $datasource->first_item = $paginator->firstItem();
            $datasource->last_item = $paginator->lastItem();
            $datasource->has_more_pages = $paginator->hasMorePages();
            $datasource->next_page_url = $paginator->nextPageUrl();
            $datasource->prev_page_url = $paginator->previousPageUrl();
            $datasource->from = $paginator->firstItem();
            $datasource->to = $paginator->lastItem();
            $datasource->options = $paginator->getOptions();

            return $datasource;
        });
    }

    protected function paginate(string $paginationMethod, $filters = [], Closure $paginatorOutput)
    {
        return function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) use (
            $paginationMethod,
            $paginatorOutput
        ) {
            /** @var \Illuminate\Database\Query\Builder $this */
            $base = $this->getQuery();

            if (isset($filters['sortField']) && isset($filters['sortOrder'])) {
                $base->orderBy($filters['sortField'], $filters['sortOrder']);
            }

            if (isset($filters['multiSortMeta'])) {
                $multiSortMeta = $filters['multiSortMeta'];
                foreach ($multiSortMeta as $sortMeta) {
                    $base->orderBy($sortMeta['field'], $sortMeta['order']);
                }
            }

            if (isset($filters['filters'])) {
                foreach ($filters['filters'] as $filter) {
                    $base->where($filter['field'], $filter['value']);
                }
            }

            if (isset($filters['globalFilter'])) {
                $globalFilter = $filters['globalFilter'];
                $base->search($globalFilter);
            }

            // Havings and groups don't work well with this paradigm, because we are
            // counting on each row of the inner query to return a primary key
            // that we can use. When grouping, that's not always the case.
            if (filled($base->havings) || filled($base->groups)) {
                return $this->{$paginationMethod}($perPage, $columns, $pageName, $page);
            }

            $model = $this->newModelInstance();
            $key = $model->getKeyName();
            $table = $model->getTable();

            try {
                $innerSelectColumns = PrimeDatasource::getInnerSelectColumns($this);
            } catch (QueryIncompatibleWithFastPagination $e) {
                return $this->{$paginationMethod}($perPage, $columns, $pageName, $page);
            }


            // This is the copy of the query that becomes
            // the inner query that selects keys only.
            $paginator = $this->clone()
                // Only select the primary key, we'll get the full
                // records in a second query below.
                ->select($innerSelectColumns)
                // We don't need eager loads for this cloned query, they'll
                // remain on the query that actually gets the records.
                // (withoutEagerLoads not available on Laravel 8.)
                ->setEagerLoads([])
                ->{$paginationMethod}($perPage, ['*'], $pageName, $page);

            // Get the key values from the records on the current page without mutating them.
            $ids = $paginator->getCollection()->map->getRawOriginal($key)->toArray();

            if (in_array($model->getKeyType(), ['int', 'integer'])) {
                $this->query->whereIntegerInRaw("$table.$key", $ids);
            } else {
                $this->query->whereIn("$table.$key", $ids);
            }

            // The $paginator is full of records that are primary keys only. Here,
            // we create a new paginator with all of the *stats* from the index-
            // only paginator, but the *items* from the outer query.
            $items = $this->simplePaginate($perPage, $columns, $pageName, 1)->items();

            return Closure::fromCallable($paginatorOutput)->call($this, $items, $paginator);
        };
    }

    /**
     * @param $builder
     * @return array
     *
     * @throws QueryIncompatibleWithFastPagination
     */
    public static function getInnerSelectColumns($builder)
    {
        $base = $builder->getQuery();
        $model = $builder->newModelInstance();
        $key = $model->getKeyName();
        $table = $model->getTable();

        // Collect all of the `orders` off of the base query and pluck
        // the column out. Based on what orders are present, we may
        // have to include certain columns in the inner query.
        $orders = collect($base->orders)
            ->pluck('column')
            ->map(function ($column) use ($base) {
                // Use the grammar to wrap them, so that our `str_contains`
                // (further down) doesn't return any false positives.
                return $base->grammar->wrap($column);
            });

        return collect($base->columns)
            ->filter(function ($column) use ($orders, $base) {
                $column = $column instanceof Expression ? $column->getValue($base->grammar) : $base->grammar->wrap($column);
                foreach ($orders as $order) {
                    // If we're ordering by this column, then we need to
                    // keep it in the inner query.
                    if (str_contains($column, "as $order")) {
                        return true;
                    }
                }

                // Otherwise we don't.
                return false;
            })
            ->each(function ($column) use ($base) {
                if ($column instanceof Expression) {
                    $column = $column->getValue($base->grammar);
                }

                if (str_contains($column, '?')) {
                    throw new QueryIncompatibleWithFastPagination;
                }
            })
            ->prepend("$table.$key")
            ->unique()
            ->values()
            ->toArray();
    }
}
