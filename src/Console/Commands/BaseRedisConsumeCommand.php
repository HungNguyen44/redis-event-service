<?php

namespace Icivi\RedisEventService\Console\Commands;

use Icivi\RedisEventService\Services\BaseRedisService;
use Icivi\RedisEventService\Services\LoggerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

abstract class BaseRedisConsumeCommand extends Command
{
    protected LoggerService $logger;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
        parent::__construct();
    }

    abstract protected function getRedisService(): BaseRedisService;

    abstract protected function processEvent(array $event);

    /**
     * Main entry point
     */
    public function handle()
    {
        $redis = $this->getRedisService();

        $this->logger->info('🚀 Start consuming Redis stream...');

        // 1️⃣ Reclaim pending messages
        $this->logger->info('🔄 Auto-claiming pending messages...');
        [$nextId, $pendingMessages] = $redis->xautoclaimAllPending();

        // Ensure $pendingMessages is an array
        $pendingMessages = $pendingMessages ?? [];

        // 2️⃣ Handle reclaimed messages
        $this->handleReclaimedMessages($redis, $pendingMessages);

        // 3️⃣ Handle new messages
        $this->handleNewMessages($redis);

        return 0;
    }

    /**
     * Process reclaimed messages that were pending
     */
    protected function handleReclaimedMessages(BaseRedisService $redis, array $messages): void
    {
        if (empty($messages)) {
            $this->logger->info('No pending messages to reclaim.');
            return;
        }

        foreach ($messages as $messageId => $fields) {
            $event = [
                'id' => $messageId,
                'type' => $fields['type'] ?? null,
                'service' => $fields['service'] ?? null,
                'payload' => $fields['payload'] ?? '{}',
                'createdAt' => $fields['createdAt'] ?? null,
            ];

            // 🔹 Lấy thông tin Times Delivered
            $pendingInfo = $redis->getPendingInfo($messageId);
            $timesDelivered = $pendingInfo['times_delivered'] ?? 0;

            // 🔸 Nếu vượt ngưỡng retry cho phép
            if ($timesDelivered >= $redis->getMaxTimesDelivered()) {
                $redis->moveToDeadLetter($event, $timesDelivered);
                $redis->acknowledge($event['id']); // xóa khỏi pending
                continue;
            }

            try {
                $this->logger->info("🔁 Reprocessing reclaimed message", [
                    'id' => $event['id'],
                    'type' => $event['type']
                ]);

                $this->processEvent($event);
                $redis->acknowledge($event['id']);

                $this->logger->info("✅ Reprocessed and acknowledged", [
                    'id' => $event['id']
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('❌ Failed to reprocess reclaimed message', [
                    'id' => $event['id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }


    /**
     * Process newly arrived messages from XREADGROUP
     */
    protected function handleNewMessages(BaseRedisService $redis): void
    {
        foreach ($redis->getUnprocessedEvents() as $event) {
            $this->logger->info('📨 Event received', [
                'type' => $event['type'] ?? 'unknown',
                'id' => $event['id'] ?? 'unknown'
            ]);

            try {
                // xử lý event
                $this->processEvent($event);

                // xác nhận event đã được xử lý
                $redis->acknowledge($event['id']);

                // log thành công
                $this->logger->info("✅ Processed new message", [
                    'id' => $event['id']
                ]);
            } catch (\Throwable $e) {
                // log lỗi
                $this->logger->error('❌ Failed to process event', [
                    'event_id' => $event['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }
}
