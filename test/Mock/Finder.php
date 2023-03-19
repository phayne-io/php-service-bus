<?php
//phpcs:ignorefile

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
 * Class Finder
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class Finder
{
    private mixed $message = null;

    private mixed $deferred = null;

    public function find($message, $deferred): void
    {
        $this->message = $message;
        $this->deferred = $deferred;
    }

    public function lastMessage()
    {
        return $this->message;
    }

    public function lastDeferred()
    {
        return $this->deferred;
    }
}
