<?php

namespace Icivi\RedisEventService\Events;

abstract class Event
{
    abstract public function getType(): string;

    abstract public function getPayload(): array;

    public function toJson(): string
    {
        return json_encode([
            'type' => $this->getType(),
            'payload' => $this->getPayload(),
        ]);
    }
}
