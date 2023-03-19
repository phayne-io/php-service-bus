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

namespace PhayneTest\ServiceBus;

use Phayne\ServiceBus\MessageBus;
use PhayneTest\ServiceBus\Mock\CustomMessageBus;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Class MessageBusTest
 *
 * @package PhayneTest\ServiceBus
 * @author Julien Guittard <julien@phayne.com>
 */
class MessageBusTest extends TestCase
{
    public function testUsesMessageClassAsNameIfNoOneWasSet(): void
    {
        $messageBus = new CustomMessageBus();
        $messageBus->dispatch(new stdClass());

        $this->assertSame(stdClass::class, $messageBus->actionEvent()->param(MessageBus::EVENT_PARAM_MESSAGE_NAME));
    }

    public function testUsesMessageAsMessageNameIfMessageIsAString(): void
    {
        $messageBus = new CustomMessageBus();
        $messageBus->dispatch('message and a message name');

        $this->assertSame(
            'message and a message name',
            $messageBus->actionEvent()->param(MessageBus::EVENT_PARAM_MESSAGE_NAME)
        );
    }

    public function testUsesTypeOfMessageAsMessageNameIfMessageIsNeitherObjectNorString(): void
    {
        $messageBus = new CustomMessageBus();
        $messageBus->dispatch([]);

        $this->assertSame('array', $messageBus->actionEvent()->param(MessageBus::EVENT_PARAM_MESSAGE_NAME));
    }

    public function testDoesNotAttachToInvalidEventNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown event name given: invalid');

        $messageBus = new CustomMessageBus();
        $messageBus->attach('invalid', function () {
        });
    }
}
