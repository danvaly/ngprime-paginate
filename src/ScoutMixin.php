<?php

namespace Danvaly\PrimeDatasource;

class ScoutMixin
{
    public function toDatasource($perPage = null, $pageName = 'page', $page = null)
    {
        return function ($perPage = null, $pageName = 'page', $page = null) {
            // Just defer to the Scout Builder for DX purposes.
            return $this->paginate($perPage, $pageName, $page);
        };
    }
}
