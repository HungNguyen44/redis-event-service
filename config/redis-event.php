<?php

return [
    'event_service_version' => env('REDIS_EVENT_SERVICE_VERSION', '1.0.0'),
    'stream_service_name' => env('REDIS_STREAM_SERVICE_NAME', 'default'),
    'stream_key' => env('APP_ENV', 'local') . ':' . env('REDIS_STREAM_SERVICE_NAME', 'default') . ':' . ':events' . ':' . env('REDIS_EVENT_SERVICE_VERSION', '1.0.0'),
    'default_group'   => env('REDIS_GROUP', 'default-group'),
    'default_consumer' => env('REDIS_CONSUMER', 'worker-1'),
];
