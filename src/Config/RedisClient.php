<?php

namespace App\Config;

use Predis\Client;

class RedisClient
{
    private static ?RedisClient $instance = null;
    private Client $client;

    private function __construct()
    {
        $config = Config::getInstance();

        $params = [
            'scheme' => 'tcp',
            'host' => $config->get('redis.host'),
            'port' => $config->get('redis.port'),
        ];

        if ($password = $config->get('redis.password')) {
            $params['password'] = $password;
        }

        $this->client = new Client($params);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}
