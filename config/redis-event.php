<?php

return [
    'stream_prefix'   => env('REDIS_STREAM_PREFIX', 'stream:'),
    'default_group'   => env('REDIS_GROUP', 'default-group'),
    'default_consumer' => env('REDIS_CONSUMER', 'worker-1'),
];
