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

namespace Phayne\ServiceBus\Plugin\InvokeStrategy;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Phayne\ServiceBus\Plugin\OnEventHandler;
use Throwable;

/**
 * Class OnEventStrategy
 *
 * @package Phayne\ServiceBus\Plugin\InvokeStrategy
 * @author Julien Guittard <julien@phayne.com>
 */
class OnEventStrategy extends AbstractPlugin
{
    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                /** @var EventBus $target */
                $target = $actionEvent->target();
                $message = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE);
                $handlers = $actionEvent->param(EventBus::EVENT_PARAM_EVENT_LISTENERS, []);

                /** @var OnEventHandler $handler */
                foreach ($handlers as $handler) {
                    try {
                        $handler->onEvent($message);
                    } catch (Throwable $exception) {
                        if ($target->isCollectingExceptions()) {
                            $target->addCollectedException($exception);
                        } else {
                            throw $exception;
                        }
                    }
                }

                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );
    }
}
