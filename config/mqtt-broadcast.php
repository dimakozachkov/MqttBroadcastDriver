<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MQTT Broadcasting Connection
    |--------------------------------------------------------------------------
    |
    | Add this connection to your config/broadcasting.php 'connections' array:
    |
    |   'mqtt' => config('mqtt-broadcast.connection'),
    |
    | Then set BROADCAST_CONNECTION=mqtt in your .env file.
    |
    */

    'connection' => [
        'driver' => 'mqtt',
        'host' => env('MQTT_HOST', '127.0.0.1'),
        'port' => (int) env('MQTT_PORT', 1883),
        'mqtt_username' => env('MQTT_USERNAME', ''),
        'mqtt_password' => env('MQTT_PASSWORD', ''),
        'app_key' => env('REVERB_APP_KEY', env('PUSHER_APP_KEY', '')),
        'app_secret' => env('REVERB_APP_SECRET', env('PUSHER_APP_SECRET', '')),
        'keep_alive' => 60,
        'connect_timeout' => 10,
    ],

];
