<?php

namespace Icivi\RedisEventService\Console\Commands;

use Icivi\RedisEventService\Services\BaseRedisService;
use Icivi\RedisEventService\Services\LoggerService;
use Illuminate\Console\Command;

abstract class BaseRedisDeadLetterReprocessCommand extends Command
{
    protected LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
        parent::__construct();
    }

    abstract protected function getRedisService(): BaseRedisService;

    /**
     * Logic xử lý lại từng message DLQ.
     */
    abstract protected function reprocessMessage(array $message): void;

    public function handle()
    {
        $redis = $this->getRedisService();

        $redis->processDeadLetterMessages(function (array $message) {
            $this->reprocessMessage($message);
        });
    }
}
