<?php

namespace Modules\PageBuilder;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class VvvebJsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__).'/database/migrations');
        View::addNamespace('page-builder', dirname(__DIR__).'/resources/views');
    }
}
