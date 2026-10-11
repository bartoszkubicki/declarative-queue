<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Xml;

use Magento\Framework\Config\Dom\UrnResolver;
use Magento\Framework\Config\SchemaLocatorInterface;

class SchemaLocator implements SchemaLocatorInterface
{
    private const SCHEMA_URN = 'urn:magento:module:BKubicki_DeclarativeQueue:etc/queue_declaration.xsd';

    private string $schema;

    public function __construct(UrnResolver $urnResolver)
    {
        $this->schema = $urnResolver->getRealPath(self::SCHEMA_URN);
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getPerFileSchema()
    {
        return $this->schema;
    }
}
