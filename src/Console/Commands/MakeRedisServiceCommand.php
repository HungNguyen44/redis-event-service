<?php

namespace Icivi\RedisEventService\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeRedisServiceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:redis-service {name : The name of the service}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new Redis service class that extends BaseRedisService';

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

        // Add 'Service' suffix if not already present
        if (!Str::endsWith($name, 'Service')) {
            $name .= 'Service';
        }

        $className = $name;
        $tagName = Str::snake(str_replace('Service', '', $className));

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
            ->replaceClass($stub, $className)
            ->replaceTagName($stub, $tagName);

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
        return __DIR__ . '/stubs/redis-service.stub';
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        return app_path('Services/' . $name . '.php');
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
            ['App\\Services'],
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

    /**
     * Replace the tag name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $tagName
     * @return string
     */
    protected function replaceTagName($stub, $tagName)
    {
        return str_replace('{{ tag_name }}', $tagName, $stub);
    }
}
