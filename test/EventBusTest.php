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
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\Exception\EventListenerException;
use Phayne\ServiceBus\Exception\MessageDispatchException;
use Phayne\ServiceBus\MessageBus;
use PhayneTest\ServiceBus\Mock\AutoErrorProducer;
use PhayneTest\ServiceBus\Mock\CustomMessage;
use PhayneTest\ServiceBus\Mock\ErrorProducer;
use PhayneTest\ServiceBus\Mock\MessageHandler;
use PhayneTest\ServiceBus\Mock\SomethingDone;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Class EventBusTest
 *
 * @package PhayneTest\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class EventBusTest extends TestCase
{
    private EventBus $eventBus;

    protected function setUp(): void
    {
        $this->eventBus = new EventBus();
    }

    public function testDispatchesAMessageUsingTheDefaultProcess(): void
    {
        $somethingDone = new SomethingDone(['done' => 'bought milk']);

        $receivedMessage = null;
        $dispatchEvent = null;
        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$receivedMessage, &$dispatchEvent): void {
                $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [
                    function (SomethingDone $somethingDone) use (&$receivedMessage): void {
                        $receivedMessage = $somethingDone;
                    },
                ]);

                $dispatchEvent = $actionEvent;
            },
            MessageBus::PRIORITY_ROUTE
        );

        $this->eventBus->dispatch($somethingDone);

        $this->assertSame($somethingDone, $receivedMessage);
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
        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$initializeIsTriggered): void {
                $initializeIsTriggered = true;
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        //Should be triggered because we dispatch a message that does not
        //implement Phayne\Common\Messaging\HasMessageName
        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$detectMessageNameIsTriggered): void {
                $detectMessageNameIsTriggered = true;
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, 'custom-message');
            },
            MessageBus::PRIORITY_DETECT_MESSAGE_NAME
        );

        //Should be triggered because we did not provide a message-handler yet
        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$routeIsTriggered): void {
                $routeIsTriggered = true;
                if ($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === 'custom-message') {
                    //We provide the message handler as a string (service id) to
                    //tell the bus to trigger the locate-handler event
                    $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, ['error-producer']);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        //Should be triggered because we provided the message-handler as string (service id)
        $this->eventBus->attach(
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
        $this->eventBus->attach(
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
        $this->eventBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$finalizeIsTriggered): void {
                $finalizeIsTriggered = true;
            }
        );

        $customMessage = new CustomMessage('I have no further meaning');

        $this->eventBus->dispatch($customMessage);

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

        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [$handler]);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $customMessage = new CustomMessage('foo');

        $this->eventBus->dispatch($customMessage);

        $this->assertSame($customMessage, $handler->lastMessage());
    }

    public function testThrowsServiceBusExceptionIfExceptionIsNotHandledByAPlugin(): void
    {
        $this->expectException(MessageDispatchException::class);

        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function () {
                throw new Exception('ka boom');
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $this->eventBus->dispatch('throw it');
    }

    public function testInvokesAllListeners(): void
    {
        $handler = new MessageHandler();

        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [$handler, $handler]);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $customMessage = new CustomMessage('foo');

        $this->eventBus->dispatch($customMessage);

        $this->assertSame($customMessage, $handler->lastMessage());
        $this->assertEquals(2, $handler->invokeCounter());
    }

    public function testStopsByDefaultIfListenerThrowsAnException(): void
    {
        $handler = new MessageHandler();
        $errorProducer = new AutoErrorProducer();
        $finalizeIsTriggered = false;

        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler, $errorProducer): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [$handler, $errorProducer,  $handler]);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $this->eventBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$finalizeIsTriggered) {
                $finalizeIsTriggered = true;
                $actionEvent->setParam(MessageBus::EVENT_PARAM_EXCEPTION, null);
            },
            1000
        );

        $customMessage = new CustomMessage('foo');

        $this->eventBus->dispatch($customMessage);

        $this->assertTrue($finalizeIsTriggered);
        $this->assertSame($customMessage, $handler->lastMessage());
        $this->assertEquals(1, $handler->invokeCounter());
    }

    public function testCollectsExceptionsIfModeIsEnabled(): void
    {
        $handler = new MessageHandler();
        $errorProducer = new AutoErrorProducer();
        $finalizeIsTriggered = false;
        $listenerExceptions = [];

        $this->eventBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler, $errorProducer): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, [$handler, $errorProducer, $handler]);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $this->eventBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$finalizeIsTriggered, &$listenerExceptions) {
                $finalizeIsTriggered = true;
                if ($exception = $actionEvent->param(MessageBus::EVENT_PARAM_EXCEPTION)) {
                    if ($exception instanceof EventListenerException) {
                        $listenerExceptions = $exception->listenerExceptions();
                    }
                }
                $actionEvent->setParam(MessageBus::EVENT_PARAM_EXCEPTION, null);
            },
            1000
        );

        $this->eventBus->enableCollectExceptions();

        $customMessage = new CustomMessage('foo');

        $this->eventBus->dispatch($customMessage);

        $this->assertTrue($finalizeIsTriggered);
        $this->assertCount(1, $listenerExceptions);
        $this->assertInstanceOf(Throwable::class, $listenerExceptions[0]);
        $this->assertSame($customMessage, $handler->lastMessage());
        $this->assertEquals(2, $handler->invokeCounter());
    }

    public function testAlwaysTriggersFinalizeListenersRegardlessWhetherThePropagationOfTheEventHasBeenStopped(): void
    {
        $this->eventBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event) {
            $event->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, function (): void {
            });
        }, MessageBus::PRIORITY_LOCATE_HANDLER + 1);
        $this->eventBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event): void {
            $event->stopPropagation();
        }, MessageBus::PRIORITY_INVOKE_HANDLER - 1);

        $this->eventBus->attach(MessageBus::EVENT_FINALIZE, function (): void {
        }, 3);
        $finalizeHasBeenCalled = false;
        $this->eventBus->attach(MessageBus::EVENT_FINALIZE, function () use (&$finalizeHasBeenCalled): void {
            $finalizeHasBeenCalled = true;
        }, 2);

        try {
            $this->eventBus->dispatch('a message');
        } catch (Throwable) {
        }

        $this->assertTrue($finalizeHasBeenCalled);
    }
}
