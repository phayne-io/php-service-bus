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
use Phayne\ServiceBus\Exception\EventListenerException;
use React\Promise\PromiseInterface;
use Throwable;

use function Phayne\Phunctional\each;

/**
 * Class EventBus
 *
 * @package Phayne\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class EventBus extends MessageBus
{
    public const EVENT_PARAM_EVENT_LISTENERS = 'event-listeners';

    protected bool $collectExceptions = false;

    protected array $collectedExceptions = [];

    public function __construct(?ActionEventEmitter $actionEventEmitter = null)
    {
        parent::__construct($actionEventEmitter);

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $event = $actionEvent->param(self::EVENT_PARAM_MESSAGE);
                $handled = false;

                foreach (
                    array_filter(
                        $actionEvent->param(self::EVENT_PARAM_EVENT_LISTENERS, []),
                        'is_callable'
                    ) as $eventListener
                ) {
                    try {
                        $eventListener($event);
                        $handled = true;
                    } catch (Throwable $exception) {
                        if ($this->collectExceptions) {
                            $this->collectedExceptions[] = $exception;
                        } else {
                            throw $exception;
                        }
                    }
                }

                if ($handled) {
                    $actionEvent->setParam(self::EVENT_PARAM_MESSAGE_HANDLED, true);
                }
            },
            self::PRIORITY_INVOKE_HANDLER
        );

        $this->events->attachListener(
            self::EVENT_FINALIZE,
            function (ActionEvent $actionEvent): void {
                $target = $actionEvent->target();

                if (empty($target->collectedExceptions)) {
                    return;
                }

                $exceptions = $target->collectedExceptions;
                $target->collectedExceptions = [];

                $actionEvent->setParam(
                    MessageBus::EVENT_PARAM_EXCEPTION,
                    EventListenerException::collected(...$exceptions)
                );
            },
            1000
        );
    }

    public function dispatch(mixed $message): ?PromiseInterface
    {
        $actionEventEmitter = $this->events;

        $actionEvent = $actionEventEmitter->getNewActionEvent(
            self::EVENT_DISPATCH,
            $this,
            [
                self::EVENT_PARAM_MESSAGE => $message,
            ]
        );

        try {
            $actionEventEmitter->dispatch($actionEvent);
        } catch (Throwable $exception) {
            $actionEvent->setParam(self::EVENT_PARAM_EXCEPTION, $exception);
        } finally {
            $actionEvent->stopPropagation(false);
            $this->triggerFinalize($actionEvent);
        }

        return null;
    }

    public function enableCollectExceptions(): void
    {
        $this->collectExceptions = true;
    }

    public function disableCollectExceptions(): void
    {
        $this->collectExceptions = false;
    }

    public function isCollectionExceptions(): bool
    {
        return $this->collectExceptions;
    }

    public function addCollectedException(Throwable $e): void
    {
        $this->collectedExceptions[] = $e;
    }
}
