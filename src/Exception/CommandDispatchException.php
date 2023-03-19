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

namespace Phayne\ServiceBus\Exception;

use Throwable;

/**
 * Class CommandDispatchException
 *
 * @package Phayne\ServiceBus\Exception
 * @author Julien Guittard <julien@phayne.com>
 */
class CommandDispatchException extends MessageDispatchException
{
    private array $pendingCommands = [];

    public static function wrap(Throwable $dispatchException, array $pendingCommands): static
    {
        if ($dispatchException instanceof MessageDispatchException) {
            $ex = parent::failed($dispatchException->getPrevious());

            $ex->pendingCommands = $pendingCommands;

            return $ex;
        }

        $ex = new static('Command dispatch failed. See previous exception for details.', $dispatchException);
        $ex->pendingCommands = $pendingCommands;

        return $ex;
    }

    public function pendingCommands(): array
    {
        return $this->pendingCommands;
    }
}
