<?php
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

declare(strict_types=1);
/**
 * Copyright 2020 Martin Neundorfer (Neunerlei)
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
 * Last modified: 2020.05.21 at 15:27
 */

namespace Neunerlei\EventBus\Dispatcher;


use Psr\EventDispatcher\ListenerProviderInterface;

class EventBusListenerProvider implements ListenerProviderInterface
{

    /**
     * The list of listeners registered in this provider
     *
     * @var \Neunerlei\EventBus\Dispatcher\EventListenerList
     */
    protected $listeners;

    /**
     * EventBusListenerProvider constructor.
     */
    public function __construct()
    {
        $this->listeners = new EventListenerList();
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $listener) {
            if ($event instanceof $listener->eventClassName) {
                yield $listener->listener;
            }
        }
    }

    /**
     * Registers a new listener for a certain event
     *
     * @param   string    $eventClassName  The name of the event class to bind this listener to
     * @param   callable  $listener        The listener callable to represent
     * @param   array     $options         The options to bind this listener with
     *
     * @return string A unique id for the registered listener
     *
     * @see \Neunerlei\EventBus\EventBusInterface::addListener() for details on the options
     */
    public function addListener(string $eventClassName, callable $listener, array $options = []): string
    {
        $item = new EventListenerListItem($eventClassName, $listener, $options);
        $this->listeners->add($item);

        return $item->id;
    }

    /**
     * Removes a previously registered listener from it's bound event
     *
     * @param   string|callable  $idOrListener  The unique id of the listener to remove or
     *                                          the listener callback. Note: if you pass a callable it will be removed
     *                                          from ALL events it was bound to!
     */
    public function removeListener($idOrListener): void
    {
        $this->listeners->remove($idOrListener);
    }
}
