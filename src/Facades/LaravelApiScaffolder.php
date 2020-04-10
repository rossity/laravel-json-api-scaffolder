<?php

namespace Rossity\LaravelApiScaffolder\Facades;

use Illuminate\Support\Facades\Facade;

class LaravelApiScaffolder extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravelapiscaffolder';
    }
}
