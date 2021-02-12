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

namespace Neunerlei\EventBus\Subscription;


interface EventSubscriberInterface
{
    /**
     * Should add all subscribed events of this object to the given subscription using the subscribe() method.
     *
     * @param   EventSubscriptionInterface  $subscription
     *
     * @return void
     */
    public function subscribeToEvents(EventSubscriptionInterface $subscription);
}
