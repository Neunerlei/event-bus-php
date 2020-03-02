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
 * Last modified: 2020.02.27 at 10:42
 */

namespace Neunerlei\EventBus;

use Crell\Tukio\Dispatcher;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\InvalidSubscriberException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

interface EventBusInterface extends EventDispatcherInterface {
	
	/**
	 * Binds a handler to a single, or multiple events
	 *
	 * @param string[]|string $events   Either the class name of an event to listen for or a list of events to listen
	 *                                  for.
	 * @param callable        $listener A callback which is executed when the matching event is dispatched
	 * @param array           $options  Additional options
	 *                                  - priority: int (0) Can be used to define the order of handlers when bound on
	 *                                  the same event. 0 is the default the "+ range" is a higher priority (earlier)
	 *                                  the "- range" is a lower priority (later)
	 *                                  - id: string (GENERATED) The identifier by which this listener should be known.
	 *                                  If not specified one will be generated. If the id was not given,
	 *                                  it will be present after the event was bound.
	 *                                  - before: string Can be used to define the id of another listener that this
	 *                                  listener should be added before. This overrides PRIORITY and AFTER. The new
	 *                                  listener is only guaranteed to come before the specified existing listener. No
	 *                                  guarantee is made regarding when it comes relative to any other listener.
	 *                                  - after: string Can be used to define the id of another listener that this
	 *                                  listener should be added after. This overrides PRIORITY. The new listener is
	 *                                  only guaranteed to come after the specified existing listener. No guarantee is
	 *                                  made regarding when it comes relative to any other listener.
	 *
	 *
	 * @return $this
	 * @throws MissingAdapterException
	 */
	public function addListener($events, callable $listener, array $options = []): EventBusInterface;
	
	/**
	 * Adds the listeners registered in an event subscriber to the event bus
	 *
	 * @param EventSubscriberInterface $instance The instance that wants to subscribe it's methods as event listeners
	 *
	 * @return $this
	 * @see EventSubscriberInterface
	 */
	public function addSubscriber(EventSubscriberInterface $instance): EventBusInterface;
	
	/**
	 * Adds the handlers registered in an event subscriber to the event bus but the instance of the object is created
	 * only if it a matching event is dispatched
	 *
	 * @param string        $lazySubscriberClass The class which should be subscribed to the events
	 * @param callable|null $factory             An optional factory to create the subscriber class with when it is
	 *                                           required It will receive the name of the class, the instance of the
	 *                                           bus and if set the instance of the container
	 *
	 * @return $this
	 * @throws InvalidSubscriberException
	 * @throws \Neunerlei\EventBus\MissingContainerException
	 * @see LazyEventSubscriberInterface
	 */
	public function addLazySubscriber(string $lazySubscriberClass, ?callable $factory = NULL): EventBusInterface;
	
	/**
	 * Returns the concrete implementation of the event dispatcher we use internally
	 * @return \Psr\EventDispatcher\EventDispatcherInterface|Dispatcher
	 */
	public function getConcreteDispatcher(): EventDispatcherInterface;
	
	/**
	 * Sets the concrete implementation of the event dispatcher we use internally
	 *
	 * @param \Psr\EventDispatcher\EventDispatcherInterface $dispatcher
	 *
	 * @return $this
	 */
	public function setConcreteDispatcher(EventDispatcherInterface $dispatcher): EventBusInterface;
	
	/**
	 * Returns the concrete listener provider implementation used by the event dispatcher
	 * @return ListenerProviderInterface
	 */
	public function getConcreteListenerProvider(): ListenerProviderInterface;
	
	/**
	 * Sets the concrete listener provider implementation used by the event dispatcher
	 *
	 * @param \Psr\EventDispatcher\ListenerProviderInterface $provider
	 *
	 * @return $this
	 */
	public function setConcreteListenerProvider(ListenerProviderInterface $provider): EventBusInterface;
	
	/**
	 * Registers a new provider adapter for the given class or interface name.
	 *
	 * @param string   $providerClassOrInterface The class or interface name to register the adapter for
	 * @param callable $adapter                  The adapter to register for the given class or interface
	 *
	 * @return \Neunerlei\EventBus\EventBusInterface
	 */
	public function setProviderAdapter(string $providerClassOrInterface, callable $adapter): EventBusInterface;
}