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


use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\EventBusInterface;
use Neunerlei\EventBus\MissingContainerException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractEventBusTest extends TestCase
{

    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        FixtureEventListenerListItemCountReset::reset();
    }

    public function testDependencyOverride()
    {
        $i = $this->getBus();
        $i->setConcreteDispatcher(new FixtureDispatcher($i->getConcreteListenerProvider()));
        self::assertInstanceOf(FixtureDispatcher::class, $i->getConcreteDispatcher());
        $i->setConcreteListenerProvider(new FixtureProvider());
        self::assertInstanceOf(FixtureProvider::class, $i->getConcreteListenerProvider());
    }

    public function testListenerBinding()
    {
        $i = $this->getBus();

        // Test single binding
        $executed = false;
        $i->addListener(FixtureEventA::class, function (FixtureEventA $eventA) use (&$executed) {
            $this->assertInstanceOf(FixtureEventA::class, $eventA);
            $executed = true;
        });
        self::assertFalse($executed);
        $e  = new FixtureEventA();
        $e2 = $i->dispatch($e);
        self::assertSame($e, $e2);
        self::assertTrue($executed);

        // Test multi binding
        $count = 0;
        $i->addListener([FixtureEventA::class, FixtureEventB::class], function ($event) use (&$count) {
            if ($count === 0) {
                $this->assertInstanceOf(FixtureEventA::class, $event);
            } else {
                $this->assertInstanceOf(FixtureEventB::class, $event);
            }
            $count++;
        });
        $i->dispatch(new FixtureEventA());
        $i->dispatch(new FixtureEventB());
        self::assertEquals(2, $count);
    }

    public function testSubscriberBinding()
    {
        $bus = $this->getBus(true);

        // Test binding with an instance
        $service = new FixtureSubscriberService();
        $bus->addSubscriber($service);
        $bus->dispatch(new FixtureEventC());
        self::assertEquals(1, $service->c);
        self::assertSame($bus, $service->bus);

        // Test binding with a lazy service
        $bus->addLazySubscriber(FixtureLazySubscriberService::class);
        $bus->dispatch(new FixtureEventC());
        self::assertEquals(1, FixtureLazySubscriberService::$c);
        self::assertSame($bus, FixtureLazySubscriberService::$bus);

        // Check if the instance was triggered again
        self::assertEquals(2, $service->c);

        // Test binding with lazy service with factory instead of container
        $bus = $this->getBus();
        $bus->addLazySubscriber(FixtureLazySubscriberService::class, function () {
            return new FixtureLazySubscriberService();
        });
        $bus->dispatch(new FixtureEventC());
        self::assertEquals(1, FixtureLazySubscriberService::$c);
        self::assertSame($bus, FixtureLazySubscriberService::$bus);
    }

    public function testIfLazySubscriberWithoutFactoryOrContainerFails()
    {
        $this->expectException(MissingContainerException::class);
        $bus = $this->getBus();
        $bus->addLazySubscriber(FixtureLazySubscriberService::class);
    }

    public function testListenerPriority()
    {
        $bus = $this->getBus();
        $c   = 0;
        $bus->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        }, ["priority" => -10]);

        $bus->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        });

        $bus->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        }, ["priority" => 10]);

        $bus->dispatch(new FixtureEventA());

        self::assertEquals(3, $c);
    }

    public function testIdActions()
    {
        $bus = $this->getBus();

        // Test if auto-generation works
        $eventId = null;
        $c1      = 0;
        $c2      = 0;
        $bus->addListener(FixtureEventA::class, function () use (&$c1) {
            $this->assertEquals(1, $c1++);
        }, ["id" => &$eventId]);
        self::assertIsString($eventId);

        // Test if setting and id based ordering works (BEFORE)
        $bus->addListener(FixtureEventA::class, function () use (&$c2) {
            $this->assertEquals(1, $c2++);
        }, ["id" => "myId"]);

        $bus->addListener(FixtureEventA::class, function () use (&$c2) {
            $this->assertEquals(0, $c2++);
        }, ["before" => "myId"]);

        $bus->addListener(FixtureEventA::class, function () use (&$c1) {
            $this->assertEquals(0, $c1++);
        }, ["before" => $eventId]);

        $bus->dispatch(new FixtureEventA());
        self::assertEquals(2, $c2);
        self::assertEquals(2, $c1);

        // Test if id bast ordering works (AFTER)
        $c1 = 0;
        $bus->addListener(FixtureEventB::class, function () use (&$c1) {
            $this->assertEquals(1, $c1++);
        }, ["after" => "myId"]);

        $bus->addListener(FixtureEventB::class, function () use (&$c1) {
            $this->assertEquals(0, $c1++);
        }, ["id" => "myId"]);
        $bus->dispatch(new FixtureEventB());
        self::assertEquals(2, $c1);
    }

    public function testStoppableEvents()
    {
        $bus = $this->getBus();
        $c   = 0;
        $bus->addListener(FixtureStoppableEvent::class, function (FixtureStoppableEvent $event) use (&$c) {
            $event->stopPropagation();
            $c++;
        });
        $bus->addListener(FixtureStoppableEvent::class, function (FixtureStoppableEvent $event) {
            $this->fail("The event was not stopped!");
        });
        $e = $bus->dispatch(new FixtureStoppableEvent());
        self::assertTrue($e->isPropagationStopped());
        self::assertEquals(1, $c);
    }

    public function testOneTimeEvents()
    {
        $bus = $this->getBus();
        $a   = 0;
        $b   = 0;
        $c   = 0;
        $d   = 0;
        $bus->addListener(FixtureEventA::class, function () use (&$d) {
            $d++;
        });
        $bus->addListener(FixtureEventA::class, function () use (&$a) {
            $a++;
        }, ['once']);
        $bus->addListener(FixtureEventB::class, function () use (&$b) {
            $b++;
        }, ['once']);
        $bus->addListener(FixtureEventC::class, function ($e) use (&$c) {
            static::assertInstanceOf(FixtureEventC::class, $e);
            $c++;
        }, ['once']);
        for ($i = 0; $i < 10; $i++) {
            $bus->dispatch(new FixtureEventA());
            $bus->dispatch(new FixtureEventB());
            $bus->dispatch(new FixtureEventC());
        }

        static::assertEquals(1, $a);
        static::assertEquals(1, $b);
        static::assertEquals(1, $c);
        static::assertEquals(20, $d); // 10 times for FixtureEventA and 10 more for FixtureEventC through inheritance
    }

    protected function getBus(bool $withContainer = false): EventBusInterface
    {
        $container = null;
        if ($withContainer) {
            $container = new class implements ContainerInterface {
                public function get($id)
                {
                    return new $id();
                }

                public function has($id)
                {
                    if ($id === LoggerInterface::class) {
                        return false;
                    }

                    return true;
                }
            };
        }

        return new EventBus($container);
    }
}
