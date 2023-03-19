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

use Phayne\Messaging\Messaging\Command;
use Phayne\Messaging\Messaging\PayloadConstructable;
use Phayne\Messaging\Messaging\PayloadTrait;

/**
 * Class DoSomething
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class DoSomething extends Command implements PayloadConstructable
{
    use PayloadTrait;
}
