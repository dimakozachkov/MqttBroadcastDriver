<?php

namespace DmytroKozachkov\MqttBroadcast;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

class MqttBroadcaster extends Broadcaster
{
    protected string $host;
    protected int $port;
    protected string $appKey;
    protected string $appSecret;
    protected ConnectionSettings $connectionSettings;
    protected ?MqttClient $persistentClient = null;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->port = $config['port'] ?? 1883;
        $this->appKey = $config['app_key'] ?? '';
        $this->appSecret = $config['app_secret'] ?? '';

        $this->connectionSettings = (new ConnectionSettings())
            ->setUsername($config['mqtt_username'] ?? null)
            ->setPassword($config['mqtt_password'] ?? null)
            ->setKeepAliveInterval($config['keep_alive'] ?? 60)
            ->setConnectTimeout($config['connect_timeout'] ?? 10);
    }

    /**
     * Authenticate the incoming request for a given channel.
     */
    public function auth($request)
    {
        $channelName = str_starts_with($request->channel_name, 'private-')
            ? substr($request->channel_name, 8)
            : substr($request->channel_name, 9);

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * Return the valid authentication response.
     */
    public function validAuthenticationResponse($request, $result)
    {
        $socketId = $request->socket_id;
        $channel = $request->channel_name;

        if (str_starts_with($channel, 'presence-')) {
            $user = $this->retrieveUser($request, $channel);

            $channelData = json_encode([
                'user_id' => $user->getAuthIdentifier(),
                'user_info' => $result,
            ]);

            $signature = $this->generateSignature($socketId, $channel, $channelData);

            return [
                'auth' => $this->appKey . ':' . $signature,
                'channel_data' => $channelData,
            ];
        }

        $signature = $this->generateSignature($socketId, $channel);

        return [
            'auth' => $this->appKey . ':' . $signature,
        ];
    }

    /**
     * Broadcast the given event.
     */
    public function broadcast(array $channels, $event, array $payload = [])
    {
        $socket = $payload['socket'] ?? null;
        unset($payload['socket']);

        $message = json_encode([
            'event' => $event,
            'data' => $payload,
            'socket' => $socket,
        ]);

        $formattedChannels = $this->formatChannels($channels);
        $mqtt = $this->getConnection();

        try {
            foreach ($formattedChannels as $channel) {
                $topic = $this->channelToMqttTopic($channel);
                $mqtt->publish($topic, $message, MqttClient::QOS_AT_MOST_ONCE);
            }
        } catch (\Throwable $e) {
            $this->persistentClient = null;

            try {
                $mqtt = $this->getConnection();

                foreach ($formattedChannels as $channel) {
                    $topic = $this->channelToMqttTopic($channel);
                    $mqtt->publish($topic, $message, MqttClient::QOS_AT_MOST_ONCE);
                }
            } catch (\Throwable $retryError) {
                $this->persistentClient = null;

                throw new BroadcastException(
                    sprintf('MQTT broadcast failed: %s', $retryError->getMessage())
                );
            }
        }
    }

    protected function channelToMqttTopic(string $channel): string
    {
        if (str_starts_with($channel, 'private-')) {
            return 'private/' . substr($channel, 8);
        }

        if (str_starts_with($channel, 'presence-')) {
            return 'presence/' . substr($channel, 9);
        }

        return 'public/' . $channel;
    }

    protected function generateSignature(string $socketId, string $channel, ?string $channelData = null): string
    {
        $stringToSign = $channelData
            ? "{$socketId}:{$channel}:{$channelData}"
            : "{$socketId}:{$channel}";

        return hash_hmac('sha256', $stringToSign, $this->appSecret);
    }

    protected function getConnection(): MqttClient
    {
        if ($this->persistentClient !== null && $this->persistentClient->isConnected()) {
            return $this->persistentClient;
        }

        $clientId = 'laravel-' . gethostname() . '-' . getmypid();
        $mqtt = new MqttClient($this->host, $this->port, $clientId);
        $mqtt->connect($this->connectionSettings);

        $this->persistentClient = $mqtt;

        return $mqtt;
    }

    public function __destruct()
    {
        if ($this->persistentClient !== null && $this->persistentClient->isConnected()) {
            try {
                $this->persistentClient->disconnect();
            } catch (\Throwable $e) {
            }
        }
    }
}
