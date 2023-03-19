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

namespace Phayne\ServiceBus\Plugin;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Psr\Container\ContainerInterface;

/**
 * Class ContainerPlugin
 *
 * @package Phayne\ServiceBus\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
class ContainerPlugin extends AbstractPlugin
{
    public function __construct(private readonly ContainerInterface $container)
    {
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $messageHandlerAlias = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);

                if (\is_string($messageHandlerAlias) && $this->container->has($messageHandlerAlias)) {
                    $actionEvent->setParam(
                        MessageBus::EVENT_PARAM_MESSAGE_HANDLER,
                        $this->container->get($messageHandlerAlias)
                    );
                }

                // for event bus only
                $currentEventListeners = $actionEvent->param(EventBus::EVENT_PARAM_EVENT_LISTENERS, []);
                $newEventListeners = [];

                foreach ($currentEventListeners as $key => $eventListenerAlias) {
                    if (\is_string($eventListenerAlias) && $this->container->has($eventListenerAlias)) {
                        $newEventListeners[$key] = $this->container->get($eventListenerAlias);
                    }
                }

                // merge array whilst preserving numeric keys and giving priority to newEventListeners
                $actionEvent->setParam(
                    EventBus::EVENT_PARAM_EVENT_LISTENERS,
                    $newEventListeners + $currentEventListeners
                );
            },
            MessageBus::PRIORITY_LOCATE_HANDLER
        );
    }
}
