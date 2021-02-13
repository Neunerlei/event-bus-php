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
 * Last modified: 2021.02.13 at 10:22
 */

declare(strict_types=1);


namespace Neunerlei\EventBus\Util;


use Neunerlei\EventBus\Dispatcher\EventListenerList;
use Neunerlei\EventBus\Dispatcher\EventListenerListItem;

class ListenerProviderOnceProxy
{
    /**
     * @var \Neunerlei\EventBus\Dispatcher\EventListenerList
     */
    protected $listeners;

    /**
     * ListenerProviderOnceProxy constructor.
     *
     * @param   \Neunerlei\EventBus\Dispatcher\EventListenerList|null  $list
     */
    public function __construct(?EventListenerList $list = null)
    {
        $this->listeners = $list ?? new EventListenerList();
    }

    /**
     * The actually registered proxy method that calls our internally registered listeners
     */
    public function call(): void
    {
        $args = func_get_args();

        foreach ($this->listeners as $listener) {
            call_user_func_array($listener->listener, $args);
        }
    }

    /**
     * Registers a new item to be executed once when the matching event is emitted in the foreign event dispatcher
     *
     * @param   \Neunerlei\EventBus\Dispatcher\EventListenerListItem  $item
     */
    public function addItem(EventListenerListItem $item): void
    {
        $concreteListener = $item->listener;

        $item->listener = function () use ($item, $concreteListener) {
            $this->listeners->remove($item);
            call_user_func_array($concreteListener, func_get_args());
        };

        $this->listeners->add($item);
    }
}
