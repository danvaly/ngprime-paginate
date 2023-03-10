<?php

namespace Danvaly\PrimeDatasource;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class RelationMixin
{
    public function toDatasource()
    {
        return function ($perPage = null, $columns = ['*'], $pageName = 'page', $page = null) {
            /** @var \Illuminate\Database\Eloquent\Relations\Relation $this */
            if ($this instanceof HasManyThrough || $this instanceof BelongsToMany) {
                $this->query->addSelect($this->shouldSelect($columns));
            }

            return tap($this->query->toDatasource($perPage, $columns, $pageName, $page), function ($paginator) {
                if ($this instanceof BelongsToMany) {
                    $this->hydratePivotRelation($paginator->items());
                }
            });
        };
    }
}
