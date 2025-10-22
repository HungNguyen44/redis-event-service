<?php

namespace Icivi\RedisEventService;

use Illuminate\Support\ServiceProvider;
use Icivi\RedisEventService\Services\LoggerService;

class RedisEventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/redis-event.php' => config_path('redis-event.php'),
        ], 'config');
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
