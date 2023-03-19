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

use Assert\Assertion;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;

use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Class SingleHandlerRouter
 *
 * @package Phayne\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class SingleHandlerRouter extends AbstractPlugin implements MessageBusRouterPlugin
{
    protected array $messageMap = [];

    protected ?string $tmpMessageName = null;

    public function __construct(array $messageMap = null)
    {
        if (null === $messageMap) {
            return;
        }

        foreach ($messageMap as $messageName => $handler) {
            $this->route($messageName)->to($handler);
        }
    }

    public function route(string $messageName): SingleHandlerRouter
    {
        Assertion::notEmpty($messageName);

        if (null !== $this->tmpMessageName) {
            throw new RuntimeException(sprintf('Message "%s" is not mapped to a handler.', $this->tmpMessageName));
        }

        $this->tmpMessageName = $messageName;

        return $this;
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onRouteMessage'],
            MessageBus::PRIORITY_ROUTE
        );
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName) || ! isset($this->messageMap[$messageName])) {
            return;
        }

        $handler = $this->messageMap[$messageName];
        $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
    }

    public function to(string | object | callable $messageHandler): SingleHandlerRouter
    {
        if (null === $this->tmpMessageName) {
            throw new InvalidArgumentException(sprintf(
                'Cannot map handler "%s" to a message. Please use method route before calling method to',
                is_object($messageHandler)
                    ? get_class($messageHandler)
                    : (is_string($messageHandler) ? $messageHandler : gettype($messageHandler))
            ));
        }

        $this->messageMap[$this->tmpMessageName] = $messageHandler;
        $this->tmpMessageName = null;

        return $this;
    }
}
