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

use function sprintf;

/**
 * Class EventListenerException
 *
 * @package Phayne\ServiceBus\Exception
 * @author Julien Guittard <julien@phayne.com>
 */
class EventListenerException extends MessageDispatchException
{
    private array $exceptionCollection;

    public static function collected(Throwable ...$exceptions): static
    {
        $messages = '';

        foreach ($exceptions as $exception) {
            $messages .= $exception->getMessage() . "\n";
        }

        $self = new static(sprintf(
            'At least one event listener caused an exception. Check listener exceptions for details:%s%s',
            PHP_EOL,
            $messages
        ));

        $self->exceptionCollection = $exceptions;

        return $self;
    }

    public function listenerExceptions(): array
    {
        return $this->exceptionCollection;
    }
}
