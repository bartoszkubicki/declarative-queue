<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Test\Integration\Topology;

use PHPUnit\Framework\TestCase;

/**
 * Installs the fixture topology into a RabbitMQ virtual host created for the test
 * with Magento's own installers and checks the result through the management API.
 *
 * @magentoAppIsolation enabled
 */
class BrokerTopologyInstallTest extends TestCase
{
    use FixtureTopologyTrait;
    use RabbitMqVhostTrait;

    protected function setUp(): void
    {
        $this->createVhost();
    }

    protected function tearDown(): void
    {
        $this->dropVhost();
    }

    public function testDeclaredQueueIsCreatedWithItsOwnArgumentsAndBindingKeepsBindingArguments(): void
    {
        $this->installTopology(true);

        $queue = $this->managementGet('/queues/' . rawurlencode($this->vhost) . '/mqi_declared');
        $this->assertSame('mqi.dead_letter', $queue['arguments']['x-dead-letter-exchange']);
        $this->assertEquals(5000, $queue['arguments']['x-message-ttl']);
        $this->assertArrayNotHasKey('binding-only', $queue['arguments']);

        $binding = $this->getBinding('mqi_declared');
        $this->assertSame('mqi.declared.#', $binding['routing_key']);
        $this->assertSame(['binding-only' => 'on-binding'], $binding['arguments']);
    }

    public function testQueueWithoutDeclarationIsCreatedPlainWhileBindingKeepsItsArguments(): void
    {
        $this->installTopology(true);

        $queue = $this->managementGet('/queues/' . rawurlencode($this->vhost) . '/mqi_undeclared');
        $this->assertSame([], $this->withoutBrokerDefaults($queue['arguments']));

        $binding = $this->getBinding('mqi_undeclared');
        $this->assertEquals(1000, $binding['arguments']['x-message-ttl']);
        $this->assertSame('mqi.dead_letter', $binding['arguments']['x-dead-letter-exchange']);
    }

    public function testDeclaredQueueWithoutBindingIsCreated(): void
    {
        $this->installTopology(true);

        $queue = $this->managementGet('/queues/' . rawurlencode($this->vhost) . '/mqi_unbound');
        $this->assertFalse($queue['durable']);
        $this->assertTrue($queue['auto_delete']);
    }

    public function testWithoutDeclarationFileAllQueuesAreCreatedPlain(): void
    {
        $this->installTopology(false);

        foreach (['mqi_declared', 'mqi_undeclared'] as $queueName) {
            $queue = $this->managementGet('/queues/' . rawurlencode($this->vhost) . '/' . $queueName);
            $this->assertSame([], $this->withoutBrokerDefaults($queue['arguments']), $queueName);
        }
    }

    private function getBinding(string $queueName): array
    {
        $bindings = $this->managementGet(
            '/bindings/' . rawurlencode($this->vhost) . '/e/mqi.entity/q/' . $queueName
        );
        $this->assertCount(1, $bindings, 'exactly one binding expected for ' . $queueName);

        return $bindings[0];
    }
}
