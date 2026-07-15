<?php

namespace MqttBroadcastDriver;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\ServiceProvider;

class MqttBroadcastServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/mqtt-broadcast.php', 'mqtt-broadcast');

        $this->app->resolving(BroadcastManager::class, function (BroadcastManager $manager) {
            $manager->extend('mqtt', function ($app, array $config) {
                return new MqttBroadcaster($config);
            });
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/mqtt-broadcast.php' => config_path('mqtt-broadcast.php'),
            ], 'mqtt-broadcast-config');
        }
    }
}
