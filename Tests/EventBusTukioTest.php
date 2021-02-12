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
    public function testDependencyInstantiation()
    {
        $i = $this->getBus();
        $this->assertInstanceOf(EventBusInterface::class, $i);
        $this->assertInstanceOf(EventBus::class, $i);

        $this->assertInstanceOf(Dispatcher::class, $i->getConcreteDispatcher());
        $this->assertInstanceOf(OrderedListenerProvider::class, $i->getConcreteListenerProvider());
    }

    protected function getBus(bool $withContainer = false): EventBusInterface
    {
        $i = parent::getBus($withContainer);
        $i->setConcreteListenerProvider(new OrderedListenerProvider());
        $i->setConcreteDispatcher(new Dispatcher($i->getConcreteListenerProvider()));

        return $i;
    }
}
