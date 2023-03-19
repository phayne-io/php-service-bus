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

namespace Phayne\ServiceBus\Plugin\Guard;

use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Phayne\ServiceBus\QueryBus;
use React\Promise\Promise;

/**
 * Class FinalizeGuard
 *
 * @package Phayne\ServiceBus\Plugin\Guard
 * @author Julien Guittard <julien@phayne.com>
 */
final class FinalizeGuard extends AbstractPlugin
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly bool $exposeEventMessageName = false
    ) {
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_FINALIZE,
            function (ActionEvent $actionEvent): void {
                $promise = $actionEvent->param(QueryBus::EVENT_PARAM_PROMISE);
                $messageName = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

                if ($promise instanceof Promise) {
                    $newPromise = $promise->then(function ($result) use ($actionEvent, $messageName) {
                        if (! $this->authorizationService->isGranted($messageName, $result)) {
                            $actionEvent->stopPropagation(true);

                            if (! $this->exposeEventMessageName) {
                                $messageName = '';
                            }

                            throw new RuntimeException($messageName); //TODO: UnauthorizedException
                        }

                        return $result;
                    });
                    $actionEvent->setParam(QueryBus::EVENT_PARAM_PROMISE, $newPromise);
                } elseif (! $this->authorizationService->isGranted($messageName)) {
                    $actionEvent->stopPropagation(true);

                    if (! $this->exposeEventMessageName) {
                        $messageName = '';
                    }

                    throw new RuntimeException($messageName); //TODO: UnauthorizedException
                }
            },
            -1000
        );
    }
}
