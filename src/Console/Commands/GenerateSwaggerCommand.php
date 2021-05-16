<?php

namespace Smoggert\SwaggerGenerator\Console\Commands;

use Illuminate\Console\Command;
use Smoggert\SwaggerGenerator\Services\SwaggerGeneratorService;

class GenerateSwaggerCommand extends Command
{
    protected $signature = 'swagger:generate {--format=yaml}';

    protected $description = 'Generate swagger documentation based on your api routes';

    protected $swagger_generator_service;

    public function __construct(SwaggerGeneratorService $swagger_generator_service)
    {
        parent::__construct();

        $this->swagger_generator_service = $swagger_generator_service;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        return $this->swagger_generator_service->generate($this->output, $this->option('format'));
    }
}
