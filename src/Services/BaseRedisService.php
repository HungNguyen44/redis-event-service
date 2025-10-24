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
    public string $streamKey;
    public string $deadLetterStreamKey;

    public string $deadLetterMailGroup;
    public string $deadLetterMailConsumer;
    public function __construct(LoggerService $logger)
    {
        $this->logger = $logger;
        $this->streamKey = Config::get('redis-event.stream_key');
        $this->timezone = Config::get('redis-event.timezone');
        $this->deadLetterStreamKey = Config::get('redis-event.dead_letter_stream_key');
        $this->deadLetterMailGroup = Config::get('redis-event.dead_letter_mail_group');
        $this->deadLetterMailConsumer = Config::get('redis-event.dead_letter_mail_consumer');
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
     * Get the idle time for claiming events
     * @example 60000 (60s) - message idle > 60s mới claim
     * @return int
     */
    abstract public function getIdleTime(): int;

    /**
     * get max auto claim count
     * @example 50
     * @return int
     */
    abstract public function getMaxAutoClaimCount(): int;


    /**
     * set up max times_delivered to move to dead letter
     * @example 3
     * @return int
     */
    abstract public function getMaxTimesDelivered(): int;

    /**
     * Get the name of the service
     * @return string
     */
    public function getServiceName(): string
    {
        return Config::get('redis-event.stream_service_name');
    }

    /**
     * Summary of getStreamKey
     * @return string
     */
    public function getStreamKey(): string
    {
        return $this->streamKey;
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
        $justCreated = $this->createGroupIfNotExistsFromStart();

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
                'stream' => $this->streamKey,
                'consumer_name' => $this->getConsumerName()
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
    protected function createGroupIfNotExistsFromStart(): bool
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
                'payload' => json_decode($fields['payload'] ?? '{}', true),
                'createdAt' => $fields['createdAt'] ?? null
            ];
        }

        $this->logger->info('Parsed events', ['count' => count($result), 'events' => $result]);
        return $result;
    }



    /**
     * XAUTOCLAIM
     * @param string $messageId
     * @return void
     */
    public function xautoclaimAllPending(): array
    {
        try {
            $client = Redis::connection()->client();

            [$nextId, $messages] = $client->xAutoClaim(
                $this->streamKey,
                $this->getGroupName(),
                $this->getConsumerName(),
                $this->getIdleTime(), // ví dụ: 60000 ms
                '0-0',
                $this->getMaxAutoClaimCount()
            );

            if (!empty($messages)) {
                $this->logger->info('XAUTOCLAIM retrieved pending messages', [
                    'count' => count($messages),
                    'messages' => $messages,
                    'next_id' => $nextId
                ]);
            } else {
                $this->logger->info('No pending messages to claim');
            }

            return [$nextId, $messages];
        } catch (\Throwable $e) {
            $this->logger->error('XAUTOCLAIM failed', [
                'error' => $e->getMessage(),
                'stream_key' => $this->streamKey,
                'group_name' => $this->getGroupName(),
                'consumer_name' => $this->getConsumerName(),
            ]);
            return [null, []];
        }
    }

    /**
     * Summary of moveToDeadLetter
     * @param array $event
     * @param int $retries
     * @return void
     */
    public function moveToDeadLetter(array $event, int $retries): void
    {
        $deadStream =   $this->deadLetterStreamKey;

        $client = Redis::connection()->client();

        $client->xAdd($deadStream, '*', [
            'original_id' => $event['id'],
            'type' => $event['type'],
            'consumer' => $this->getConsumerName(),
            'service' => $this->getServiceName(),
            'payload' => $event['payload'],
            'retries' => $retries,
            'moved_at' => Carbon::now($this->timezone)->format('Y-m-d H:i:s'),
        ]);

        $this->logger->warning('☠️ Moved to Dead-letter Stream', [
            'id' => $event['id'],
            'stream' => $deadStream,
            'retries' => $retries
        ]);
    }


    /**
     * Summary of getPendingInfo
     * @param string $messageId
     * @return array
     */
    public function getPendingInfo(string $messageId): array
    {
        $client = Redis::connection()->client();

        $info = $client->rawCommand(
            'XPENDING',
            $this->getStreamKey(),
            $this->getGroupName(),
            $messageId,
            $messageId,
            1
        );


        if (empty($info)) {
            return [];
        }

        $data = $info[0] ?? [];

        // Check if we have the expected data structure
        if (empty($data) || !isset($data[0], $data[1], $data[2], $data[3])) {
            $this->logger->warning('Unexpected XPENDING response format', [
                'data' => $data
            ]);
            return [
                'message_id' => $data[0] ?? null,
                'consumer' => $data[1] ?? null,
                'idle' => $data[2] ?? 0,
                'times_delivered' => $data[3] ?? 0
            ];
        }

        return [
            'message_id' => $data[0],
            'consumer' => $data[1],
            'idle' => $data[2],
            'times_delivered' => $data[3] ?? 0
        ];
    }


    /******************************************************************************************************************* */
    /**
     * Handle Dead Letter Queue (DLQ) messages — for reprocessing or manual handling.
     *
     * @param callable $callback Function that receives each message for processing: function(array $message): void
     * @param string|null $groupName Optionally override consumer group name (default: dead.reprocess.group)
     * @param int $count Number of messages per batch
     * @param int $block Block time in milliseconds
     * @return void
     */
    public function processDeadLetterMessages(callable $callback, ?string $groupName = null, int $count = 10, int $block = 10000): void
    {
        $deadStream = $this->deadLetterStreamKey;
        $group = $groupName ?? 'dead.reprocess.group';
        $consumer = $this->getConsumerName() . '-reprocess';
        $currentService = $this->getServiceName();
        $expectedType = $this->getStreamKey();

        $this->logger->info("♻️ Listening DLQ stream: {$deadStream}");
        $this->logger->info("🔍 Filtering service={$currentService}, type={$expectedType}");

        // Ensure consumer group exists
        try {
            Redis::xgroup('CREATE', $deadStream, $group, '0', true);
        } catch (\Exception $e) {
            // ignore if group already exists
        }

        while (true) {
            $entries = Redis::xreadgroup(
                $group,
                $consumer,
                [$deadStream => '>'],
                $count,
                $block
            );

            if (empty($entries[$deadStream])) {
                continue;
            }

            foreach ($entries[$deadStream] as $id => $fields) {
                $message = [
                    'id'          => $id,
                    'original_id' => $fields['original_id'] ?? null,
                    'type'        => $fields['type'] ?? null,
                    'service'     => $fields['service'] ?? 'unknown',
                    'consumer'    => $fields['consumer'] ?? 'unknown',
                    'payload'     => $fields['payload'] ?? '{}',
                    'retries'     => $fields['retries'] ?? 0,
                    'moved_at'    => $fields['moved_at'] ?? null,
                ];

                // Skip message if service/type mismatch
                if ($message['service'] !== $currentService || $message['type'] !== $expectedType) {
                    $this->logger->info("⏭️ Skipping mismatched DLQ message", [
                        'id' => $id,
                        'message_service' => $message['service'],
                        'expected_service' => $currentService,
                        'message_type' => $message['type'],
                        'expected_type' => $expectedType,
                    ]);
                    Redis::xack($deadStream, $group, [$id]);
                    continue;
                }

                try {
                    $this->logger->info("🔁 Processing DLQ message", [
                        'id' => $id,
                        'type' => $message['type'],
                        'service' => $message['service'],
                    ]);

                    // Call user callback to handle message
                    $callback($message);

                    Redis::xack($deadStream, $group, [$id]);
                    $this->logger->info("✅ DLQ message processed", ['id' => $id]);
                } catch (\Throwable $e) {
                    $this->logger->error("❌ DLQ message processing failed", [
                        'id' => $id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }

    /**
     * Create a group if it does not exist (start reading only from *new* messages)
     *
     * @return bool
     */
    public function createGroupIfNotExistsFromNow(): bool
    {
        try {
            $groups = Redis::xinfo('GROUPS', $this->streamKey);
            $groupExists = collect($groups)->pluck('name')->contains($this->getGroupName());
            if ($groupExists) {
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->info('No groups found, creating from now.', ['error' => $e->getMessage()]);
        }

        try {
            Redis::xgroup('CREATE', $this->streamKey, $this->getGroupName(), '$', true);
            $this->logger->info('✅ Created group from now ($)', [
                'stream' => $this->streamKey,
                'group' => $this->getGroupName()
            ]);
            return true;
        } catch (\Exception $e) {
            $this->logger->error('❌ Failed to create group from now', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Read messages from DLQ (for notification/mail purpose)
     *
     * @param callable $callback Callback executed for each message: function(array $message): void
     * @param string|null $groupName Optional group name for DLQ mail listener
     * @param int $count Max number of messages per batch
     * @param int $block Block time (ms)
     * @return void
     */
    /**
     * Consume all DLQ messages (for sending system-wide mail alerts)
     *
     * @param callable $callback Function to handle mail send: function(array $message): void
     * @param int $count Max number of messages per batch
     * @param int $block Block time (ms)
     * @return void
     */
    public function consumeDeadLetterForMail(callable $callback, int $count = 10, int $block = 5000): void
    {
        $deadStream = $this->deadLetterStreamKey;
        $group = $this->deadLetterMailGroup;
        $consumer = $this->deadLetterMailConsumer;

        $this->logger->info("📬 Start consuming DLQ (system-wide mail alerts) from stream: {$deadStream}");

        // Ensure DLQ mail consumer group exists (start from now)
        try {
            Redis::xgroup('CREATE', $deadStream, $group, '$', true);
            $this->logger->info("✅ Created DLQ mail group '{$group}' (start from now)");
        } catch (\Exception $e) {
            // ignore if already exists
        }

        while (true) {
            $entries = Redis::xreadgroup(
                $group,
                $consumer,
                [$deadStream => '>'],
                $count,
                $block
            );

            if (empty($entries[$deadStream])) {
                continue;
            }

            foreach ($entries[$deadStream] as $id => $fields) {
                $message = [
                    'id'          => $id,
                    'original_id' => $fields['original_id'] ?? null,
                    'type'        => $fields['type'] ?? null,
                    'service'     => $fields['service'] ?? 'unknown',
                    'consumer'    => $fields['consumer'] ?? 'unknown',
                    'payload'     => $fields['payload'] ?? '{}',
                    'retries'     => $fields['retries'] ?? 0,
                    'moved_at'    => $fields['moved_at'] ?? null,
                ];

                try {
                    $this->logger->info("📨 Processing DLQ message for mail", [
                        'id' => $id,
                        'type' => $message['type'],
                        'service' => $message['service']
                    ]);

                    // Call mail callback
                    $callback($message);

                    // Acknowledge message
                    Redis::xack($deadStream, $group, [$id]);

                    $this->logger->info("📧 Mail notification sent for DLQ message", [
                        'id' => $id,
                        'service' => $message['service']
                    ]);
                } catch (\Throwable $e) {
                    $this->logger->error("❌ Failed to send DLQ mail", [
                        'id' => $id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        }
    }
}
