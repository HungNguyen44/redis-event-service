<?php

namespace Icivi\RedisEventService\Events;

use Carbon\Carbon;
use Illuminate\Support\Facades\Config;

abstract class Event
{

    private string $timezone;

    /**
     * Đối tượng dữ liệu cho event
     * @var object
     */
    protected object $data;

    /**
     * Payload của event
     * @var object
     */
    protected object $payload;

    /**
     * Loại event
     * @var string
     */
    protected string $eventType;

    /**
     * Constructor
     *
     * @param object $data Đối tượng dữ liệu
     */
    public function __construct(object $data)
    {
        $this->data = $data;
        $this->payload = $data;
        $this->timezone = Config::get('redis-event.timezone');
    }

    /**
     * Get the type of the event
     * @return string
     */
    public function getType(): string
    {
        return $this->eventType;
    }

    /**
     * Get the payload of the event
     * @return array
     */
    public function getPayload(): array
    {
        if (method_exists($this->data, 'toArray')) {
            return $this->data->toArray();
        }

        // Fallback nếu đối tượng không có phương thức toArray()
        return (array) $this->data;
    }

    /**
     * Convert the event to a JSON string
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'type' => $this->getType(),
            'payload' => $this->getPayload(),
            'createdAt' => Carbon::now($this->timezone)->format('Y-m-d H:i:s')
        ]);
    }
}
