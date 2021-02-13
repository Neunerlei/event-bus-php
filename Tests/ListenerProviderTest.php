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
 * Last modified: 2020.05.21 at 16:10
 */

namespace Neunerlei\EventBus\Tests;


use Neunerlei\EventBus\Dispatcher\CircularPivotIdException;
use Neunerlei\EventBus\Dispatcher\EventBusListenerProvider;
use Neunerlei\EventBus\Tests\Assets\FixtureEventA;
use Neunerlei\EventBus\Tests\Assets\FixtureEventB;
use Neunerlei\EventBus\Tests\Assets\FixtureEventC;
use Neunerlei\EventBus\Tests\Assets\FixtureEventListenerListItemCountReset;
use PHPUnit\Framework\TestCase;

class ListenerProviderTest extends TestCase
{
    /**
     * @inheritDoc
     */
    public static function setUpBeforeClass(): void
    {
        FixtureEventListenerListItemCountReset::reset();
    }

    /**
     * Tests if the listeners are kept in correct order
     */
    public function testDefaultBinding()
    {
        $provider = $this->getInstance();
        $c        = 0;
        $id       = $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        });
        self::assertEquals(md5(FixtureEventA::class . "-" . 0), $id);
        $id = $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        });
        self::assertEquals(md5(FixtureEventA::class . "-" . 1), $id);
        $id = $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        });
        self::assertEquals(md5(FixtureEventA::class . "-" . 2), $id);

        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(4, $c++);
        });
        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(5, $c++);
        });
        $id = $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(6, $c++);
        });
        self::assertEquals(md5(FixtureEventB::class . "-" . 5), $id);

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(3, $c++);
        });

        $provider->addListener(FixtureEventC::class, function () {
            $this->fail("This should not be executed!");
        });

        foreach ($provider->getListenersForEvent(new FixtureEventA()) as $listener) {
            call_user_func($listener);
        }
        foreach ($provider->getListenersForEvent(new FixtureEventB()) as $listener) {
            call_user_func($listener);
        }
    }

    public function testPriorityBinding()
    {
        $provider = $this->getInstance();
        $c        = 0;

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        });
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        }, ["priority" => -400]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        }, ["priority" => -500]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(3, $c++);
        }, ["priority" => -500]);


        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(5, $c++);
        }, ["priority" => 500]);
        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(6, $c++);
        }, ["priority" => 200]);
        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(7, $c++);
        });

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(4, $c++);
        }, ["priority" => -700]);

        $provider->addListener(FixtureEventC::class, function () {
            $this->fail("This should not be executed!");
        });

        foreach ($provider->getListenersForEvent(new FixtureEventA()) as $listener) {
            call_user_func($listener);
        }
        foreach ($provider->getListenersForEvent(new FixtureEventB()) as $listener) {
            call_user_func($listener);
        }
    }

    public function testBeforeAfterBinding()
    {
        $provider = $this->getInstance();
        $c        = 0;

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        }, ["before" => "3", "id" => "2"]);

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        }, ["before" => "2", "id" => "1"]);

        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        }, ["before" => "1", "id" => "0"]);

        $id = $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(4, $c++);
        }, ["after" => "3", "id" => "4"]);
        self::assertEquals("4", $id);

        $id = $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(3, $c++);
        }, ["id" => "3"]);
        self::assertEquals("3", $id);


        $id = $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(5, $c++);
        }, ["id" => "5"]);

        $id = $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(7, $c++);
        }, ["after" => $id, "id" => "7"]);

        $provider->addListener(FixtureEventB::class, function () use (&$c) {
            $this->assertEquals(6, $c++);
        }, ["before" => $id, "id" => "6"]);


        $provider->addListener(FixtureEventC::class, function () {
            $this->fail("This should not be executed!");
        });

        foreach ($provider->getListenersForEvent(new FixtureEventA()) as $listener) {
            call_user_func($listener);
        }
        foreach ($provider->getListenersForEvent(new FixtureEventB()) as $listener) {
            call_user_func($listener);
        }
    }

    public function testMixedBinding()
    {
        $provider = $this->getInstance();
        $c        = 0;
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        }, ["priority" => 100, "id" => "1"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        }, ["before" => "1", "id" => "0"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        }, ["priority" => 50, "id" => "2"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(3, $c++);
        }, ["before" => "4", "id" => "3"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(4, $c++);
        }, ["after" => 2, "id" => "4"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(5, $c++);
        }, ["id" => "5"]);

        foreach ($provider->getListenersForEvent(new FixtureEventA()) as $listener) {
            call_user_func($listener);
        }
    }

    public function testParentClassBinding()
    {
        $provider = $this->getInstance();
        $c        = 0;
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(0, $c++);
        }, ["id" => "0"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(1, $c++);
        }, ["id" => "1"]);
        $provider->addListener(FixtureEventC::class, function () use (&$c) {
            $this->assertEquals(2, $c++);
        }, ["id" => "2"]);
        $provider->addListener(FixtureEventC::class, function () use (&$c) {
            $this->assertEquals(3, $c++);
        }, ["id" => "3"]);
        $provider->addListener(FixtureEventC::class, function () use (&$c) {
            $this->assertEquals(5, $c++);
        }, ["id" => "5", "after" => "4"]);
        $provider->addListener(FixtureEventA::class, function () use (&$c) {
            $this->assertEquals(4, $c++);
        }, ["priority" => -200, "id" => "4"]);


        foreach ($provider->getListenersForEvent(new FixtureEventC()) as $listener) {
            call_user_func($listener);
        }
    }

    public function testExceptionOnCircularPivotId()
    {
        $this->expectException(CircularPivotIdException::class);
        $this->expectDeprecationMessage("You have an issue with your event's pivot id's! The pivot id's that failed are: 0, 1, 2");
        $provider = $this->getInstance();
        $provider->addListener(FixtureEventA::class, function () { }, [
            "before" => "1",
            "id"     => "0",
        ]);
        $provider->addListener(FixtureEventA::class, function () { }, [
            "before" => "2",
            "id"     => "1",
        ]);
        $provider->addListener(FixtureEventA::class, function () { }, [
            "before" => "1",
            "id"     => "2",
        ]);
    }

    public function testBindingRemoval()
    {
        $provider = $this->getInstance();

        // Removal by id
        $id = $provider->addListener(FixtureEventA::class, function () {
            $this->fail("The event was not removed as it should have been!");
        });
        $provider->removeListener($id);
        $id = $provider->addListener(FixtureEventB::class, function () {
            $this->fail("The event was not removed as it should have been!");
        });
        $provider->removeListener($id);

        // Removal by callback
        $callback = function () {
            $this->fail("The event was not removed as it should have been!");
        };
        $provider->addListener(FixtureEventA::class, $callback);
        $provider->addListener(FixtureEventB::class, $callback);
        $provider->removeListener($callback);

        // Remove invalid values
        $provider->removeListener("asdfasdfasdf");
        $provider->removeListener(null);
        $provider->removeListener(function () { });

        foreach ($provider->getListenersForEvent(new FixtureEventA()) as $listener) {
            call_user_func($listener);
        }
        foreach ($provider->getListenersForEvent(new FixtureEventB()) as $listener) {
            call_user_func($listener);
        }

        // We reached this point -> so everything is fine
        self::assertTrue(true);
    }

    protected function getInstance(): EventBusListenerProvider
    {
        return new EventBusListenerProvider();
    }
}
