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

namespace PhayneTest\ServiceBus\Mock;

use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\MessageBus;
use React\Promise\PromiseInterface;

/**
 * Class CustomMessageBus
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class CustomMessageBus extends MessageBus
{
    private ?ActionEvent $actionEvent = null;

    public function dispatch(mixed $message): ?PromiseInterface
    {
        $actionEventEmitter = $this->events;

        $actionEvent = $this->actionEvent();
        $actionEvent->setName(self::EVENT_DISPATCH);
        $actionEvent->setTarget($this);
        $actionEvent->setParam(self::EVENT_PARAM_MESSAGE, $message);

        $actionEventEmitter->dispatch($actionEvent);

        return null;
    }

    public function setActionEvent(ActionEvent $event): void
    {
        $this->actionEvent = $event;
    }

    public function actionEvent(): ActionEvent
    {
        if (null === $this->actionEvent) {
            $this->actionEvent = $this->events->getNewActionEvent();
        }

        return $this->actionEvent;
    }
}
