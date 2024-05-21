<?php

namespace Smoggert\SwaggerGenerator;

use Illuminate\Support\ServiceProvider;
use Smoggert\SwaggerGenerator\Console\Commands\GenerateSwaggerCommand;

class SwaggerGeneratorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/smoggert_swagger.php', 'smoggert_swagger'
        );
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/smoggert_swagger.php' => \config_path('smoggert_swagger.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerCommand::class,
            ]);
        }
    }
}
