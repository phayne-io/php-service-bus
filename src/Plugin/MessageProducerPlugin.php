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
use Phayne\ServiceBus\Async\MessageProducer;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;

/**
 * Class MessageProducerPlugin
 *
 * @package Phayne\ServiceBus\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
class MessageProducerPlugin extends AbstractPlugin
{
    public function __construct(private readonly MessageProducer $messageProducer)
    {
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $event): void {
                $bus = $event->target();

                if ($bus instanceof EventBus) {
                    $listeners = $event->param(EventBus::EVENT_PARAM_EVENT_LISTENERS, []);
                    $listeners[] = $this->messageProducer;
                    $event->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, $listeners);
                } else {
                    $event->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $this->messageProducer);
                }
            },
            MessageBus::PRIORITY_INITIALIZE
        );
    }
}
