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

use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\Plugin\Router\CommandRouter;

/**
 * Class CommandBusFactory
 *
 * @package Phayne\ServiceBus\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class CommandBusFactory extends AbstractBusFactory
{
    public function __construct(string $configId = 'command_bus')
    {
        parent::__construct($configId);
    }

    protected function busClass(): string
    {
        return CommandBus::class;
    }

    protected function defaultRouterClass(): string
    {
        return CommandRouter::class;
    }
}
