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
 * Last modified: 2020.05.21 at 15:31
 */

namespace Neunerlei\EventBus\Dispatcher;


use IteratorAggregate;

class EventListenerList implements IteratorAggregate
{

    /**
     * A lookup table to find the item instances using their unique ID
     *
     * @var \Neunerlei\EventBus\Dispatcher\EventListenerListItem[]
     */
    protected $itemsById = [];

    /**
     * The sorted list of items where each priority is an array of items
     *
     * @var \Neunerlei\EventBus\Dispatcher\EventListenerListItem[][]
     */
    protected $items = [];

    /**
     * The list of new items that have to be sorted if $isDirty is true
     *
     * @var \Neunerlei\EventBus\Dispatcher\EventListenerListItem[]
     */
    protected $itemsToSort = [];

    /**
     * True when the internal list of listeners is not yet sorted based on the options
     *
     * @var bool
     */
    protected $isDirty = false;

    /**
     * Adds a new item to the list
     *
     * @param   \Neunerlei\EventBus\Dispatcher\EventListenerListItem  $item
     */
    public function add(EventListenerListItem $item): void
    {
        $this->isDirty                = true;
        $this->itemsById[$item->id]   = $item;
        $this->itemsToSort[$item->id] = $item;
    }

    /**
     * Removes an item from the list that was previously registered.
     * If the item does not exist, it is ignored silently
     *
     * @param   string|callable|EventListenerListItem  $idItemOrListener  The id of the listener,
     *                                                                    the listener item or the listener callback
     *                                                                    to remove from the list
     */
    public function remove($idItemOrListener): void
    {
        if ($idItemOrListener instanceof EventListenerListItem) {
            $item = $idItemOrListener;
        } elseif (is_string($idItemOrListener) && isset($this->itemsById[$idItemOrListener])) {
            $item = $this->itemsById[$idItemOrListener];
        } elseif (is_callable($idItemOrListener)) {
            $list = $this->itemsById;
            foreach ($list as $lookupItem) {
                if ($lookupItem->listener === $idItemOrListener) {
                    $this->remove($lookupItem);
                }
            }

            return;
        } else {
            return;
        }

        unset(
            $this->items[$item->priority][$item->id],
            $this->itemsById[$item->id],
            $this->itemsToSort[$item->id]
        );
    }

    /**
     * @inheritDoc
     * @return \Neunerlei\EventBus\Dispatcher\EventListenerListItem[]|iterable
     */
    public function getIterator()
    {
        if ($this->isDirty) {
            $this->sortItems();
        }
        foreach ($this->items as $priorityList) {
            foreach ($priorityList as $item) {
                yield $item;
            }
        }
    }

    /**
     * Sorts the items in the list according to the given options
     *
     * @throws \Neunerlei\EventBus\Dispatcher\CircularPivotIdException
     */
    protected function sortItems(): void
    {
        // Sort all items that don't have a pivot id
        $itemsWithPivotId = [];
        foreach ($this->itemsToSort as $item) {
            if ($item->pivotId !== null) {
                $itemsWithPivotId[$item->id] = $item;
                continue;
            }

            $this->items[$item->priority][$item->id] = $item;
        }

        // Sort items with pivot id -> sort dependencies recursively
        // use $c and $limit as an arbitrary limiter to avoid endless loops
        $limit = count($itemsWithPivotId) * 15;
        $c     = 0;
        while (! empty($itemsWithPivotId) && $c < $limit) {
            $c++;
            $itemsWithPivotIdFiltered = [];

            foreach ($itemsWithPivotId as $item) {
                // Check if it is easy -> Element is NOT in the new element list
                if (isset($itemsWithPivotId[$item->pivotId])) {
                    // Keep the item for the next loop
                    $itemsWithPivotIdFiltered[$item->id] = $item;
                } else {
                    // Set the item directly
                    $pivotItem                               = $this->itemsById[$item->pivotId];
                    $item->priority                          = $pivotItem->priority;
                    $item->priority                          += $item->beforePivot ? 1 : -1;
                    $this->items[$item->priority][$item->id] = $item;
                }
            }

            $itemsWithPivotId = $itemsWithPivotIdFiltered;
        }

        if (! empty($itemsWithPivotId)) {
            throw new CircularPivotIdException(
                'You have an issue with your event\'s pivot id\'s! The pivot id\'s that failed are: '
                . implode(', ', array_keys($itemsWithPivotId)));
        }

        // Order the list by their keys
        krsort($this->items);

        // Done
        $this->itemsToSort = [];
        $this->isDirty     = false;
    }
}
