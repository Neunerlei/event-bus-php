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
 * Last modified: 2020.02.28 at 19:48
 */

namespace Neunerlei\EventBus;


use Crell\Tukio\Dispatcher;
use Crell\Tukio\OrderedListenerProvider;
use Crell\Tukio\OrderedProviderInterface;
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\EventSubscription;
use Neunerlei\EventBus\Subscription\InvalidSubscriberException;
use Neunerlei\EventBus\Subscription\LazyEventSubscriberInterface;
use Neunerlei\EventBus\Subscription\LazyEventSubscription;
use Neunerlei\Options\Options;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\Log\LoggerInterface;

class EventBus implements EventDispatcherInterface, ListenerProviderInterface, EventBusInterface, SingletonInterface {
	/**
	 * Holds the container instance or is null
	 * @var \Psr\Container\ContainerInterface
	 */
	protected $container;
	
	/**
	 * The concrete event dispatcher this instance wraps around
	 * @var EventDispatcherInterface
	 */
	protected $concreteDispatcher;
	
	/**
	 * The concrete listener provider implementation used by the event dispatcher
	 * @var ListenerProviderInterface
	 */
	protected $concreteProvider;
	
	/**
	 * The list of registered listener provider adapters
	 * @var array
	 */
	protected $providerAdapters = [];
	
	/**
	 * Holds the class/interface name of the adapter we should use to bind to events
	 * @var string|null
	 */
	protected $suggestedAdapter;
	
	/**
	 * EventBus constructor.
	 *
	 * @param \Psr\Container\ContainerInterface $container
	 */
	public function __construct(?ContainerInterface $container = NULL) {
		$this->container = $container;
		
		// Register provider adapters
		$this->providerAdapters = [
			OrderedProviderInterface::class => function (OrderedProviderInterface $provider, string $event, callable $listener, array $options) {
				// Validate options
				$options = Options::make($options, [
					"priority" => [
						"type"    => "int",
						"default" => 0,
					],
					"id"       => [
						"type"    => ["string", "null"],
						"default" => NULL,
					],
					"before"   => [
						"type"    => ["string", "null"],
						"default" => NULL,
					],
					"after"    => [
						"type"    => ["string", "null"],
						"default" => NULL,
					],
				]);
				
				// Register the listener
				if (!empty($options["before"]))
					return $provider->addListenerBefore($options["before"], $listener, $options["id"], $event);
				else if (!empty($options["after"]))
					return $provider->addListenerAfter($options["after"], $listener, $options["id"], $event);
				return $provider->addListener($listener, $options["priority"], $options["id"], $event);
			},
		];
	}
	
	
	/**
	 * @inheritDoc
	 */
	public function addListener($events, callable $listener, array $options = []): EventBusInterface {
		if (is_iterable($events))
			foreach ($events as $event)
				$this->addListener($event, $listener, $options);
		else {
			
			// Find the correct adapter we should use
			$provider = $this->getConcreteListenerProvider();
			$adapter = NULL;
			if (!is_string($this->suggestedAdapter) || !$provider instanceof $this->suggestedAdapter) {
				$this->suggestedAdapter = NULL;
				foreach ($this->providerAdapters as $class => $_adapter) {
					if (!$provider instanceof $class) continue;
					$this->suggestedAdapter = $class;
					break;
				}
				if (empty($this->suggestedAdapter))
					throw new MissingAdapterException("Could not resolve a listener provider adapter class!");
			}
			$adapter = $this->providerAdapters[$this->suggestedAdapter];
			
			// Validate event
			$events = Options::makeSingle("events", $events, [
				"type" => "string",
			]);
			
			// Call the real handler implementation
			$id = call_user_func($adapter, $provider, $events, $listener, $options);
			if (empty($options["id"])) $options["id"] = $id;
		}
		
		// Done
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function addSubscriber(EventSubscriberInterface $instance): EventBusInterface {
		$subscription = new EventSubscription($this, $instance);
		$instance->subscribeToEvents($subscription);
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function addLazySubscriber(string $subscriberClass, ?callable $factory = NULL): EventBusInterface {
		
		// Check if the class implements the required interface
		if (!in_array(LazyEventSubscriberInterface::class, class_implements($subscriberClass)))
			throw new InvalidSubscriberException("The given lazy subscriber: " . $subscriberClass .
				" does not implement the required interface: " . LazyEventSubscriberInterface::class);
		
		// Prepare the factory
		if (empty($factory)) {
			// Check if we have a container
			if (empty($this->container))
				throw new MissingContainerException("Could not add a lazy subscriber, because there is neither a " .
					"factory, nor a container to instantiate the class: \"$subscriberClass\"!");
			
			$factory = function () use ($subscriberClass) {
				return $this->container->get($subscriberClass);
			};
		}
		
		// Create the subscription
		call_user_func([$subscriberClass, "subscribeToEvents"], new LazyEventSubscription($this, $factory));
		
		// Done
		return $this;
	}
	
	
	/**
	 * @inheritDoc
	 */
	public function dispatch(object $event) {
		return $this->getConcreteDispatcher()->dispatch($event);
	}
	
	/**
	 * @inheritDoc
	 */
	public function getListenersForEvent(object $event): iterable {
		return $this->getConcreteListenerProvider()->getListenersForEvent($event);
	}
	
	/**
	 * @inheritDoc
	 */
	public function setConcreteDispatcher(EventDispatcherInterface $dispatcher): EventBusInterface {
		$this->concreteDispatcher = $dispatcher;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getConcreteDispatcher(): EventDispatcherInterface {
		if (empty($this->concreteDispatcher))
			$this->concreteDispatcher = new Dispatcher($this->getConcreteListenerProvider(),
				(!empty($this->container) && $this->container->has(LoggerInterface::class) ?
					$this->container->get(LoggerInterface::class) : NULL));
		return $this->concreteDispatcher;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setConcreteListenerProvider(ListenerProviderInterface $provider): EventBusInterface {
		$this->concreteProvider = $provider;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getConcreteListenerProvider(): ListenerProviderInterface {
		if (empty($this->concreteProvider)) $this->concreteProvider = new OrderedListenerProvider();
		return $this->concreteProvider;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setProviderAdapter(string $providerClassOrInterface, callable $adapter): EventBusInterface {
		$this->providerAdapters[$providerClassOrInterface] = $adapter;
		return $this;
	}
}