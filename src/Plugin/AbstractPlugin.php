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

namespace Phayne\ServiceBus\Plugin;

use Phayne\Messaging\Event\ListenerHandler;
use Phayne\ServiceBus\MessageBus;

use function Phayne\Phunctional\each;

/**
 * Class AbstractPlugin
 *
 * @package Phayne\ServiceBus\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
abstract class AbstractPlugin implements Plugin
{
    protected array $listenerHandlers = [];

    public function detachFromMessageBus(MessageBus $messageBus): void
    {
        each(fn(ListenerHandler $handler) => $messageBus->detach($handler), $this->listenerHandlers);
        $this->listenerHandlers = [];
    }
}
