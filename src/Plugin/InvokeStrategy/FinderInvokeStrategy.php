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

namespace Phayne\ServiceBus\Plugin\InvokeStrategy;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Phayne\ServiceBus\QueryBus;

use function is_object;

/**
 * Class FinderInvokeStrategy
 *
 * @package Phayne\ServiceBus\Plugin\InvokeStrategy
 * @author Julien Guittard <julien@phayne.com>
 */
class FinderInvokeStrategy extends AbstractPlugin
{
    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            function (ActionEvent $actionEvent): void {
                if ($actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLED, false)) {
                    return;
                }

                $finder = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_HANDLER);
                $query = $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE);
                $deferred = $actionEvent->param(QueryBus::EVENT_PARAM_DEFERRED);

                if (is_object($finder)) {
                    $finder->find($query, $deferred);
                    $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLED, true);
                }
            },
            MessageBus::PRIORITY_INVOKE_HANDLER
        );
    }
}
