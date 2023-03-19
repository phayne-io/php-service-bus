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
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;

use function array_merge;
use function get_class;
use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * Class EventRouter
 *
 * @package Phayne\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class EventRouter extends AbstractPlugin implements MessageBusRouterPlugin
{
    protected array $eventMap = [];

    protected ?string $tmpEventName = null;

    public function __construct(array $eventMap = null)
    {
        if (null === $eventMap) {
            return;
        }

        foreach ($eventMap as $eventName => $listeners) {
            if (is_string($listeners) || is_object($listeners) || is_callable($listeners)) {
                $listeners = [$listeners];
            }

            $this->route($eventName);

            foreach ($listeners as $listener) {
                $this->to($listener);
            }
        }
    }

    public function route(string $eventName): EventRouter
    {
        Assertion::notEmpty($eventName);

        if (null !== $this->tmpEventName && empty($this->eventMap[$this->tmpEventName])) {
            throw new RuntimeException(sprintf('event %s is not mapped to a listener.', $this->tmpEventName));
        }

        $this->tmpEventName = $eventName;

        if (! isset($this->eventMap[$this->tmpEventName])) {
            $this->eventMap[$this->tmpEventName] = [];
        }

        return $this;
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName) || ! isset($this->eventMap[$messageName])) {
            return;
        }

        $listeners = $actionEvent->param(EventBus::EVENT_PARAM_EVENT_LISTENERS, []);

        $listeners = array_merge($listeners, $this->eventMap[$messageName]);

        $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, $listeners);
    }

    public function to(string | object | callable $eventListener): EventRouter
    {
        if (null === $this->tmpEventName) {
            throw new RuntimeException(sprintf(
                'Cannot map listener %s to an event. Please use method route before calling method to',
                is_object($eventListener)
                    ? get_class($eventListener)
                    : (is_string($eventListener) ? $eventListener : gettype($eventListener))
            ));
        }

        $this->eventMap[$this->tmpEventName][] = $eventListener;

        return $this;
    }

    public function andTo(string | object | callable $eventListener): EventRouter
    {
        return $this->to($eventListener);
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
