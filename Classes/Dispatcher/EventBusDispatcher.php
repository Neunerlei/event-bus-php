<?php
declare(strict_types=1);
/*
 * Copyright 2021 Martin Neundorfer (Neunerlei)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * Last modified: 2021.02.12 at 22:59
 */

namespace Neunerlei\EventBus\Dispatcher;


use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

class EventBusDispatcher implements EventDispatcherInterface
{

    /**
     * The listener provider to fetch the event listeners from
     *
     * @var \Psr\EventDispatcher\ListenerProviderInterface
     */
    protected $listenerProvider;

    /**
     * EventBusDispatcher constructor.
     *
     * @param   \Psr\EventDispatcher\ListenerProviderInterface  $listenerProvider
     */
    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event)
    {
        // Ignore already stopped events
        // @codeCoverageIgnoreStart
        // There seems to be a bug in phpunit... This code gets executed correctly, but the coverage does not report it.
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }
        // @codeCoverageIgnoreEnd

        // Call all listeners
        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);
            if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
                return $event;
            }
        }

        // Done
        return $event;
    }

}
