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
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\EventBusInterface;

/**
 * Class EventBusTukioTest
 *
 * Tests the event handling implementation using crell\tukio
 *
 * @package Neunerlei\EventBus\Tests\Assets
 */
class EventBusTukioTest extends AbstractEventBusTest
{
    public function testDependencyInstantiation(): void
    {
        $i = $this->getBus();
        self::assertInstanceOf(EventBusInterface::class, $i);
        self::assertInstanceOf(EventBus::class, $i);

        self::assertInstanceOf(Dispatcher::class, $i->getConcreteDispatcher());
        self::assertInstanceOf(OrderedListenerProvider::class, $i->getConcreteListenerProvider());
    }

    public function testOnceProxyReuse(): void
    {
        $i = $this->getBus();
        // The proxy should be reused -> therefore the last listener id should be the same
        $i->addListener('foo', static function () { }, ['once']);
        $id = $i->getLastListenerId();
        $i->addListener('foo', static function () { }, ['once']);
        static::assertEquals($id, $i->getLastListenerId());

        // The options differ to the previous proxy -> this should generate a new proxy id
        $i->addListener('foo', static function () { }, ['once', 'priority' => 100]);
        static::assertNotEquals($id, $i->getLastListenerId());

        // A new event requires a new proxy
        $i->addListener('bar', static function () { }, ['once']);
        static::assertNotEquals($id, $i->getLastListenerId());
    }

    public function testOnceObjectSerialization(): void
    {
        $i = $this->getBus();
        $i->addListener('foo', static function () { }, ['once', 'someOption' => function () { }]);
        $id = $i->getLastListenerId();
        static::assertIsString($id);
        $i->addListener('foo', static function () { }, ['once', 'someOption' => $i]);
        static::assertIsString($i->getLastListenerId());
        static::assertNotEquals($id, $i->getLastListenerId());

    }

    protected function getBus(bool $withContainer = false): EventBusInterface
    {
        $i = parent::getBus($withContainer);
        $i->setConcreteListenerProvider(new OrderedListenerProvider());
        $i->setConcreteDispatcher(new Dispatcher($i->getConcreteListenerProvider()));

        return $i;
    }
}
