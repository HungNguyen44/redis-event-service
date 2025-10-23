<?php

namespace Icivi\RedisEventService\Services;

use Icivi\RedisEventService\Dto\BaseDto;
use Icivi\RedisEventService\Events\Event;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Icivi\RedisEventService\Services\LoggerService;

abstract class BaseRedisService
{
    protected string $timezone;
    protected LoggerService $logger;
    protected string $streamKey;

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
        $this->streamKey = Config::get('redis-event.stream_key');
        $this->timezone = Config::get('redis-event.timezone');
    }

    /**
     * Get the name of the group
     * @example '<service_name>.<environment>.<purpose>.<version>'
     * @example 'order.local.process.v1'
     * @return string
     */
    abstract public function getGroupName(): string;

    /**
     * Get the name of the consumer
     * @example '<service_name>-<environment>-worker-<worker_version>-<worker_number>'
     * @example 'order-local-worker-v1-1'
     * @return string
     */
    abstract public function getConsumerName(): string;

    /**
     * Get the block time for reading events
     * @example 10000 (10s)
     * @return int
     */
    abstract public function getBlockTime(): int;

    /**
     * The batch size for reading events
     * @example 10 (10 events)
     * @return int
     */
    abstract public function getBatchSize(): int;


    /**
     * Get the name of the service
     * @return string
     */
    public function getServiceName(): string
    {
        return Config::get('redis-event.stream_service_name');
    }

    /**
     * Summary of publishEvent
     * @param \Icivi\RedisEventService\Dto\BaseDto $dto
     * @return void
     */
    abstract public function publishEvent(BaseDto $dto): void;

    /**
     * Publish an event to the Redis stream
     * @param Event $event
     * @return void
     */
    public function publish(Event $event): void
    {
        Redis::xadd($this->streamKey, '*', [
            'type' => $event->getType(),
            'service' => $this->getServiceName(),
            'payload' => $event->toJson(),
            'createdAt' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get the unprocessed events
     * @return array
     */
    public function getUnprocessedEvents(): array
    {
        $justCreated = $this->createGroupIfNotExists();

        // Nếu vừa tạo group thì đọc từ '0' để lấy toàn bộ sự kiện cũ, nếu không thì dùng '>'
        $offset = $justCreated ? '0' : '>';

        $entries = Redis::xreadgroup(
            $this->getGroupName(),
            $this->getConsumerName(),
            [$this->streamKey => $offset],
            $this->getBatchSize(),
            $this->getBlockTime()
        );

        if (!is_array($entries) || empty($entries[$this->streamKey])) {
            $this->logger->info('No entries returned from XREADGROUP', [
                'entries' => $entries,
                'stream' => $this->streamKey
            ]);
            return [];
        }

        return $this->parseEvents($entries[$this->streamKey]);
    }

    /**
     * Acknowledge an event
     * @param string $messageId
     * @return void
     */
    public function acknowledge(string $messageId): void
    {
        if (!empty($messageId)) {
            try {
                Redis::xack($this->streamKey, $this->getGroupName(), [$messageId]);
                $this->logger->info("XACK success", ['id' => $messageId]);
            } catch (\Exception $e) {
                $this->logger->error("XACK failed", ['id' => $messageId, 'error' => $e->getMessage()]);
            }
        } else {
            $this->logger->warning("XACK skipped: empty message ID");
        }
    }

    /**
     * Create a group if it does not exist
     * @return bool
     */
    protected function createGroupIfNotExists(): bool
    {
        try {
            $groups = Redis::xinfo('GROUPS', $this->streamKey);

            $groupExists = Collection::make($groups)->pluck('name')->contains($this->getGroupName());
            if ($groupExists) {
                return false;
            }
        } catch (\Exception $e) {
            // Nếu stream chưa tồn tại → vẫn cho phép tạo
            $this->logger->info('No groups yet or stream not found, will try creating group.', ['error' => $e->getMessage()]);
        }

        try {
            Redis::xgroup('CREATE', $this->streamKey, $this->getGroupName(), '0', true);
            $this->logger->info('✅ Created stream group', [
                'stream' => $this->streamKey,
                'group' => $this->getGroupName()
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('❌ Failed to create group', ['error' => $e->getMessage()]);
            return false;
        }
    }


    // protected function parseEvents(array $eventsFromRedis): array
    // {
    //     $result = [];

    //     foreach ($eventsFromRedis as $entry) {
    //         [$messageId, $fields] = $entry;

    //         $eventJson = $fields['event'] ?? '{}';
    //         $eventData = json_decode($eventJson, true);

    //         if (is_array($eventData) && isset($eventData['type'])) {
    //             $result[] = array_merge($eventData, ['id' => $messageId]);
    //         } else {
    //             $this->logger->warning('Failed to parse event', ['id' => $messageId, 'raw' => $eventJson]);
    //         }
    //     }

    //     return $result;
    // }

    /**
     * Parse the events from Redis
     * @param array $eventsFromRedis
     * @return array
     */
    protected function parseEvents(array $eventsFromRedis): array
    {
        $result = [];

        foreach ($eventsFromRedis as $messageId => $fields) {
            // Structure matches what we see in the Redis stream
            $result[] = [
                'id' => $messageId,
                'type' => $fields['type'] ?? null,
                'service' => $fields['service'] ?? null,
                'payload' => $fields['payload'] ?? '{}',
                'createdAt' => $fields['createdAt'] ?? null
            ];
        }

        $this->logger->info('Parsed events', ['count' => count($result), 'events' => $result]);
        return $result;
    }
}
