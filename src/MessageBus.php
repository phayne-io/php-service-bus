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

namespace Phayne\ServiceBus;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\Messaging\Event\ListenerHandler;
use Phayne\Messaging\Event\PhayneActionEventEmitter;
use Phayne\Messaging\Messaging\HasMessageName;
use React\Promise\PromiseInterface;

use function get_class;
use function gettype;
use function is_object;
use function is_string;

/**
 * Class MessageBus
 *
 * @package Phayne\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class MessageBus
{
    protected readonly ActionEventEmitter $events;

    public const EVENT_DISPATCH = 'dispatch';
    public const EVENT_FINALIZE = 'finalize';

    public const EVENT_PARAM_MESSAGE = 'message';
    public const EVENT_PARAM_MESSAGE_NAME = 'message-name';
    public const EVENT_PARAM_MESSAGE_HANDLER = 'message-handler';
    public const EVENT_PARAM_EXCEPTION = 'exception';
    public const EVENT_PARAM_MESSAGE_HANDLED = 'message-handled';

    public const PRIORITY_INITIALIZE = 400000;
    public const PRIORITY_DETECT_MESSAGE_NAME = 300000;
    public const PRIORITY_ROUTE = 200000;
    public const PRIORITY_LOCATE_HANDLER = 100000;
    public const PRIORITY_PROMISE_REJECT = 1000;
    public const PRIORITY_INVOKE_HANDLER = 0;

    public function __construct(?ActionEventEmitter $events = null)
    {
        $this->events = $events ?: new PhayneActionEventEmitter([self::EVENT_DISPATCH, self::EVENT_FINALIZE]);

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $actionEvent->setParam(self::EVENT_PARAM_MESSAGE_HANDLED, false);
                $message = $actionEvent->param(self::EVENT_PARAM_MESSAGE);

                if ($message instanceof HasMessageName) {
                    $actionEvent->setParam(self::EVENT_PARAM_MESSAGE_NAME, $message->messageName());
                }
            },
            self::PRIORITY_INITIALIZE
        );

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                if ($actionEvent->param(self::EVENT_PARAM_MESSAGE_NAME) === null) {
                    $actionEvent->setParam(
                        self::EVENT_PARAM_MESSAGE_NAME,
                        $this->messageName($actionEvent->param(self::EVENT_PARAM_MESSAGE))
                    );
                }
            },
            self::PRIORITY_DETECT_MESSAGE_NAME
        );

        $this->events->attachListener(
            self::EVENT_FINALIZE,
            function (ActionEvent $actionEvent): void {
                if ($exception = $actionEvent->param(self::EVENT_PARAM_EXCEPTION)) {
                    throw Exception\MessageDispatchException::failed($exception);
                }
            }
        );
    }

    abstract public function dispatch(mixed $message): ?PromiseInterface;

    protected function triggerFinalize(ActionEvent $actionEvent): void
    {
        $actionEvent->setName(self::EVENT_FINALIZE);
        $this->events->dispatch($actionEvent);
    }

    protected function messageName($message): string
    {
        if (is_object($message)) {
            return get_class($message);
        }

        if (is_string($message)) {
            return $message;
        }

        return gettype($message);
    }

    public function attach(string $eventName, callable $listener, int $priority = 0): ListenerHandler
    {
        return $this->events->attachListener($eventName, $listener, $priority);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->events->detachListener($handler);
    }
}
