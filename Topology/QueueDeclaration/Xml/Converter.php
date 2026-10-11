<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Topology\QueueDeclaration\Xml;

use DOMDocument;
use DOMNode;
use Magento\Framework\Config\ConverterInterface;
use Magento\Framework\Config\Converter\Dom\Flat as FlatConverter;
use Magento\Framework\Config\Dom\ArrayNodeConfig;
use Magento\Framework\Config\Dom\NodePathMatcher;
use Magento\Framework\Data\Argument\InterpreterInterface;
use Magento\Framework\MessageQueue\DefaultValueProvider;
use Magento\Framework\Stdlib\BooleanUtils;

class Converter implements ConverterInterface
{
    private ?FlatConverter $converter = null;

    public function __construct(
        private readonly BooleanUtils $booleanUtils,
        private readonly InterpreterInterface $argumentInterpreter,
        private readonly DefaultValueProvider $defaultValue
    ) {
    }

    public function convert($source)
    {
        $result = [];
        /** @var DOMDocument $source */
        foreach ($source->getElementsByTagName('queue') as $queue) {
            $name = $this->getAttributeValue($queue, 'name');
            $connection = $this->getAttributeValue($queue, 'connection', $this->defaultValue->getConnection());

            $arguments = [];
            foreach ($queue->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE && $node->nodeName === 'arguments') {
                    $arguments = $this->processArguments($node);
                }
            }

            $result[$name . '--' . $connection] = [
                'name' => $name,
                'connection' => $connection,
                'durable' => $this->booleanUtils->toBoolean($this->getAttributeValue($queue, 'durable', true)),
                'autoDelete' => $this->booleanUtils->toBoolean($this->getAttributeValue($queue, 'autoDelete', false)),
                'arguments' => $arguments,
            ];
        }

        return $result;
    }

    private function processArguments(DOMNode $node): array
    {
        $output = [];
        foreach ($node->childNodes as $argumentNode) {
            if ($argumentNode->nodeType !== XML_ELEMENT_NODE || $argumentNode->nodeName !== 'argument') {
                continue;
            }
            $argumentName = $argumentNode->attributes->getNamedItem('name')->nodeValue;
            $argumentData = $this->getConverter()->convert($argumentNode, 'argument');
            $output[$argumentName] = $this->argumentInterpreter->evaluate($argumentData);
        }

        return $output;
    }

    private function getConverter(): FlatConverter
    {
        if ($this->converter === null) {
            $this->converter = new FlatConverter(
                new ArrayNodeConfig(new NodePathMatcher(), ['argument(/item)+' => 'name'])
            );
        }

        return $this->converter;
    }

    private function getAttributeValue(DOMNode $node, string $attributeName, $default = null)
    {
        $item = $node->attributes->getNamedItem($attributeName);

        return $item ? $item->nodeValue : $default;
    }
}
