<?php

declare(strict_types=1);

namespace BKubicki\DeclarativeQueue\Topology\QueueDeclaration;

class Registry
{
    public function __construct(private readonly Data $data)
    {
    }

    public function getAll(): array
    {
        return (array)$this->data->get();
    }

    public function isDeclared(string $queueName): bool
    {
        foreach ($this->getAll() as $queue) {
            if ($queue['name'] === $queueName) {
                return true;
            }
        }

        return false;
    }
}
