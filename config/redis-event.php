<?php

return [
    'event_service_version' => env('REDIS_EVENT_SERVICE_VERSION', '1.0.0'),
    'stream_service_name' => env('REDIS_STREAM_SERVICE_NAME', 'default'),
    'stream_key' => env('APP_ENV', 'local') . ':' . env('REDIS_STREAM_SERVICE_NAME', 'default') . ':' . 'events' . ':' . env('REDIS_EVENT_SERVICE_VERSION', '1.0.0'),
    'dead_letter_stream_key' => env('REDIS_DEAD_LETTER_STREAM_KEY', 'icivi:dead:letter:stream'),
    'timezone' => env('REDIS_EVENT_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'dead_letter_mail_group' => env('REDIS_DEAD_LETTER_MAIL_GROUP', 'dead.mail.notify.group'),
    'dead_letter_mail_consumer' => env('REDIS_DEAD_LETTER_MAIL_CONSUMER', 'dead-mail-notify-consumer'),
];
