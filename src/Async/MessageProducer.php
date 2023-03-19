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

namespace Phayne\ServiceBus\Async;

use Phayne\Messaging\Messaging\Message;
use React\Promise\Deferred;

/**
 * Interface MessageProducer
 *
 * @package Phayne\ServiceBus\Async
 * @author Julien Guittard <julien@phayne.com>
 */
interface MessageProducer
{
    public function __invoke(Message $message, Deferred $deferred = null): void;
}
