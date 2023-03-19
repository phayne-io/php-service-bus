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

use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\ServiceBus\Exception\MessageDispatchException;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use Throwable;

use function is_callable;
use function sprintf;

/**
 * Class QueryBus
 *
 * @package Phayne\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class QueryBus extends MessageBus
{
    public const EVENT_PARAM_PROMISE = 'query-promise';
    public const EVENT_PARAM_DEFERRED = 'query-deferred';

    public function __construct(?ActionEventEmitter $actionEventEmitter = null)
    {
        parent::__construct($actionEventEmitter);

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $finder = $actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLER);

                if (is_callable($finder)) {
                    $query = $actionEvent->param(self::EVENT_PARAM_MESSAGE);
                    $deferred = $actionEvent->param(self::EVENT_PARAM_DEFERRED);
                    $finder($query, $deferred);
                    $actionEvent->setParam(self::EVENT_PARAM_MESSAGE_HANDLED, true);
                }
            },
            self::PRIORITY_INVOKE_HANDLER
        );

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                if ($actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLER) === null) {
                    throw new RuntimeException(sprintf(
                        'QueryBus was not able to identify a Finder for query "%s"',
                        $this->messageName($actionEvent->param(self::EVENT_PARAM_MESSAGE))
                    ));
                }
            },
            self::PRIORITY_LOCATE_HANDLER
        );

        $this->events->attachListener(
            self::EVENT_FINALIZE,
            function (ActionEvent $actionEvent): void {
                if ($exception = $actionEvent->param(self::EVENT_PARAM_EXCEPTION)) {
                    $deferred = $actionEvent->param(self::EVENT_PARAM_DEFERRED);
                    $deferred->reject(MessageDispatchException::failed($exception));
                    $actionEvent->setParam(self::EVENT_PARAM_EXCEPTION, null);
                }
            },
            self::PRIORITY_PROMISE_REJECT
        );
    }

    public function dispatch(mixed $message): ?PromiseInterface
    {
        $deferred = new Deferred();

        $actionEventEmitter = $this->events;

        $actionEvent = $actionEventEmitter->getNewActionEvent(
            self::EVENT_DISPATCH,
            $this,
            [
                self::EVENT_PARAM_MESSAGE => $message,
                self::EVENT_PARAM_DEFERRED => $deferred,
                self::EVENT_PARAM_PROMISE => $deferred->promise(),
            ]
        );

        try {
            $actionEventEmitter->dispatch($actionEvent);

            if (! $actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLED)) {
                throw new RuntimeException(sprintf('Query %s was not handled', $this->messageName($message)));
            }
        } catch (Throwable $exception) {
            $actionEvent->setParam(self::EVENT_PARAM_EXCEPTION, $exception);
        } finally {
            $actionEvent->stopPropagation(false);
            $this->triggerFinalize($actionEvent);
        }

        return $actionEvent->param(self::EVENT_PARAM_PROMISE);
    }
}
