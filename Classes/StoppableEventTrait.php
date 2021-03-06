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

namespace Neunerlei\EventBus;

/**
 * Trait StoppableEventTrait
 *
 * Used to satisfy the StoppableEventInterface contract
 *
 * @package Neunerlei\EventBus
 */
trait StoppableEventTrait
{
    /**
     * True if the propagation of the event is stopped
     *
     * @var bool
     */
    protected $propagationStopped = false;

    /**
     * Stops the propagation of the event object
     */
    public function stopPropagation(): void
    {
        $this->propagationStopped = true;
    }

    /**
     * @inheritDoc
     */
    public function isPropagationStopped(): bool
    {
        return $this->propagationStopped;
    }
}
