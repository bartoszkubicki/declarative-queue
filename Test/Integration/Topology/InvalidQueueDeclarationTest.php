<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Test\Integration\Topology;

use BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Registry;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

/**
 * queue_declaration.xml is validated against the module schema when files are read.
 *
 * @magentoAppIsolation enabled
 */
class InvalidQueueDeclarationTest extends TestCase
{
    use FixtureTopologyTrait;

    private const HEADER = '<?xml version="1.0"?><config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
        . 'xsi:noNamespaceSchemaLocation="urn:magento:module:BKubicki_DeclarativeQueue:etc/queue_declaration.xsd">';

    public static function invalidDeclarationProvider(): array
    {
        return [
            'queue without name' => ['<queue connection="amqp"/>'],
            'unknown queue attribute' => ['<queue name="q" priority="1"/>'],
            'unknown queue child' => ['<queue name="q"><binding id="b"/></queue>'],
            'not boolean durable' => ['<queue name="q" durable="maybe"/>'],
            'not boolean autoDelete' => ['<queue name="q" autoDelete="sometimes"/>'],
            'argument without name' => ['<queue name="q"><arguments><argument xsi:type="string">v</argument>'
                . '</arguments></queue>'],
            'empty arguments' => ['<queue name="q"><arguments/></queue>'],
            'unknown root child' => ['<exchange name="e"/>'],
            'duplicated queue' => ['<queue name="q" connection="amqp"/><queue name="q" connection="amqp"/>'],
        ];
    }

    /**
     * @dataProvider invalidDeclarationProvider
     */
    public function testInvalidDeclarationIsRejected(string $body): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/invalid|Verify the XML/i');

        $this->createRegistryFromContents(self::HEADER . $body . '</config>')->getAll();
    }

    public function testMalformedXmlIsRejected(): void
    {
        $this->expectException(LocalizedException::class);

        $this->createRegistryFromContents(self::HEADER . '<queue name="q">')->getAll();
    }

    public function testValidDeclarationIsAccepted(): void
    {
        $registry = $this->createRegistryFromContents(
            self::HEADER . '<queue name="q" connection="amqp" durable="false" autoDelete="true">'
            . '<arguments><argument name="x-message-ttl" xsi:type="number">1</argument></arguments></queue></config>'
        );

        $this->assertSame(['q--amqp'], array_keys($registry->getAll()));
    }

    private function createRegistryFromContents(string $xml): Registry
    {
        return $this->createRegistryFromReader(
            $this->createDeclarationReader(
                $this->createFileResolverWithContents('queue_declaration.xml', ['queue_declaration.xml' => $xml])
            )
        );
    }
}
