# MqttBroadcastDriver

MQTT broadcast driver for Laravel. Publishes broadcasting events to an MQTT broker (EMQX or any MQTT 3.1.1/5.0 compatible broker).

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- An MQTT broker (e.g. EMQX)

## Installation

```bash
composer require dimakozachkov/mqtt-broadcast-driver
```

The service provider is auto-discovered. No manual registration needed.

## Configuration

### 1. Publish config (optional)

```bash
php artisan vendor:publish --tag=mqtt-broadcast-config
```

### 2. Add MQTT connection to `config/broadcasting.php`

```php
'connections' => [
    'mqtt' => config('mqtt-broadcast.connection'),

    // ...other connections
],
```

### 3. Set environment variables

```env
BROADCAST_CONNECTION=mqtt

MQTT_HOST=emqx
MQTT_PORT=1883
MQTT_USERNAME=
MQTT_PASSWORD=
```

## Usage

Standard Laravel broadcasting works without any changes:

```php
class OrderShipped implements ShouldBroadcast
{
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('orders.' . $this->order->user_id),
        ];
    }
}
```

Channel authorization in `routes/channels.php`:

```php
Broadcast::channel('orders.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
```

## MQTT Topic Mapping

| Laravel Channel | MQTT Topic |
|-----------------|------------|
| `news` | `public/news` |
| `private-orders.5` | `private/orders.5` |
| `presence-chat.1` | `presence/chat.1` |

## Local Development (path repository)

Add to your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../path-to-this-package"
        }
    ]
}
```

Then:

```bash
composer require dimakozachkov/mqtt-broadcast-driver:@dev
```

## License

MIT
