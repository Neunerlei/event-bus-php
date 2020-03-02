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
 * Last modified: 2020.03.01 at 18:54
 */

namespace Neunerlei\EventBus\Tests;

use Crell\Tukio\Dispatcher;
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\EventBusInterface;
use Neunerlei\EventBus\MissingContainerException;
use Neunerlei\EventBus\Tests\Assets\DummyDispatcher;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;
use Neunerlei\EventBus\Tests\Assets\DummyEventB;
use Neunerlei\EventBus\Tests\Assets\DummyEventC;
use Neunerlei\EventBus\Tests\Assets\DummyLazySubscriberService;
use Neunerlei\EventBus\Tests\Assets\DummyProvider;
use Neunerlei\EventBus\Tests\Assets\DummyStoppableEvent;
use Neunerlei\EventBus\Tests\Assets\DummySubscriberService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;

class EventBusTest extends TestCase {
	public function testDependencyInstantiation() {
		$i = $this->getBus();
		$this->assertInstanceOf(EventBusInterface::class, $i);
		$this->assertInstanceOf(EventBus::class, $i);
		
		$this->assertInstanceOf(Dispatcher::class, $i->getConcreteDispatcher());
		$this->assertInstanceOf(ListenerProviderInterface::class, $i->getConcreteListenerProvider());
	}
	
	public function testDependencyOverride() {
		$i = $this->getBus();
		$i->setConcreteDispatcher(new DummyDispatcher($i->getConcreteListenerProvider()));
		$this->assertInstanceOf(DummyDispatcher::class, $i->getConcreteDispatcher());
		$i->setConcreteListenerProvider(new DummyProvider());
		$this->assertInstanceOf(DummyProvider::class, $i->getConcreteListenerProvider());
	}
	
	public function testListenerBinding() {
		$i = $this->getBus();
		
		// Test single binding
		$executed = FALSE;
		$i->addListener(DummyEventA::class, function (DummyEventA $eventA) use (&$executed) {
			$this->assertInstanceOf(DummyEventA::class, $eventA);
			$executed = TRUE;
		});
		$this->assertFalse($executed);
		$e = new DummyEventA();
		$e2 = $i->dispatch($e);
		$this->assertSame($e, $e2);
		$this->assertTrue($executed);
		
		// Test multi binding
		$count = 0;
		$i->addListener([DummyEventA::class, DummyEventB::class], function ($event) use (&$count) {
			if ($count === 0) $this->assertInstanceOf(DummyEventA::class, $event);
			else $this->assertInstanceOf(DummyEventB::class, $event);
			$count++;
		});
		$i->dispatch(new DummyEventA());
		$i->dispatch(new DummyEventB());
		$this->assertEquals(2, $count);
	}


//	public function testSubscriberBindingWithoutContainer() {
//		$this->expectException(ContainerMissingException::class);
//		$bus = $this->getBus();
//		$bus->addSubscriber(DummySubscriberService::class);
//	}
	
	public function testSubscriberBinding() {
		$bus = $this->getBus(TRUE);
		
		// Test binding with an instance
		$service = new DummySubscriberService();
		$bus->addSubscriber($service);
		$bus->dispatch(new DummyEventC());
		$this->assertEquals(1, $service->c);
		$this->assertSame($bus, $service->bus);
		
		// Test binding with a lazy service
		$bus->addLazySubscriber(DummyLazySubscriberService::class);
		$bus->dispatch(new DummyEventC());
		$this->assertEquals(1, DummyLazySubscriberService::$c);
		$this->assertSame($bus, DummyLazySubscriberService::$bus);
		
		// Check if the instance was triggered again
		$this->assertEquals(2, $service->c);
		
		// Test binding with lazy service with factory instead of container
		$bus = $this->getBus();
		$bus->addLazySubscriber(DummyLazySubscriberService::class, function () {
			return new DummyLazySubscriberService();
		});
		$bus->dispatch(new DummyEventC());
		$this->assertEquals(1, DummyLazySubscriberService::$c);
		$this->assertSame($bus, DummyLazySubscriberService::$bus);
	}
	
	public function testIfLazySubscriberWithoutFactoryOrContainerFails() {
		$this->expectException(MissingContainerException::class);
		$bus = $this->getBus();
		$bus->addLazySubscriber(DummyLazySubscriberService::class);
	}
	
	public function testListenerPriority() {
		$bus = $this->getBus();
		$c = 0;
		$bus->addListener(DummyEventA::class, function () use (&$c) {
			$this->assertEquals(2, $c++);
		}, ["priority" => -10]);
		
		$bus->addListener(DummyEventA::class, function () use (&$c) {
			$this->assertEquals(1, $c++);
		});
		
		$bus->addListener(DummyEventA::class, function () use (&$c) {
			$this->assertEquals(0, $c++);
		}, ["priority" => 10]);
		
		$bus->dispatch(new DummyEventA());
		
		$this->assertEquals(3, $c);
	}
	
	public function testIdActions() {
		$bus = $this->getBus();
		
		// Test if auto-generation works
		$eventId = NULL;
		$c1 = 0;
		$c2 = 0;
		$bus->addListener(DummyEventA::class, function () use (&$c1) {
			$this->assertEquals(1, $c1++);
		}, ["id" => &$eventId]);
		$this->assertIsString($eventId);
		
		// Test if setting and id based ordering works (BEFORE)
		$bus->addListener(DummyEventA::class, function () use (&$c2) {
			$this->assertEquals(1, $c2++);
		}, ["id" => "myId"]);
		
		$bus->addListener(DummyEventA::class, function () use (&$c2) {
			$this->assertEquals(0, $c2++);
		}, ["before" => "myId"]);
		
		$bus->addListener(DummyEventA::class, function () use (&$c1) {
			$this->assertEquals(0, $c1++);
		}, ["before" => $eventId]);
		
		$bus->dispatch(new DummyEventA());
		$this->assertEquals(2, $c2);
		$this->assertEquals(2, $c1);
		
		// Test if id bast ordering works (AFTER)
		$c1 = 0;
		$bus->addListener(DummyEventB::class, function () use (&$c1) {
			$this->assertEquals(1, $c1++);
		}, ["after" => "myId"]);
		
		$bus->addListener(DummyEventB::class, function () use (&$c1) {
			$this->assertEquals(0, $c1++);
		}, ["id" => "myId"]);
		$bus->dispatch(new DummyEventB());
		$this->assertEquals(2, $c1);
	}
	
	public function testStoppableEvents() {
		$bus = $this->getBus();
		$c = 0;
		$bus->addListener(DummyStoppableEvent::class, function (DummyStoppableEvent $event) use (&$c) {
			$event->stopPropagation();
			$c++;
		});
		$bus->addListener(DummyStoppableEvent::class, function (DummyStoppableEvent $event) {
			$this->fail("The event was not stopped!");
		});
		$e = $bus->dispatch(new DummyStoppableEvent());
		$this->assertTrue($e->isPropagationStopped());
		$this->assertEquals(1, $c);
	}
	
	protected function getBus(bool $withContainer = FALSE): EventBusInterface {
		$container = NULL;
		if ($withContainer) {
			$container = new class implements ContainerInterface {
				public function get($id) {
					return new $id();
				}
				
				public function has($id) {
					if ($id === LoggerInterface::class) return FALSE;
					return TRUE;
				}
			};
		}
		return new EventBus($container);
	}
}