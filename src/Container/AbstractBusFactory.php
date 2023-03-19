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

namespace Phayne\ServiceBus\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresConfigId;
use Phayne\Exception\InvalidArgumentException;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Messaging\MessageFactory;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\ContainerPlugin;
use Phayne\ServiceBus\Plugin\MessageFactoryPlugin;
use Phayne\ServiceBus\Plugin\Router\AsyncSwitchMessageRouter;
use Psr\Container\ContainerInterface;

use function implode;
use function is_string;
use function sprintf;

/**
 * Class AbstractBusFactory
 *
 * @package Phayne\ServiceBus\Plugin\Container
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class AbstractBusFactory implements RequiresConfigId, ProvidesDefaultOptions
{
    use ConfigurationTrait;

    public static function __callStatic(string $name, array $arguments): MessageBus
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static($name))->__invoke($arguments[0]);
    }

    abstract protected function busClass(): string;

    abstract protected function defaultRouterClass(): string;

    public function __construct(private readonly string $configId)
    {
    }

    public function __invoke(ContainerInterface $container): MessageBus
    {
        $config = [];

        if ($container->has('config')) {
            $config = $container->get('config');
        }

        $busConfig = $this->optionsWithFallback($config, $this->configId);

        $busClass = $this->busClass();

        $bus = new $busClass();

        if (isset($busConfig['plugins'])) {
            $this->attachPlugins($bus, $busConfig['plugins'], $container);
        }

        if (isset($busConfig['router'])) {
            $this->attachRouter($bus, $busConfig['router'], $container);
        }

        if ((bool) $busConfig['enable_handler_location']) {
            (new ContainerPlugin($container))->attachToMessageBus($bus);
        }

        if ($container->has($busConfig['message_factory'])) {
            (new MessageFactoryPlugin($container->get($busConfig['message_factory'])))->attachToMessageBus($bus);
        }

        return $bus;
    }

    public function dimensions(): iterable
    {
        return ['phayne', 'service_bus'];
    }

    public function defaultOptions(): iterable
    {
        return [
            'enable_handler_location' => true,
            'message_factory' => MessageFactory::class,
        ];
    }

    private function attachPlugins(MessageBus $bus, array $plugins, ContainerInterface $container): void
    {
        foreach ($plugins as $index => $plugin) {
            if (! is_string($plugin) || ! $container->has($plugin)) {
                throw new RuntimeException(sprintf(
                    'Wrong message bus utility configured at %s. 
                    Either it is not a string or unknown by the container.',
                    implode('.', $this->dimensions()) . '.' . $this->configId . '.' . $index
                ));
            }

            $container->get($plugin)->attachToMessageBus($bus);
        }
    }

    private function attachRouter(MessageBus $bus, array $routerConfig, ContainerInterface $container): void
    {
        $routerClass = $routerConfig['type'] ?? $this->defaultRouterClass();

        $routes = $routerConfig['routes'] ?? [];

        $router = new $routerClass($routes);

        if (isset($routerConfig['async_switch'])) {
            $asyncMessageProducer = $container->get($routerConfig['async_switch']);

            $router = new AsyncSwitchMessageRouter($router, $asyncMessageProducer);
        }

        $router->attachToMessageBus($bus);
    }
}
