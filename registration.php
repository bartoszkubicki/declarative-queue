<?php

declare(strict_types=1);


use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'BKubicki_DeclarativeQueue',
    __DIR__
);
