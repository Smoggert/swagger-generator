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
    public function register()
    {
        //$this->app->make("smoggert\swagger-generator\SwaggerGeneratorService");
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        dd('ttetten');
        $this->publishes([
            __DIR__.'/config/swagger_gen.php' => \config_path('swagger_gen.php')
        ], 'config');

        if($this->app->runningInConsole()) {
            $this->commands([
                GenerateSwaggerCommand::class,
            ]);
        }
    }
}