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
use Phayne\ServiceBus\Plugin\Guard\FinalizeGuard;
use Psr\Container\ContainerInterface;

/**
 * Class FinalizeGuardFactory
 *
 * @package Phayne\ServiceBus\Container\Plugin\Guard
 * @author Julien Guittard <julien@phayne.com>
 */
class FinalizeGuardFactory
{
    public static function __callStatic(string $name, array $arguments): FinalizeGuard
    {
        if (! isset($arguments[0]) || ! $arguments[0] instanceof ContainerInterface) {
            throw new InvalidArgumentException(
                \sprintf('The first argument must be of type %s', ContainerInterface::class)
            );
        }

        return (new static(true))->__invoke($arguments[0]);
    }

    public function __construct(private readonly bool $exposeEventMessageName = false)
    {
    }

    public function __invoke(ContainerInterface $container): FinalizeGuard
    {
        $authorizationService = $container->get(AuthorizationService::class);

        return new FinalizeGuard($authorizationService, $this->exposeEventMessageName);
    }
}
