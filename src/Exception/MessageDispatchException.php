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

use Fig\Http\Message\StatusCodeInterface;
use Phayne\Exception\DomainException;
use Throwable;

/**
 * Class MessageDispatchException
 *
 * @package Phayne\ServiceBus\Exception
 * @author Julien Guittard <julien@phayne.com>
 */
class MessageDispatchException extends DomainException
{
    public static function failed(Throwable $dispatchException): self
    {
        return new self(
            'Message dispatch failed. See previous exception for details',
            StatusCodeInterface::STATUS_UNPROCESSABLE_ENTITY,
            $dispatchException
        );
    }
}
