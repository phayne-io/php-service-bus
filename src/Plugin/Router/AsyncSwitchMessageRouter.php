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

use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\Async\AsyncMessage;
use Phayne\ServiceBus\Async\MessageProducer;
use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Phayne\ServiceBus\QueryBus;

/**
 * Class AsyncSwitchMessageRouter
 *
 * @package Phayne\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class AsyncSwitchMessageRouter extends AbstractPlugin implements MessageBusRouterPlugin
{
    public function __construct(
        private readonly MessageBusRouterPlugin $router,
        private readonly MessageProducer $asyncMessageProducer
    ) {
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        $message = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE);

        //if the message is marked with AsyncMessage, but had not yet been sent via async then sent to async producer
        if (
            $message instanceof AsyncMessage &&
            ! (
                isset($message->metadata()['handled-async']) &&
                $message->metadata()['handled-async'] === true
            )
        ) {
            //apply metadata, needed so we can identify that the message has already been sent via the async producer
            $message = $message->withAddedMetadata('handled-async', true);

            // update ActionEvent
            $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE, $message);

            if ($actionEvent->target() instanceof CommandBus || $actionEvent->target() instanceof QueryBus) {
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $this->asyncMessageProducer);
            } elseif ($actionEvent->target() instanceof EventBus) {
                //Target is an event-bus so we set message producer as the only listener of the message
                $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [$this->asyncMessageProducer]);
            } else {
                throw new RuntimeException(
                    'Unexpected bus implementation. This plugin is only compatible with standard
                     CommandBus, QueryBus and EventBus implementations.'
                );
            }

            return;
        }

        // pass ActionEvent to decorated router
        $this->router->onRouteMessage($actionEvent);
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
