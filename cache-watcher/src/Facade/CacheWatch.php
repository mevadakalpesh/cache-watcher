<?php

namespace Kalpesh\CacheWatcher\Facade;

use Illuminate\Support\Facades\Facade;

class CacheWatch  extends Facade
{

    protected static function  getFacadeAccessor(): string
    {
        return "CacheWatch";
    }
}
