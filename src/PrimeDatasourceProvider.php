<?php
namespace Danvaly\PrimeDatasource;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class PrimeDatasourceProvider extends ServiceProvider
{
    public function boot()
    {
        Builder::mixin(new BuilderMixin());
        Relation::mixin(new RelationMixin());

        if (class_exists(\Laravel\Scout\Builder::class)) {
            \Laravel\Scout\Builder::mixin(new ScoutMixin());
        }
    }
}
