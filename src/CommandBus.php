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

use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Event\ActionEventEmitter;
use Phayne\ServiceBus\Exception\CommandDispatchException;
use React\Promise\PromiseInterface;
use Throwable;

use function array_shift;
use function is_callable;
use function sprintf;

/**
 * Class CommandBus
 *
 * @package Phayne\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class CommandBus extends MessageBus
{
    private array $commandQueue = [];

    private bool $isDispatching = false;

    public function __construct(?ActionEventEmitter $events = null)
    {
        parent::__construct($events);

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $commandHandler = $actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLER);

                if (is_callable($commandHandler)) {
                    $command = $actionEvent->param(self::EVENT_PARAM_MESSAGE);
                    $commandHandler($command);
                    $actionEvent->setParam(self::EVENT_PARAM_MESSAGE_HANDLED, true);
                }
            },
            self::PRIORITY_INVOKE_HANDLER
        );

        $this->events->attachListener(
            self::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                if ($actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLER) === null) {
                    throw new InvalidArgumentException(sprintf(
                        'CommandBus was not able to identify a CommandHandler for command %s',
                        $this->messageName($actionEvent->param(self::EVENT_PARAM_MESSAGE))
                    ));
                }
            },
            self::PRIORITY_LOCATE_HANDLER
        );
    }

    public function dispatch(mixed $message): ?PromiseInterface
    {
        $this->commandQueue[] = $message;

        if (! $this->isDispatching) {
            $this->isDispatching = true;

            $actionEventEmitter = $this->events;

            try {
                while ($command = array_shift($this->commandQueue)) {
                    $actionEvent = $actionEventEmitter->getNewActionEvent(
                        self::EVENT_DISPATCH,
                        $this,
                        [
                            self::EVENT_PARAM_MESSAGE => $command,
                        ]
                    );

                    try {
                        $actionEventEmitter->dispatch($actionEvent);

                        if (! $actionEvent->param(self::EVENT_PARAM_MESSAGE_HANDLED)) {
                            throw new RuntimeException(sprintf(
                                'Command %s was not handled',
                                $this->messageName($command)
                            ));
                        }
                    } catch (Throwable $exception) {
                        $actionEvent->setParam(self::EVENT_PARAM_EXCEPTION, $exception);
                    } finally {
                        $actionEvent->stopPropagation(false);
                        $this->triggerFinalize($actionEvent);
                    }
                }
                $this->isDispatching = false;
            } catch (Throwable $e) {
                $this->isDispatching = false;
                throw CommandDispatchException::wrap($e, $this->commandQueue);
            }
        }

        return null;
    }
}
