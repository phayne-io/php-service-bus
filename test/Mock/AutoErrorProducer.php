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

use Exception;

/**
 * Class AutoErrorProducer
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class AutoErrorProducer
{
    public function __invoke(mixed $message): void
    {
        $this->throwException($message);
    }

    public function throwException(mixed $message)
    {
        throw new Exception('I can only throw exceptions');
    }
}
