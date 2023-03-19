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

namespace Phayne\ServiceBus\Plugin\Router;

use Assert\Assertion;
use Phayne\Exception\RuntimeException;
use Phayne\Messaging\Event\ActionEvent;
use Phayne\ServiceBus\CommandBus;
use Phayne\ServiceBus\EventBus;
use Phayne\ServiceBus\MessageBus;
use Phayne\ServiceBus\Plugin\AbstractPlugin;
use Phayne\ServiceBus\QueryBus;

use function current;
use function get_class;
use function gettype;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use function key;
use function preg_match;
use function sprintf;

/**
 * Class RegexRouter
 *
 * @package Phayne\ServiceBus\Plugin\Router
 * @author Julien Guittard <julien@phayne.com>
 */
class RegexRouter extends AbstractPlugin implements MessageBusRouterPlugin
{
    public const ALL = '/.*/';

    protected array $patternMap = [];

    protected ?string $tmpPattern = null;

    public function __construct(?array $patternMap = null)
    {
        if (null === $patternMap) {
            return;
        }

        foreach ($patternMap as $pattern => $handler) {
            if (is_array($handler)) {
                foreach ($handler as $singleHandler) {
                    $this->route($pattern)->to($singleHandler);
                }
            } else {
                $this->route($pattern)->to($handler);
            }
        }
    }

    public function onRouteMessage(ActionEvent $actionEvent): void
    {
        if ($actionEvent->target() instanceof CommandBus || $actionEvent->target() instanceof QueryBus) {
            $this->onRouteToSingleHandler($actionEvent);
        } else {
            $this->onRouteEvent($actionEvent);
        }
    }

    public function to(string | object | callable $handler): RegexRouter
    {
        if (null === $this->tmpPattern) {
            throw new RuntimeException(sprintf(
                'Cannot map handler %s to a pattern. Please use method route before calling method to',
                is_object($handler)
                    ? get_class($handler)
                    : (is_string($handler) ? $handler : gettype($handler))
            ));
        }

        $this->patternMap[] = [$this->tmpPattern => $handler];
        $this->tmpPattern = null;

        return $this;
    }


    public function attachToMessageBus(MessageBus $messageBus): void
    {
        $this->listenerHandlers[] = $messageBus->attach(
            MessageBus::EVENT_DISPATCH,
            [$this, 'onRouteMessage'],
            MessageBus::PRIORITY_ROUTE
        );
    }

    public function route(string $pattern): RegexRouter
    {
        Assertion::notEmpty($pattern);

        if (null !== $this->tmpPattern) {
            throw new RuntimeException(sprintf('pattern %s is not mapped to a handler.', $this->tmpPattern));
        }

        $this->tmpPattern = $pattern;

        return $this;
    }

    private function onRouteToSingleHandler(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        $alreadyMatched = false;

        foreach ($this->patternMap as $map) {
            $pattern = key($map);
            $handler = current($map);
            if (preg_match($pattern, $messageName)) {
                if ($alreadyMatched) {
                    throw new RuntimeException(sprintf(
                        'Multiple handlers detected for message %s. The patterns %s and %s matches both',
                        $messageName,
                        $alreadyMatched,
                        $pattern
                    ));
                }
                $actionEvent->setParam(MessageBus::EVENT_PARAM_MESSAGE_HANDLER, $handler);

                $alreadyMatched = true;
            }
        }
    }

    private function onRouteEvent(ActionEvent $actionEvent): void
    {
        $messageName = (string) $actionEvent->param(MessageBus::EVENT_PARAM_MESSAGE_NAME);

        if (empty($messageName)) {
            return;
        }

        foreach ($this->patternMap as $map) {
            $pattern = key($map);
            $handler = current($map);
            if (preg_match($pattern, $messageName)) {
                $listeners = $actionEvent->param(EventBus::EVENT_PARAM_EVENT_LISTENERS, []);
                $listeners[] = $handler;
                $actionEvent->setParam(EventBus::EVENT_PARAM_EVENT_LISTENERS, $listeners);
            }
        }
    }
}
