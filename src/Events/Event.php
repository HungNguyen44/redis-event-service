<?php

namespace Icivi\RedisEventService\Events;

use Carbon\Carbon;

abstract class Event
{
    /**
     * Get the type of the event
     * @return string
     */
    abstract public function getType(): string;

    /**
     * Get the payload of the event
     * @return array
     */
    abstract public function getPayload(): array;

    /**
     * Convert the event to a JSON string
     * @return string
     */
    public function toJson(): string
    {
        return json_encode([
            'type' => $this->getType(),
            'payload' => $this->getPayload(),
            'createdAt' => Carbon::now('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s')
        ]);
    }
}
