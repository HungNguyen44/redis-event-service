<?php

namespace Icivi\RedisEventService;

use Illuminate\Support\ServiceProvider;
use Icivi\RedisEventService\Services\LoggerService;
use Icivi\RedisEventService\Console\Commands\MakeRedisServiceCommand;
use Icivi\RedisEventService\Console\Commands\MakeDtoCommand;

class RedisEventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/redis-event.php' => config_path('redis-event.php'),
        ], 'config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeRedisServiceCommand::class,
                MakeDtoCommand::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/redis-event.php',
            'redis-event'
        );

        $this->app->singleton(LoggerService::class, function ($app) {
            return new LoggerService();
        });
    }
}
