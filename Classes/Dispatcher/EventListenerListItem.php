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
 * Last modified: 2020.05.21 at 17:13
 */

namespace Neunerlei\EventBus\Dispatcher;


class EventListenerListItem
{

    /**
     * Counter to generate unique id's with
     *
     * @var int
     */
    protected static $counter = 0;

    /**
     * A unique id for this listener
     *
     * @var string
     */
    public $id;

    /**
     * The pivot id to sort this item before/after
     *
     * @var null|string
     */
    public $pivotId;

    /**
     * True if the item should be sorted before the pivot id
     *
     * @var bool
     */
    public $beforePivot = false;

    /**
     * The priority that was given for this item
     *
     * @var int
     */
    public $priority = 0;

    /**
     * The name of the event class this listener was registered for
     *
     * @var string
     */
    public $eventClassName;

    /**
     * The actual listener callable
     *
     * @var callable
     */
    public $listener;

    /**
     * EventListenerListItem constructor.
     *
     * @param   string    $eventClassName  The name of the event class to bind this listener to
     * @param   callable  $listener        The listener callable to represent
     * @param   array     $options         The options to bind this listener with
     *
     * @see \Neunerlei\EventBus\EventBusInterface::addListener() for details on the options
     */
    public function __construct(string $eventClassName, callable $listener, array $options)
    {
        $this->eventClassName = $eventClassName;
        $this->listener       = $listener;

        if (isset($options['priority']) && is_numeric($options['priority'])) {
            $this->priority = $options['priority'];
        }

        if (isset($options['before']) && is_string($options['before'])) {
            $this->pivotId     = $options['before'];
            $this->beforePivot = true;
        } elseif (isset($options['after']) && is_string($options['after'])) {
            $this->pivotId = $options['after'];
        }

        if (isset($options['id']) && is_string($options['id'])) {
            $this->id = $options['id'];
        } else {
            $this->id = md5($eventClassName . '-' . static::$counter);
        }

        static::$counter++;
    }
}
