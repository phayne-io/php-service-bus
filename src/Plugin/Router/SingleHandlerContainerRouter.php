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

namespace Phayne\ServiceBus\Plugin\Router;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Psr\Container\ContainerInterface;

/**
 * Class SingleHandlerContainerRouter
 *
 * @package Phayne\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class SingleHandlerContainerRouter extends AbstractPlugin implements MessageBusRouterPlugin
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if ($this->container->has($messageName)) {
            $actionEvent->setParam(
                MessageBus::EVENT_PARAM_MESSAGE_HANDLER,
                $this->container->get($messageName)
            );
        }
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onRouteMessage'],
            MessageBus::PRIORITY_ROUTE
        );
    }
}
