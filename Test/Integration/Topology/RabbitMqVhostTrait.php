<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Test\Integration\Topology;

use Magento\Framework\Amqp\Config as AmqpConfig;
use Magento\Framework\Amqp\ConfigPool;
use Magento\Framework\Amqp\TopologyInstaller;
use Magento\Framework\MessageQueue\Topology\Config as TopologyConfig;
use Magento\Framework\MessageQueue\Topology\Config\ExchangeConfigItem\Iterator as ExchangeIterator;
use Magento\Framework\MessageQueue\Topology\Config\QueueConfigItem\Iterator as QueueIterator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;

use function sprintf;

/**
 * Throwaway RabbitMQ virtual host for a test, together with an installer running Magento's TopologyInstaller in it.
 *
 * Connection is taken from MQI_AMQP_HOST / MQI_AMQP_PORT / MQI_AMQP_MANAGEMENT_PORT / MQI_AMQP_USER /
 * MQI_AMQP_PASSWORD, defaults match the Warden `rabbitmq` service. Tests are skipped when the broker is unreachable.
 * Requires FixtureTopologyTrait in the same class.
 */
trait RabbitMqVhostTrait
{
    private string $vhost = '';

    private ?AMQPStreamConnection $connection = null;

    private function createVhost(): void
    {
        $this->vhost = 'mqi_test_' . bin2hex(random_bytes(4));
        $status = $this->management('PUT', '/vhosts/' . rawurlencode($this->vhost));
        if ($status[0] === 0 || $status[0] >= 400) {
            $this->markTestSkipped('RabbitMQ management API is not reachable: HTTP ' . $status[0]);
        }
        $this->management(
            'PUT',
            '/permissions/' . rawurlencode($this->vhost) . '/' . rawurlencode($this->env('USER', 'guest')),
            ['configure' => '.*', 'write' => '.*', 'read' => '.*']
        );
        $this->connection = new AMQPStreamConnection(
            $this->env('HOST', 'rabbitmq'),
            (int)$this->env('PORT', '5672'),
            $this->env('USER', 'guest'),
            $this->env('PASSWORD', 'guest'),
            $this->vhost
        );
    }

    private function dropVhost(): void
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }
        if ($this->vhost !== '') {
            $this->management('DELETE', '/vhosts/' . rawurlencode($this->vhost));
        }
    }

    private function installTopology(bool $withDeclaration, string $fixtureDir = ''): void
    {
        $om = $this->objectManager();
        $topologyData = $this->createTopologyData($fixtureDir);
        $mapper = $this->createDataMapper($topologyData, $this->createRegistry($withDeclaration, $fixtureDir));
        $topologyConfig = $om->create(
            TopologyConfig::class,
            [
                'exchangeIterator' => $om->create(ExchangeIterator::class, ['configData' => $topologyData]),
                'queueIterator' => $om->create(QueueIterator::class, ['configData' => $mapper]),
            ]
        );

        $channel = $this->connection->channel();
        $amqpConfig = $this->createMock(AmqpConfig::class);
        $amqpConfig->method('getChannel')->willReturn($channel);
        $configPool = $this->createMock(ConfigPool::class);
        $configPool->method('get')->willReturn($amqpConfig);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $om->create(
            TopologyInstaller::class,
            ['topologyConfig' => $topologyConfig, 'configPool' => $configPool, 'logger' => $logger]
        )->install();
    }

    /**
     * RabbitMQ 4 reports the default queue type as an argument of every queue.
     */
    private function withoutBrokerDefaults(array $arguments): array
    {
        unset($arguments['x-queue-type']);

        return $arguments;
    }

    private function managementGet(string $path): array
    {
        [$status, $body] = $this->management('GET', $path);
        $this->assertSame(200, $status, 'GET ' . $path . ' ' . json_encode($body));

        return $body;
    }

    private function management(string $method, string $path, ?array $payload = null): array
    {
        $curl = curl_init(
            sprintf('http://%s:%s/api%s', $this->env('HOST', 'rabbitmq'), $this->env('MANAGEMENT_PORT', '15672'), $path)
        );
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_USERPWD => $this->env('USER', 'guest') . ':' . $this->env('PASSWORD', 'guest'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => ['content-type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload ?? new \stdClass(), JSON_THROW_ON_ERROR),
        ]);
        $response = (string)curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return [$status, $response === '' ? null : json_decode($response, true, 512, JSON_THROW_ON_ERROR)];
    }

    private function env(string $name, string $default): string
    {
        $value = getenv('MQI_AMQP_' . $name);

        return $value === false || $value === '' ? $default : $value;
    }
}
