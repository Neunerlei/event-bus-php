<?php
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
 * Last modified: 2020.03.02 at 10:49
 */

namespace Neunerlei\EventBus\Subscription;


use Neunerlei\EventBus\EventBusInterface;

interface EventSubscriptionInterface {
	/**
	 * Subscribes a given method to the list of events
	 *
	 * @param array|string $events   The events to trigger this callback for. Multiple events can be separated via
	 *                               empty space, or by using an array of multiple values
	 * @param string       $method   The name of a method that should be subscribed to the given events.
	 *                               NOTE: The method has to be public and available on the current instance
	 * @param array        $options  Additional options
	 *                               - priority: int (0) Can be used to define the order of handlers when bound on the
	 *                               same event. 0 is the default the + range is a higher priority (earlier) the - range
	 *                               is a lower priority (later)
	 *
	 * @return EventSubscriptionInterface
	 */
	public function subscribe($events, string $method, array $options = []): EventSubscriptionInterface;
	
	/**
	 * Returns the event bus instance
	 *
	 * @return EventBusInterface
	 */
	public function getBus(): EventBusInterface;
}