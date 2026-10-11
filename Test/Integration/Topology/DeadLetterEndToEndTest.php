<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Test\Integration\Topology;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PHPUnit\Framework\TestCase;

/**
 * Dead lettering through queues created from queue_declaration.xml: messages are
 * published, rejected or expired and must end up in the dead letter queue
 * bound to the dead letter exchange.
 *
 * @magentoAppIsolation enabled
 */
class DeadLetterEndToEndTest extends TestCase
{
    use FixtureTopologyTrait;
    use RabbitMqVhostTrait;

    private const FIXTURE_DIR = 'dead_letter';

    private ?AMQPChannel $channel = null;

    protected function setUp(): void
    {
        $this->createVhost();
    }

    protected function tearDown(): void
    {
        $this->dropVhost();
    }

    public function testRejectedMessageIsDeadLetteredToTheDeclaredExchange(): void
    {
        $this->installTopology(true, self::FIXTURE_DIR);
        $channel = $this->channel();

        $this->publish('mqi.work', 'mqi.work.create', 'payload-1');
        $message = $this->waitForMessage('mqi_work');
        $this->assertNotNull($message, 'message was not routed to mqi_work');
        $this->assertSame('payload-1', $message->getBody());
        $channel->basic_reject($message->getDeliveryTag(), false);

        $deadLettered = $this->waitForMessage('mqi_dead_letter');
        $this->assertNotNull($deadLettered, 'rejected message was not dead lettered');
        $this->assertSame('payload-1', $deadLettered->getBody());
        $death = $deadLettered->get('application_headers')->getNativeData()['x-death'][0];
        $this->assertSame('rejected', $death['reason']);
        $this->assertSame('mqi_work', $death['queue']);
        $this->assertSame('mqi.work', $death['exchange']);
        $this->assertSame(1, $death['count']);
        $channel->basic_ack($deadLettered->getDeliveryTag());
    }

    public function testExpiredMessageIsDeadLetteredToTheDeclaredExchange(): void
    {
        $this->installTopology(true, self::FIXTURE_DIR);

        $this->publish('mqi.work', 'mqi.expiring.create', 'payload-2');

        $deadLettered = $this->waitForMessage('mqi_dead_letter');
        $this->assertNotNull($deadLettered, 'expired message was not dead lettered');
        $this->assertSame('payload-2', $deadLettered->getBody());
        $death = $deadLettered->get('application_headers')->getNativeData()['x-death'][0];
        $this->assertSame('expired', $death['reason']);
        $this->assertSame('mqi_expiring', $death['queue']);
    }

    public function testWithoutDeclarationRejectedMessageIsDropped(): void
    {
        $this->installTopology(false, self::FIXTURE_DIR);
        $channel = $this->channel();

        $this->publish('mqi.work', 'mqi.work.create', 'payload-3');
        $message = $this->waitForMessage('mqi_work');
        $this->assertNotNull($message, 'message was not routed to mqi_work');
        $channel->basic_reject($message->getDeliveryTag(), false);

        $this->assertNull($this->waitForMessage('mqi_dead_letter', 1500), 'message must not be dead lettered');
    }

    /**
     * Delivery tags are per channel, so everything in a test has to use the same one.
     */
    private function channel(): AMQPChannel
    {
        $this->channel ??= $this->connection->channel();

        return $this->channel;
    }

    private function publish(string $exchange, string $routingKey, string $body): void
    {
        $this->channel()->basic_publish(
            new AMQPMessage($body, ['delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]),
            $exchange,
            $routingKey
        );
    }

    /**
     * Polls the queue, dead lettering is asynchronous.
     */
    private function waitForMessage(string $queue, int $timeoutMs = 5000): ?AMQPMessage
    {
        $channel = $this->channel();
        $deadline = microtime(true) + $timeoutMs / 1000;
        do {
            $message = $channel->basic_get($queue);
            if ($message !== null) {
                return $message;
            }
            usleep(100000);
        } while (microtime(true) < $deadline);

        return null;
    }
}
