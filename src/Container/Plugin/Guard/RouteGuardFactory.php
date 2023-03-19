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

namespace Phayne\ServiceBus\Container\Plugin\Guard;

use Phayne\Exception\InvalidArgumentException;
use Phayne\ServiceBus\Plugin\Guard\AuthorizationService;
use Phayne\ServiceBus\Plugin\Guard\RouteGuard;
use Psr\Container\ContainerInterface;

use function sprintf;

/**
 * Class RouteGuardFactory
 *
 * @package Phayne\ServiceBus\Container\Plugin\Guard
 * @author Julien Guittard <julien@phayne.com>
 */
class RouteGuardFactory
{
    public function __construct(private readonly bool $exposeEventMessageName = false)
    {
    }

    public static function __callStatic($name, array $arguments): RouteGuard
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static(true))->__invoke($arguments[0]);
    }

    public function __invoke(ContainerInterface $container): RouteGuard
    {
        $authorizationService = $container->get(AuthorizationService::class);

        return new RouteGuard($authorizationService, $this->exposeEventMessageName);
    }
}
