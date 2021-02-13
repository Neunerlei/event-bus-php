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

namespace Neunerlei\EventBus\Tests;

use Neunerlei\EventBus\Dispatcher\EventBusDispatcher;
use Neunerlei\EventBus\Dispatcher\EventBusListenerProvider;
use Neunerlei\EventBus\Dispatcher\EventListenerListItem;
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\EventBusInterface;
use Neunerlei\EventBus\MissingAdapterException;
use Neunerlei\EventBus\Tests\Assets\AbstractEventBusTest;
use Neunerlei\EventBus\Tests\Assets\FixtureContainer;
use Neunerlei\EventBus\Tests\Assets\FixtureEventA;
use Neunerlei\EventBus\Tests\Assets\FixtureStandAloneProvider;
use Neunerlei\EventBus\Tests\Assets\FixtureStoppableEvent;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class EventBusTest
 *
 * Tests the default implementation of the listener provider and the dispatcher
 *
 * @package Neunerlei\EventBus\Tests
 */
class EventBusTest extends AbstractEventBusTest
{
    public function testDependencyInstantiation()
    {
        $i = $this->getBus();
        self::assertInstanceOf(EventBusInterface::class, $i);
        self::assertInstanceOf(EventBus::class, $i);

        self::assertInstanceOf(EventBusDispatcher::class, $i->getConcreteDispatcher());
        self::assertInstanceOf(EventBusListenerProvider::class, $i->getConcreteListenerProvider());
    }

    public function testMissingAdapterFail()
    {
        $this->expectException(MissingAdapterException::class);
        $i = $this->getBus();
        $i->setConcreteListenerProvider(new class implements ListenerProviderInterface {
            public function getListenersForEvent(object $event): iterable { }
        });
        $i->addListener('foo', static function () { });
    }

    public function testCustomProviderRegistration()
    {
        $i              = $this->getBus();
        $calledProvider = false;
        $provider       = new FixtureStandAloneProvider(function ($event) use (&$calledProvider) {
            static::assertInstanceOf(FixtureEventA::class, $event);
            $calledProvider = true;
        });

        $i->setConcreteListenerProvider($provider);
        $calledAdapter = false;
        $i->setProviderAdapter(FixtureStandAloneProvider::class, static function (
            $provider,
            $eventName,
            $item,
            $options
        ) use (&$calledAdapter) {
            static::assertInstanceOf(FixtureStandAloneProvider::class, $provider);
            static::assertEquals(FixtureEventA::class, $eventName);
            static::assertInstanceOf(EventListenerListItem::class, $item);
            static::assertIsArray($options);
            static::assertFalse($item->once);
            static::assertInstanceOf(\Closure::class, $item->listener);
            $calledAdapter = true;
        });

        $i->addListener(FixtureEventA::class, function () { });

        static::assertEquals([], $i->getListenersForEvent(new FixtureEventA()));

        static::assertTrue($calledProvider);
        static::assertTrue($calledAdapter);
    }

    public function testGetSetContainer()
    {
        $i = $this->getBus();
        static::assertNull($i->getContainer());
        $c = new FixtureContainer();
        $i->setContainer($c);
        static::assertSame($c, $i->getContainer());
    }

    public function testIfStoppedEventsDontGetDispatched()
    {
        $this->expectNotToPerformAssertions();
        $i = $this->getBus();
        $e = new FixtureStoppableEvent();
        $e->stopPropagation();
        $i->addListener(FixtureStoppableEvent::class, static function () {
            static::fail('An already stopped event has been dispatched!');
        });
        $i->dispatch($e);
    }
}
