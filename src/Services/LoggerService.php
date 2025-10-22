<?php

namespace Icivi\RedisEventService\Services;

use Illuminate\Support\Facades\Log;

class LoggerService
{
    /**
     * Log info message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }
}
