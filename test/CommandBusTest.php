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

namespace PhayneTest\ServiceBus;

use Exception;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\Exception\CommandDispatchException;
use Phayne\ServiceBus\Exception\MessageDispatchException;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\Router\CommandRouter;
use PhayneTest\ServiceBus\Mock\CustomMessage;
use PhayneTest\ServiceBus\Mock\DoSomething;
use PhayneTest\ServiceBus\Mock\ErrorProducer;
use PhayneTest\ServiceBus\Mock\MessageHandler;
use PHPUnit\Framework\TestCase;
use Throwable;
use TypeError;

use function count;
use function get_class;

/**
 * Class CommandBusTest
 *
 * @package PhayneTest\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class CommandBusTest extends TestCase
{
    private CommandBus $commandBus;

    protected function setUp(): void
    {
        $this->commandBus = new CommandBus();
    }

    public function testDispatchesAMessageUsingTheDefaultProcess(): void
    {
        $doSomething = new DoSomething(['todo' => 'buy milk']);

        $receivedMessage = null;
        $dispatchEvent = null;
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$receivedMessage, &$dispatchEvent): void {
                $actionEvent->setParam(
                    MessageBus::EVENT_PARAM_MESSAGE_HANDLER,
                    function (DoSomething $doSomething) use (&$receivedMessage): void {
                        $receivedMessage = $doSomething;
                    }
                );

                $dispatchEvent = $actionEvent;
            },
            MessageBus::PRIORITY_ROUTE
        );

        $this->commandBus->dispatch($doSomething);

        $this->assertSame($doSomething, $receivedMessage);
        $this->assertTrue($dispatchEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLED));
    }

    public function testTriggersAllDefinedActionEvents(): void
    {
        $initializeIsTriggered = false;
        $detectMessageNameIsTriggered = false;
        $routeIsTriggered = false;
        $locateHandlerIsTriggered = false;
        $invokeHandlerIsTriggered = false;
        $finalizeIsTriggered = false;

        //Should always be triggered
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$initializeIsTriggered): void {
                $initializeIsTriggered = true;
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        //Should be triggered because we dispatch a message that does not
        //implement Phayne\Common\Messaging\HasMessageName
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$detectMessageNameIsTriggered): void {
                $detectMessageNameIsTriggered = true;
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, 'custom-message');
            },
            MessageBus::PRIORITY_DETECT_MESSAGE_NAME
        );

        //Should be triggered because we did not provide a message-handler yet
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$routeIsTriggered): void {
                $routeIsTriggered = true;
                if ($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === 'custom-message') {
                    //We provide the message handler as a string (service id) to
                    // tell the bus to trigger the locate-handler event
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, 'error-producer');
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        //Should be triggered because we provided the message-handler as string (service id)
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$locateHandlerIsTriggered): void {
                $locateHandlerIsTriggered = true;
                if ($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER) === 'error-producer') {
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, new ErrorProducer());
                }
            },
            MessageBus::PRIORITY_LOCATE_HANDLER
        );

        //Should be triggered because the message-handler is not callable
        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$invokeHandlerIsTriggered): void {
                $invokeHandlerIsTriggered = true;
                $handler = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);
                if ($handler instanceof ErrorProducer) {
                    $handler->throwException($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE));
                }
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );

        //Should always be triggered
        $this->commandBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$finalizeIsTriggered): void {
                if ($actionEvent->param(MessageBus::EVENT_PARAM_EXCEPTION) instanceof \Exception) {
                    $finalizeIsTriggered = true;
                }
            },
            100 // before exception is thrown
        );

        $customMessage = new CustomMessage('I have no further meaning');

        try {
            $this->commandBus->dispatch($customMessage);
        } catch (CommandDispatchException $exception) {
            $this->assertNotNull($exception->getPrevious());
            $this->assertEquals('I can only throw exceptions', $exception->getPrevious()->getMessage());
        }

        $this->assertTrue($initializeIsTriggered);
        $this->assertTrue($detectMessageNameIsTriggered);
        $this->assertTrue($routeIsTriggered);
        $this->assertTrue($locateHandlerIsTriggered);
        $this->assertTrue($invokeHandlerIsTriggered);
        $this->assertTrue($finalizeIsTriggered);
    }

    public function testUsesTheFqcnOfTheMessageIfMessageNameWasNotProvidedAndMessageDoesNotImplementHasMessageName(): void //phpcs:ignore
    {
        $handler = new MessageHandler();

        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $customMessage = new CustomMessage('foo');

        $this->commandBus->dispatch($customMessage);

        $this->assertSame($customMessage, $handler->lastMessage());
    }

    public function testThrowsServiceBusExceptionIfExceptionIsNotHandledByAPlugin(): void
    {
        $this->expectException(CommandDispatchException::class);

        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function () {
                throw new Exception('ka boom');
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $this->commandBus->dispatch('throw it');
    }

    public function testThrowsExceptionIfEventHasNoHandlerAfterItHasBeenSetAndEventWasTriggered(): void
    {
        $this->expectException(CommandDispatchException::class);

        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, null);
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $this->commandBus->dispatch('throw it');
    }

    public function testThrowsExceptionIfMessageWasNotHandled(): void
    {
        $this->expectException(CommandDispatchException::class);

        $this->commandBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, new \stdClass());
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $this->commandBus->dispatch('throw it');
    }

    public function testQueuesNewCommandsAsLongAsItIsDispatching(): void
    {
        $messageHandler = new MessageHandler();

        (new CommandRouter())
            ->route(CustomMessage::class)->to($messageHandler)
            ->route('initial message')->to(function () use ($messageHandler): void {
                $delayedMessage = new CustomMessage('delayed message');

                $this->commandBus->dispatch($delayedMessage);

                $this->assertEquals(0, $messageHandler->invokeCounter());
            })
            ->attachToMessageBus($this->commandBus);

        $this->commandBus->dispatch('initial message');

        $this->assertEquals(1, $messageHandler->invokeCounter());
    }

    public function testPassesQueuedCommandsToCommandDispatchExceptionInCaseOfAnError(): void
    {
        $messageHandler = new MessageHandler();

        (new CommandRouter())
            ->route(CustomMessage::class)->to($messageHandler)
            ->route('initial message')->to(function () use ($messageHandler): void {
                $delayedMessage = new CustomMessage('delayed message');

                $this->commandBus->dispatch($delayedMessage);

                throw new Exception('Ka Boom');
            })
            ->attachToMessageBus($this->commandBus);

        $commandDispatchException = null;

        try {
            $this->commandBus->dispatch('initial message');
        } catch (CommandDispatchException $ex) {
            $commandDispatchException = $ex;
        }

        $this->assertInstanceOf(CommandDispatchException::class, $commandDispatchException);
        $this->assertSame(1, count($commandDispatchException->pendingCommands()));
        $this->assertSame(CustomMessage::class, get_class($commandDispatchException->pendingCommands()[0]));
    }

    public function testAlwaysTriggersFinalizeListenersRegardlessWhetherThePropagationOfTheEventHasBeenStopped(): void
    {
        $this->commandBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event) {
            $event->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, function (): void {
            });
        }, MessageBus::PRIORITY_LOCATE_HANDLER + 1);
        $this->commandBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event): void {
            $event->stopPropagation();
        }, MessageBus::PRIORITY_INVOKE_HANDLER - 1);

        $this->commandBus->attach(MessageBus::EVENT_FINALIZE, function (): void {
        }, 3);
        $finalizeHasBeenCalled = false;
        $this->commandBus->attach(MessageBus::EVENT_FINALIZE, function () use (&$finalizeHasBeenCalled): void {
            $finalizeHasBeenCalled = true;
        }, 2);

        try {
            $this->commandBus->dispatch('a message');
        } catch (Throwable) {
        }

        $this->assertTrue($finalizeHasBeenCalled);
    }
}
