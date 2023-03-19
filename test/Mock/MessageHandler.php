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

/**
 * Class MessageHandler
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class MessageHandler
{
    private mixed $lastMessage;

    private int $invokeCounter = 0;

    public function __invoke(mixed $message): void
    {
        $this->lastMessage = $message;
        $this->invokeCounter++;
    }

    public function handle(mixed $message): void
    {
        $this->lastMessage = $message;
        $this->invokeCounter++;
    }

    public function onEvent(mixed $message): void
    {
        $this->lastMessage = $message;
        $this->invokeCounter++;
    }

    public function invokeCounter(): int
    {
        return $this->invokeCounter;
    }

    public function lastMessage(): mixed
    {
        return $this->lastMessage;
    }
}
