<?php

namespace Icivi\RedisEventService\Services;

use Icivi\RedisEventService\Events\Event;
use Illuminate\Support\Facades\Redis;
use Icivi\RedisEventService\Services\LoggerService;

abstract class BaseRedisService
{
    protected LoggerService $logger;
    public const STREAM_KEY = 'events';

    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
    }
    abstract public function getGroupName(): string;
    public const CONSUMER_NAME = 'consumer-1';
    public const BATCH_SIZE = 10;

    abstract public function getServiceName(): string;

    public function publish(Event $event): void
    {
        Redis::xadd(self::STREAM_KEY, '*', [
            'event' => $event->toJson(),
            'service' => $this->getServiceName(),
            'createdAt' => now()->format('Y-m-d H:i:s')
        ]);
    }

    public function getUnprocessedEvents(): array
    {
        $justCreated = $this->createGroupIfNotExists();

        // Nếu vừa tạo group thì đọc từ '0' để lấy toàn bộ sự kiện cũ, nếu không thì dùng '>'
        $offset = $justCreated ? '0' : '>';

        $entries = Redis::xreadgroup(
            $this->getGroupName(),
            self::CONSUMER_NAME,
            [self::STREAM_KEY => $offset],
            self::BATCH_SIZE,
            10000 // block 10s
        );

        if (!is_array($entries) || empty($entries[self::STREAM_KEY])) {
            $this->logger->info('No entries returned from XREADGROUP', [
                'entries' => $entries,
                'stream' => self::STREAM_KEY
            ]);
            return [];
        }

        return $this->parseEvents($entries[self::STREAM_KEY]);
    }

    public function acknowledge(string $messageId): void
    {
        if (!empty($messageId)) {
            try {
                Redis::xack(self::STREAM_KEY, $this->getGroupName(), [$messageId]);
                $this->logger->info("XACK success", ['id' => $messageId]);
            } catch (\Exception $e) {
                $this->logger->error("XACK failed", ['id' => $messageId, 'error' => $e->getMessage()]);
            }
        } else {
            $this->logger->warning("XACK skipped: empty message ID");
        }
    }

    protected function createGroupIfNotExists(): bool
    {
        try {
            $groups = Redis::xinfo('GROUPS', self::STREAM_KEY);

            $groupExists = collect($groups)->pluck('name')->contains($this->getGroupName());
            if ($groupExists) {
                return false;
            }
        } catch (\Exception $e) {
            // Nếu stream chưa tồn tại → vẫn cho phép tạo
            $this->logger->info('No groups yet or stream not found, will try creating group.', ['error' => $e->getMessage()]);
        }

        try {
            Redis::xgroup('CREATE', self::STREAM_KEY, $this->getGroupName(), '0', true);
            $this->logger->info('✅ Created stream group', [
                'stream' => self::STREAM_KEY,
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


    protected function parseEvents(array $eventsFromRedis): array
    {
        $result = [];

        foreach ($eventsFromRedis as $messageId => $fields) {
            $eventJson = $fields['event'] ?? '{}';
            $eventData = json_decode($eventJson, true);

            if (is_array($eventData) && isset($eventData['type'])) {
                $result[] = array_merge($eventData, ['id' => $messageId]);
            } else {
                $this->logger->warning('Failed to parse event', [
                    'id' => $messageId,
                    'event_json' => $eventJson
                ]);
            }
        }

        return $result;
    }
}
