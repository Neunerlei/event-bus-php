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

namespace Neunerlei\EventBus\Tests\Assets;

use Crell\Tukio\Dispatcher;
use Crell\Tukio\OrderedListenerProvider;
use Neunerlei\EventBus\AbstractStoppableEvent;
use Neunerlei\EventBus\Dispatcher\EventListenerListItem;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\EventSubscriptionInterface;
use Neunerlei\EventBus\Subscription\LazyEventSubscriberInterface;

class FixtureEventA
{
}

class FixtureEventB
{
}

class FixtureEventC extends FixtureEventA
{
}

class FixtureStoppableEvent extends AbstractStoppableEvent
{
}

class FixtureDispatcher extends Dispatcher
{
}

class FixtureProvider extends OrderedListenerProvider
{
}

class FixtureLazySubscriberService implements LazyEventSubscriberInterface
{
    public static $c = 0;
    public static $bus;

    /**
     * @inheritDoc
     */
    public static function subscribeToEvents(EventSubscriptionInterface $subscription)
    {
        $subscription->subscribe(FixtureEventA::class, "onTest");
        static::$bus = $subscription->getBus();
    }

    public function onTest(FixtureEventA $eventC)
    {
        self::$c = 1;
    }
}

class FixtureSubscriberService implements EventSubscriberInterface
{
    public $c = 0;
    public $bus;

    /**
     * @inheritDoc
     */
    public function subscribeToEvents(EventSubscriptionInterface $subscription)
    {
        $subscription->subscribe(FixtureEventA::class, "onTest");
        $this->bus = $subscription->getBus();
    }

    public function onTest(FixtureEventA $eventC)
    {
        $this->c++;
    }
}

class FixtureEventListenerListItemCountReset extends EventListenerListItem
{
    /**
     * Helper to reset the unique id counter to avoid tainting the different test scenarios
     */
    public static function reset()
    {
        EventListenerListItem::$counter = 0;
    }
}
