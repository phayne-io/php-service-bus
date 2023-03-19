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
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\Exception\MessageDispatchException;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\InvokeStrategy\FinderInvokeStrategy;
use Phayne\ServiceBus\QueryBus;
use PhayneTest\ServiceBus\Mock\CustomMessage;
use PhayneTest\ServiceBus\Mock\ErrorProducer;
use PhayneTest\ServiceBus\Mock\FetchSomething;
use PhayneTest\ServiceBus\Mock\Finder;
use PHPUnit\Framework\TestCase;
use React\Promise\Deferred;
use React\Promise\Promise;
use stdClass;
use Throwable;

/**
 * Class QueryBusTest
 *
 * @package PhayneTest\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class QueryBusTest extends TestCase
{
    private QueryBus $queryBus;

    protected function setUp(): void
    {
        $this->queryBus = new QueryBus();
    }

    public function testDispatchesAMessageUsingTheDefaultProcess(): void
    {
        $fetchSomething = new FetchSomething(['filter' => 'todo']);

        $receivedMessage = null;
        $dispatchEvent = null;
        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$receivedMessage, &$dispatchEvent): void {
                $actionEvent->setParam(
                    MessageBus::EVENT_PARAM_MESSAGE_HANDLER,
                    function (FetchSomething $fetchSomething, Deferred $deferred) use (&$receivedMessage): void {
                        $deferred->resolve($fetchSomething);
                    }
                );
                $dispatchEvent = $actionEvent;
            },
            MessageBus::PRIORITY_ROUTE
        );

        $promise = $this->queryBus->dispatch($fetchSomething);

        $promise->then(function ($result) use (&$receivedMessage): void {
            $receivedMessage = $result;
        });

        $this->assertSame($fetchSomething, $receivedMessage);
        $this->assertTrue($dispatchEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLED));
    }

    public function testTriggersAllDefinedActionEvents(): void
    {
        $initializeIsTriggered = false;
        $detectMessageNameIsTriggered = false;
        $routeIsTriggered = false;
        $locateHandlerIsTriggered = false;
        $invokeFinderIsTriggered = false;
        $finalizeIsTriggered = false;

        //Should always be triggered
        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$initializeIsTriggered): void {
                $initializeIsTriggered = true;
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        //Should be triggered because we dispatch a message that does not
        //implement Phayne\Common\Messaging\HasMessageName
        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$detectMessageNameIsTriggered): void {
                $detectMessageNameIsTriggered = true;
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_NAME, 'custom-message');
            },
            MessageBus::PRIORITY_DETECT_MESSAGE_NAME
        );

        //Should be triggered because we did not provide a message-handler yet
        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$routeIsTriggered): void {
                $routeIsTriggered = true;
                if ($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === 'custom-message') {
                    //We provide the message handler as a string (service id) to
                    //tell the bus to trigger the locate-handler event
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, 'error-producer');
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        //Should be triggered because we provided the message-handler as string (service id)
        $this->queryBus->attach(
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
        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) use (&$invokeFinderIsTriggered): void {
                $invokeFinderIsTriggered = true;
                $handler = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);
                if ($handler instanceof ErrorProducer) {
                    $handler->throwException($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE));
                }
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );

        //Should always be triggered
        $this->queryBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$finalizeIsTriggered): void {
                if ($actionEvent->param(MessageBus::EVENT_PARAM_EXCEPTION) instanceof Throwable) {
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_EXCEPTION, null);
                }
                $finalizeIsTriggered = true;
            },
            1000 // high priority
        );

        $customMessage = new CustomMessage('I have no further meaning');

        $this->queryBus->dispatch($customMessage);

        $this->assertTrue($initializeIsTriggered);
        $this->assertTrue($detectMessageNameIsTriggered);
        $this->assertTrue($routeIsTriggered);
        $this->assertTrue($locateHandlerIsTriggered);
        $this->assertTrue($invokeFinderIsTriggered);
        $this->assertTrue($finalizeIsTriggered);
    }

    public function testUsesTheFqcnOfTheMessageIfMessageNameWasNotProvidedAndMessageDoesNotImplementHasMessageName(): void //phpcs:ignore
    {
        $handler = new Finder();

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        (new FinderInvokeStrategy())->attachToMessageBus($this->queryBus);

        $customMessage = new CustomMessage('foo');

        $promise = $this->queryBus->dispatch($customMessage);

        $this->assertSame($customMessage, $handler->lastMessage());
        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertInstanceOf(Deferred::class, $handler->lastDeferred());
    }

    public function testRejectsTheDeferredWithAServiceBusExceptionIfExceptionIsNotHandledByAPlugin(): void
    {
        $exception = null;

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function () {
                throw new Exception('ka boom');
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $promise = $this->queryBus->dispatch('throw it');

        $promise->then(
            function () {
            },
            function (Throwable $ex) use (&$exception): void {
                $exception = $ex;
            }
        );

        $this->assertInstanceOf(MessageDispatchException::class, $exception);
    }

    public function testThrowsExceptionIfEventHasNoHandlerAfterItHasBeenSetAndEventWasTriggered(): void
    {
        $exception = null;

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, null);
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $promise = $this->queryBus->dispatch('throw it');

        $promise->then(
            function () {
            },
            function (Throwable $ex) use (&$exception): void {
                $exception = $ex;
            }
        );

        $this->assertInstanceOf(MessageDispatchException::class, $exception);
        $this->assertEquals('Message dispatch failed. See previous exception for details', $exception->getMessage());
    }

    public function testThrowsExceptionIfEventHasStoppedPropagation(): void
    {
        $exception = null;

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                throw new RuntimeException('throw it!');
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $promise = $this->queryBus->dispatch('throw it');

        $promise->then(
            function () {
            },
            function (Throwable $ex) use (&$exception): void {
                $exception = $ex;
            }
        );

        $this->assertInstanceOf(MessageDispatchException::class, $exception);
        $this->assertEquals('Message dispatch failed. See previous exception for details', $exception->getMessage());
        $this->assertInstanceOf(RuntimeException::class, $exception->getPrevious());
        $this->assertEquals('throw it!', $exception->getPrevious()->getMessage());
    }

    public function testCanDeactivateAnActionEventListenerAggregate(): void
    {
        $handler = new Finder();

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e) use ($handler): void {
                if ($e->param(MessageBus::EVENT_PARAM_MESSAGE_NAME) === CustomMessage::class) {
                    $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);
                }
            },
            MessageBus::PRIORITY_ROUTE
        );

        $plugin = new FinderInvokeStrategy();
        $plugin->attachToMessageBus($this->queryBus);
        $plugin->detachFromMessageBus($this->queryBus);

        $customMessage = new CustomMessage('foo');

        $promise = $this->queryBus->dispatch($customMessage);

        $this->assertNull($handler->lastMessage());
        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertNull($handler->lastDeferred());
    }

    public function testThrowsExceptionIfMessageWasNotHandled(): void
    {
        $this->expectException(MessageDispatchException::class);

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, new stdClass());
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $promise = $this->queryBus->dispatch('throw it');

        $promise->done();
    }

    public function testCouldResetExceptionBeforePromiseBecomesRejected(): void
    {
        $exceptionParamWasSet = false;

        $this->queryBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent) {
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, function (): void {
                    throw new Exception('Unset me!');
                });
            },
            MessageBus::PRIORITY_INITIALIZE
        );

        $this->queryBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent) use (&$exceptionParamWasSet): void {
                if ($actionEvent->param(MessageBus::EVENT_PARAM_EXCEPTION) instanceof Throwable) {
                    $exceptionParamWasSet = true;
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_EXCEPTION, null);
                }
            },
            MessageBus::PRIORITY_PROMISE_REJECT + 1
        );

        $promise = $this->queryBus->dispatch('throw an exception!');
        $promise->done();

        $this->assertTrue($exceptionParamWasSet);
    }

    public function testAlwaysTriggersFinalizeListenersRegardlessWhetherThePropagationOfTheEventHasBeenStopped(): void
    {
        $this->queryBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event) {
            $event->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, function (): void {
            });
        }, MessageBus::PRIORITY_LOCATE_HANDLER + 1);
        $this->queryBus->attach(MessageBus::EVENT_DISPATCH, function (ActionEvent $event): void {
            $event->stopPropagation();
        }, MessageBus::PRIORITY_INVOKE_HANDLER - 1);

        $this->queryBus->attach(MessageBus::EVENT_FINALIZE, function (): void {
        }, 3);
        $finalizeHasBeenCalled = false;
        $this->queryBus->attach(MessageBus::EVENT_FINALIZE, function () use (&$finalizeHasBeenCalled): void {
            $finalizeHasBeenCalled = true;
        }, 2);

        $this->queryBus->dispatch('a message');

        $this->assertTrue($finalizeHasBeenCalled);
    }
}
