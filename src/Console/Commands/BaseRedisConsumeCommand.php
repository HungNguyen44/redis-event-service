<?php

namespace Icivi\RedisEventService\Console\Commands;

use Icivi\RedisEventService\Services\BaseRedisService;
use Icivi\RedisEventService\Services\LoggerService;
use Illuminate\Console\Command;

/**
 * Base command for consuming events from Redis stream using XREADGROUP
 */
abstract class BaseRedisConsumeCommand extends Command
{
    protected LoggerService $logger;

    /**
     * Get the Redis service instance.
     *
     * @return BaseRedisService
     */
    abstract protected function getRedisService(): BaseRedisService;

    /**
     * Process an event based on its type.
     *
     * @param array $event The event data to process
     * @return mixed
     */
    abstract protected function processEvent(array $event);

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $redis = $this->getRedisService();

        $this->logger->info('🚀 Start consuming Redis stream...');

        foreach ($redis->getUnprocessedEvents() as $event) {
            $this->logger->info('📨 Event received', [
                'type' => $event['type'] ?? 'unknown',
                'id' => $event['id'] ?? 'unknown'
            ]);

            try {
                // Process the event using the implementation from child class
                $this->processEvent($event);

                // Acknowledge the event
                $redis->acknowledge($event['id']);
            } catch (\Throwable $e) {
                $this->logger->error('❌ Failed to process event', [
                    'event_id' => $event['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
        return 0;
    }
}
