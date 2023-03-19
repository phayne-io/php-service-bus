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

namespace PhayneTest\ServiceBus\Container;

use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\Messaging\Messaging\Message;
use Phayne\Messaging\Messaging\MessageFactory;
use Phayne\ServiceBus\Async\AsyncMessage;
use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\Container\AbstractBusFactory;
use Phayne\ServiceBus\Container\CommandBusFactory;
use Phayne\ServiceBus\Container\EventBusFactory;
use Phayne\ServiceBus\Container\QueryBusFactory;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\Plugin;
use Phayne\ServiceBus\Plugin\Router\RegexRouter;
use Phayne\ServiceBus\QueryBus;
use PhayneTest\ServiceBus\Mock\NoopMessageProducer;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;

/**
 * Class BusFactoriesTest
 *
 * @package PhayneTest\ServiceBus\Container
 * @author Julien Guittard <julien@phayne.com>
 */
class BusFactoriesTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesABusWithoutNeedingAApplicationConfig(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(false);
        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesABusWithoutNeedingPhayneConfig(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([]);
        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesANewBusWithAllPluginsAttachedUsingAContainerAndConfiguration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $firstPlugin = $this->prophesize(Plugin::class);
        $secondPlugin = $this->prophesize(Plugin::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'plugins' => [
                            'first_plugin_service_id',
                            'second_plugin_service_id',
                        ],
                    ],
                ],
            ],
        ]);

        $firstPlugin->attachToMessageBus(Argument::type(MessageBus::class))->shouldBeCalled();
        $secondPlugin->attachToMessageBus(Argument::type(MessageBus::class))->shouldBeCalled();

        $container->has('first_plugin_service_id')->willReturn(true);
        $container->get('first_plugin_service_id')->willReturn($firstPlugin->reveal());
        $container->has('second_plugin_service_id')->willReturn(true);
        $container->get('second_plugin_service_id')->willReturn($secondPlugin->reveal());

        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testThrowsARuntimeExceptionIfPluginIsNotRegisteredInContainer(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $this->expectException(RuntimeException::class);

        $container = $this->prophesize(ContainerInterface::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'plugins' => [
                            'plugin_service_id',
                        ],
                    ],
                ],
            ],
        ]);

        $container->has('plugin_service_id')->willReturn(false);

        $container->has(MessageFactory::class)->willReturn(false);

        $busFactory($container->reveal());
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesABusWithTheDefaultRouterAttachedIfRoutesAreConfigured(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(Message::class);

        $message->messageName()->willReturn('test_message');
        $handlerWasCalled = false;

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'routes' => [
                                'test_message' => function (Message $message) use (&$handlerWasCalled): void {
                                    $handlerWasCalled = true;
                                },
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $bus->dispatch($message->reveal());

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesABusAndAttachesTheRouterDefinedViaConfiguration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(Message::class);

        $message->messageName()->willReturn('test_message');
        $handlerWasCalled = false;

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'type' => RegexRouter::class,
                            'routes' => [
                                '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                    $handlerWasCalled = true;
                                },
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $bus->dispatch($message->reveal());

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCreatesABusAndAttachesTheMessageFactoryDefinedViaConfiguration(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(Message::class);
        $messageFactory = $this->prophesize(MessageFactory::class);

        $message->messageName()->willReturn('test_message');
        $handlerWasCalled = false;

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'type' => RegexRouter::class,
                            'routes' => [
                                '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                    $handlerWasCalled = true;
                                },
                            ],
                        ],
                        'message_factory' => 'custom_message_factory',
                    ],
                ],
            ],
        ]);

        $container->has('custom_message_factory')->willReturn(true);
        $container->get('custom_message_factory')->willReturn($messageFactory);

        $bus = $busFactory($container->reveal());

        $bus->dispatch($message->reveal());

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testDecoratesRouterWithAsyncSwitchAndPullsAsyncMessageProducerFromContainer(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(AsyncMessage::class);
        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageProducer = new NoopMessageProducer();
        $container->get('noop_message_producer')->willReturn($messageProducer);

        $message->messageName()->willReturn('test_message');
        $message->metadata()->willReturn([]);
        $message->withAddedMetadata('handled-async', true)->willReturn($message->reveal());
        $handlerWasCalled = false;

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'async_switch' => 'noop_message_producer',
                            'type' => RegexRouter::class,
                            'routes' => [
                                '/^test_./' => function (Message $message) use (&$handlerWasCalled): void {
                                    $handlerWasCalled = true;
                                },
                            ],
                        ],
                        'message_factory' => 'custom_message_factory',
                    ],
                ],
            ],
        ]);

        $container->has('custom_message_factory')->willReturn(true);
        $container->get('custom_message_factory')->willReturn($messageFactory);

        $bus = $busFactory($container->reveal());

        $bus->dispatch($message->reveal());

        $this->assertFalse($handlerWasCalled);
        $this->assertTrue($messageProducer->isInvoked());
    }

    /**
     * @dataProvider provideBuses
     */
    public function testEnablesHandlerLocationByDefault(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(Message::class);

        $message->messageName()->willReturn('test_message')->shouldBeCalled();
        $handlerWasCalled = false;

        $container->has('config')->willReturn(true)->shouldBeCalled();
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'routes' => [
                                'test_message' => 'handler_service_id',
                            ],
                        ],
                    ],
                ],
            ],
        ])->shouldBeCalled();

        $container->has('handler_service_id')->willReturn(true)->shouldBeCalled();
        $container->get('handler_service_id')->willReturn(function (Message $message) use (&$handlerWasCalled): void {
            $handlerWasCalled = true;
        })->shouldBeCalled();

        $container->has(MessageFactory::class)->willReturn(false)->shouldBeCalled();

        $bus = $busFactory($container->reveal());

        $bus->dispatch($message->reveal());

        $this->assertTrue($handlerWasCalled);
    }

    /**
     * @dataProvider provideBuses
     */
    public function testProvidesPossibilityToDisableHandlerLocation(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $message = $this->prophesize(Message::class);

        $message->messageName()->willReturn('test_message');

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'router' => [
                            'routes' => [
                                'test_message' => 'handler_service_id',
                            ],
                        ],
                        'enable_handler_location' => false,
                    ],
                ],
            ],
        ]);

        $container->has(MessageFactory::class)->willReturn(false);

        $container->has('handler_service_id')->shouldNotBeCalled();

        $bus = $busFactory($container->reveal());

        $bus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $e): void {
                $e->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLED, true);
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );

        $bus->dispatch($message->reveal());
    }

    /**
     * @dataProvider provideBuses
     */
    public function testCanHandleApplicationConfigBeingOfTypeArrayAccess(
        string $busClass,
        string $busConfigKey,
        AbstractBusFactory $busFactory
    ): void {
        $container = $this->prophesize(ContainerInterface::class);
        $firstPlugin = $this->prophesize(Plugin::class);

        $container->has('config')->willReturn(true);
        $container->get('config')->willReturn(new \ArrayObject([
            'phayne' => [
                'service_bus' => [
                    $busConfigKey => [
                        'plugins' => [
                            'first_plugin_service_id',
                        ],
                    ],
                ],
            ],
        ]));

        $firstPlugin->attachToMessageBus(Argument::type(MessageBus::class))->shouldBeCalled();

        $container->has('first_plugin_service_id')->willReturn(true);
        $container->get('first_plugin_service_id')->willReturn($firstPlugin->reveal());

        $container->has(MessageFactory::class)->willReturn(false);

        $bus = $busFactory($container->reveal());

        $this->assertInstanceOf($busClass, $bus);
    }

    /**
     * @dataProvider provideBusFactoryClasses
     */
    public function testCreatesABusFromStaticCall(string $busClass, string $busFactoryClass): void
    {
        $container = $this->prophesize(ContainerInterface::class);
        $container->has('config')->willReturn(true);
        $container->has(MessageFactory::class)->willReturn(false);
        $container->get('config')->willReturn([]);

        $factory = [$busFactoryClass, 'other_config_id'];
        $this->assertInstanceOf($busClass, $factory($container->reveal()));
    }

    public function testThrowsInvalidArgumentExceptionWithoutContainerOnStaticCall(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The first argument must be of type Psr\Container\ContainerInterface');

        CommandBusFactory::other_config_id();
    }

    public function provideBusFactoryClasses(): array
    {
        return [
            [CommandBus::class, CommandBusFactory::class],
            [EventBus::class, EventBusFactory::class],
            [QueryBus::class, QueryBusFactory::class],
        ];
    }

    public function provideBuses(): array
    {
        return [
            [CommandBus::class, 'command_bus', new CommandBusFactory()],
            [EventBus::class, 'event_bus', new EventBusFactory()],
            [QueryBus::class, 'query_bus', new QueryBusFactory()],
        ];
    }
}
