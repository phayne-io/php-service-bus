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
use Phayne\Messaging\Messaging\MessageFactory;
use Phayne\ServiceBus\MessageBus;

use function array_key_exists;
use function is_array;

/**
 * Class MessageFactoryPlugin
 *
 * @package Phayne\ServiceBus\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
class MessageFactoryPlugin extends AbstractPlugin
{
    public function __construct(private readonly MessageFactory $messageFactory)
    {
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $message = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE);

                if (! is_array($message) || ! array_key_exists('message_name', $message)) {
                    return;
                }

                $messageName = $message['message_name'];
                unset($message['message_name']);

                $message = $this->messageFactory->createMessageFromArray($messageName, $message);

                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE, $message);
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, $messageName);
            },
            MessageBus::PRIORITY_INITIALIZE
        );
    }
}
