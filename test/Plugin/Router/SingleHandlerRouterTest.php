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

namespace PhayneTest\ServiceBus\Plugin\Router;

use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\DefaultActionEvent;
use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\Router\CommandRouter;
use PhayneTest\ServiceBus\Mock\DoSomething;
use PHPUnit\Framework\TestCase;
use stdClass;
use TypeError;

/**
 * Class SingleHandlerRouterTest
 *
 * @package PhayneTest\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class SingleHandlerRouterTest extends TestCase
{
    public function testCanHandleRoutingDefinitionByChainingRouteTo(): void
    {
        $router = new CommandRouter();

        $router->route(DoSomething::class)->to('DoSomethingHandler');

        $actionEvent = new DefaultActionEvent(
            MessageBus::EVENT_DISPATCH,
            new CommandBus(),
            [
                MessageBus::EVENT_PARAM_MESSAGE_NAME => DoSomething::class,
            ]
        );

        $router->onRouteMessage($actionEvent);

        $this->assertEquals('DoSomethingHandler', $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER));
    }

    public function testFailsWhenRoutingToInvalidHandler(): void
    {
        $this->expectException(TypeError::class);

        $router = new CommandRouter();

        $router->route(DoSomething::class)->to([]);
    }

    public function testReturnsEarlyWhenMessageNameIsEmpty(): void
    {
        $router = new CommandRouter();

        $router->route(DoSomething::class)->to('DoSomethingHandler');

        $actionEvent = new DefaultActionEvent(
            MessageBus::EVENT_DISPATCH,
            new CommandBus(),
            [
                MessageBus::EVENT_PARAM_MESSAGE_NAME => 'unknown',
            ]
        );

        $router->onRouteMessage($actionEvent);

        $this->assertEmpty($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER));
    }

    public function testReturnsEarlyWhenMessageNameIsNotInEventMap(): void
    {
        $router = new CommandRouter();

        $router->route(DoSomething::class)->to('DoSomethingHandler');

        $actionEvent = new DefaultActionEvent(
            MessageBus::EVENT_DISPATCH,
            new CommandBus(),
            [
                '' => DoSomething::class,
            ]
        );

        $router->onRouteMessage($actionEvent);

        $this->assertEmpty($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER));
    }

    public function testFailsOnRoutingASecondCommandBeforeFirstDefinitionIsFinished(): void
    {
        $this->expectException(RuntimeException::class);

        $router = new CommandRouter();

        $router->route(DoSomething::class);

        $router->route('AnotherCommand');
    }

    public function testFailsOnSettingAHandlerBeforeACommandIsSet(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $router = new CommandRouter();

        $router->to('DoSomethingHandler');
    }

    public function testFailsOnSettingAHandlerBeforeACommandIsSet2(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $router = new CommandRouter();

        $router->to(new stdClass());
    }

    public function testTakesARoutingDefinitionOnInstantiation(): void
    {
        $router = new CommandRouter([
            DoSomething::class => 'DoSomethingHandler',
        ]);

        $actionEvent = new DefaultActionEvent(
            MessageBus::EVENT_DISPATCH,
            new CommandBus(),
            [
                MessageBus::EVENT_PARAM_MESSAGE_NAME => DoSomething::class,
            ]
        );

        $router->onRouteMessage($actionEvent);

        $this->assertEquals('DoSomethingHandler', $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER));
    }
}
