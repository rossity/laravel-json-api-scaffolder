<?php

namespace Rossity\LaravelApiScaffolder;

use Illuminate\Support\ServiceProvider;
use Rossity\LaravelApiScaffolder\Console\ScaffoldApplication;
use Rossity\LaravelApiScaffolder\Console\ScaffoldConfig;

class LaravelApiScaffolderServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'rossity');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'rossity');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laravelapiscaffolder.php', 'laravelapiscaffolder');

        // Register the service the package provides.
        $this->app->singleton('laravelapiscaffolder', function ($app) {
            return new LaravelApiScaffolder;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['laravelapiscaffolder'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole()
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config/laravelapiscaffolder.php' => config_path('laravelapiscaffolder.php'),
        ], 'laravelapiscaffolder.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/rossity'),
        ], 'laravelapiscaffolder.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/rossity'),
        ], 'laravelapiscaffolder.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/rossity'),
        ], 'laravelapiscaffolder.views');*/

        // Registering package commands.
        $this->commands([
            ScaffoldConfig::class,
            ScaffoldApplication::class,
        ]);
    }
}
