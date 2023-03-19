<?php

/**
 * This file is part of phayne-io/php-service-bus package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @see       https://github.com/phayne-io/php-service-bus for the canonical source repository
 * @copyright Copyright (c) 2023 Phayne. (https://phayne.io)
 */

declare(strict_types=1);

namespace Phayne\ServiceBus\Container;

use Phayne\ServiceBus\Plugin\Router\QueryRouter;
use Phayne\ServiceBus\QueryBus;

/**
 * Class QueryBusFactory
 *
 * @package Phayne\ServiceBus\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class QueryBusFactory extends AbstractBusFactory
{
    public function __construct(string $configId = 'query_bus')
    {
        parent::__construct($configId);
    }

    protected function busClass(): string
    {
        return QueryBus::class;
    }

    protected function defaultRouterClass(): string
    {
        return QueryRouter::class;
    }
}
