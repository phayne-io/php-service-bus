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

use Phayne\ServiceBus\MessageBus;

/**
 * Interface Plugin
 *
 * @package Phayne\ServiceBus\Plugin
 * @author Julien Guittard <julien@phayne.com>
 */
interface Plugin
{
    public function attachToMessageBus(MessageBus $messageBus): void;

    public function detachFromMessageBus(MessageBus $messageBus): void;
}
