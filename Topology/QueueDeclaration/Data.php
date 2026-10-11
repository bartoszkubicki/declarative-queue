<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Topology\QueueDeclaration;

use BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Xml\Reader;
use Magento\Framework\Config\CacheInterface;
use Magento\Framework\Config\Data as ConfigData;
use Magento\Framework\Serialize\SerializerInterface;

class Data extends ConfigData
{
    public function __construct(
        Reader $reader,
        CacheInterface $cache,
        $cacheId = 'declarative_queue_declaration_config_cache',
        ?SerializerInterface $serializer = null
    ) {
        parent::__construct($reader, $cache, $cacheId, $serializer);
    }
}
