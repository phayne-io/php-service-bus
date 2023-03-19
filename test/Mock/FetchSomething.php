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

use Phayne\Messaging\Messaging\PayloadConstructable;
use Phayne\Messaging\Messaging\PayloadTrait;
use Phayne\Messaging\Messaging\Query;

/**
 * Class FetchSomething
 *
 * @package PhayneTest\ServiceBus\Mock
 * @author Julien Guittard <julien@phayne.com>
 */
class FetchSomething extends Query implements PayloadConstructable
{
    use PayloadTrait;
}
