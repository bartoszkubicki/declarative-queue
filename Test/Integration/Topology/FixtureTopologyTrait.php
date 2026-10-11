<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Test\Integration\Topology;

use BKubicki\DeclarativeQueue\Plugin\Amqp\AddDeclaredQueuesToTopology;
use BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Data as QueueDeclarationData;
use BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Registry;
use BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Xml\Reader as QueueDeclarationReader;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\MessageQueue\Topology\Config\Data as TopologyData;
use Magento\Framework\MessageQueue\Topology\Config\QueueConfigItem\DataMapper;
use Magento\Framework\MessageQueue\Topology\Config\Xml\Reader as TopologyReader;
use Magento\Framework\ObjectManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Builds the real Magento topology config (readers, converters, data mapper, plugins) on top of fixture files, so the
 * module's behaviour can be checked without touching the topology of the installed modules.
 */
trait FixtureTopologyTrait
{
    /**
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    private function objectManager(): ObjectManagerInterface
    {
        return Bootstrap::getObjectManager();
    }

    private function createFileResolver(
        string $fileName,
        ?string $fixture,
        string $fixtureDir = ''
    ): FileResolverInterface {
        $files = $fixture === null
            ? []
            : [$fixture => file_get_contents(__DIR__ . '/../_files/' . $fixtureDir . '/' . $fixture)];

        return $this->createFileResolverWithContents($fileName, $files);
    }

    private function createFileResolverWithContents(
        string $fileName,
        array $files
    ): FileResolverInterface {
        return new class ($fileName, $files) implements FileResolverInterface {
            private string $fileName;

            private array $files;

            public function __construct(string $fileName, array $files)
            {
                $this->fileName = $fileName;
                $this->files = $files;
            }

            public function get($filename, $scope)
            {
                return $filename === $this->fileName ? $this->files : [];
            }
        };
    }

    private function createTopologyData(string $fixtureDir = ''): TopologyData
    {
        $reader = $this->objectManager()->create(
            TopologyReader::class,
            ['fileResolver' => $this->createFileResolver('queue_topology.xml', 'queue_topology.xml', $fixtureDir)]
        );

        return $this->objectManager()->create(
            TopologyData::class,
            ['reader' => $reader, 'cacheId' => 'mqi_test_topology_' . uniqid('', true)]
        );
    }

    private function createRegistry(bool $withDeclaration = true, string $fixtureDir = ''): Registry
    {
        $fileResolver = $this->createFileResolver(
            'queue_declaration.xml',
            $withDeclaration ? 'queue_declaration.xml' : null,
            $fixtureDir
        );

        return $this->createRegistryFromReader($this->createDeclarationReader($fileResolver));
    }

    private function createDeclarationReader(FileResolverInterface $fileResolver): QueueDeclarationReader
    {
        return $this->objectManager()->create(QueueDeclarationReader::class, ['fileResolver' => $fileResolver]);
    }

    private function createRegistryFromReader(QueueDeclarationReader $reader): Registry
    {
        $data = $this->objectManager()->create(
            QueueDeclarationData::class,
            ['reader' => $reader, 'cacheId' => 'mqi_test_declaration_' . uniqid('', true)]
        );

        return new Registry($data);
    }

    /**
     * Data mapper wired the way Magento does it (interceptor with the module's plugin), with fixture config.
     */
    private function createDataMapper(TopologyData $topologyData, Registry $registry): DataMapper
    {
        $this->objectManager()->addSharedInstance(
            new AddDeclaredQueuesToTopology($registry),
            AddDeclaredQueuesToTopology::class
        );

        return $this->objectManager()->create(DataMapper::class, ['configData' => $topologyData]);
    }
}
