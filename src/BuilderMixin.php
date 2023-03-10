<?php
namespace Danvaly\PrimeDatasource;

class BuilderMixin
{

    public function toDatasource()
    {
        return (new PrimeDatasource())->toDatasource();
    }
}
