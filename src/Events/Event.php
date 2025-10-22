<?php

namespace Icivi\RedisEventService\Events;

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
            'createdAt' => Carbon::now()->format('Y-m-d H:i:s')
        ]);
    }
}
