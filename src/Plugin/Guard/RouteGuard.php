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

/**
 * Class RouteGuard
 *
 * @package Phayne\ServiceBus\Plugin\Guard
 * @author Julien Guittard <julien@phayne.com>
 */
class RouteGuard extends AbstractPlugin
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly bool $exposeEventMessageName = false
    ) {
    }

    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                $messageName = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

                if (
                    $this->authorizationService->isGranted(
                        $messageName,
                        $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE)
                    )
                ) {
                    return;
                }

                $actionEvent->stopPropagation();

                if (! $this->exposeEventMessageName) {
                    $messageName = '';
                }

                throw new RuntimeException($messageName); //TODO: UnauthorizedException
            },
            MessageBus::PRIORITY_ROUTE
        );
    }
}
