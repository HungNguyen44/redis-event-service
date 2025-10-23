<?php

namespace Icivi\RedisEventService\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class phpMakeDtoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:redis-dto {name : The name of the DTO class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Redis DTO class that extends BaseDto';

    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new command instance.
     *
     * @param  \Illuminate\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $name = $this->argument('name');

        // Add 'Dto' suffix if not already present
        if (!Str::endsWith($name, 'Dto')) {
            $name .= 'Dto';
        }

        $className = $name;

        $path = $this->getPath($className);

        // Check if file already exists
        if ($this->files->exists($path)) {
            $this->error("$className already exists!");
            return false;
        }

        // Create directory if it doesn't exist
        $this->makeDirectory($path);

        // Get stub content and replace placeholders
        $stub = $this->files->get($this->getStub());
        $stub = $this->replaceNamespace($stub, $className)
            ->replaceClass($stub, $className);

        // Write the file
        $this->files->put($path, $stub);

        $this->info("$className created successfully.");

        return true;
    }

    /**
     * Get the stub file path.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/dto.stub';
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        return app_path('Dtos/' . $name . '.php');
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string  $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }

        return $path;
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            ['{{ namespace }}'],
            ['App\\Dtos'],
            $stub
        );

        return $this;
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceClass(&$stub, $name)
    {
        $stub = str_replace('{{ class }}', $name, $stub);

        return $this;
    }
}
