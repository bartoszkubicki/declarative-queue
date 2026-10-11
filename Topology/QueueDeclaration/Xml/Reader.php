<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Xml;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\Reader\Filesystem;
use Magento\Framework\Config\ValidationStateInterface;

class Reader extends Filesystem
{
    protected $_idAttributes = [
        '/config/queue' => ['name', 'connection'],
        '/config/queue/arguments/argument' => 'name',
        '/config/queue/arguments/argument(/item)+' => 'name',
    ];

    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocator $schemaLocator,
        ValidationStateInterface $validationState,
        $fileName = 'queue_declaration.xml',
        $idAttributes = [],
        $domDocumentClass = Dom::class,
        $defaultScope = 'global'
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope
        );
    }
}
