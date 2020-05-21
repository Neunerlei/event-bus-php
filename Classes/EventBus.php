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


use Crell\Tukio\OrderedProviderInterface;
use InvalidArgumentException;
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;
use Neunerlei\EventBus\Dispatcher\EventBusDispatcher;
use Neunerlei\EventBus\Dispatcher\EventBusListenerProvider;
use Neunerlei\EventBus\Dispatcher\EventListenerListItem;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\EventSubscription;
use Neunerlei\EventBus\Subscription\InvalidSubscriberException;
use Neunerlei\EventBus\Subscription\LazyEventSubscriberInterface;
use Neunerlei\EventBus\Subscription\LazyEventSubscription;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

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
			// Crell\Tukio listener provider
			OrderedProviderInterface::class => function (OrderedProviderInterface $provider, string $event, callable $listener, array $options) {
				// Create a pseudo item to translate the options
				$item = new EventListenerListItem("", [$this, "__construct"], $options);
				if (is_null($item->pivotId))
					return $provider->addListener($listener, $item->priority, $item->id, $event);
				else if ($item->beforePivot)
					return $provider->addListenerBefore($item->pivotId, $listener, $item->id, $event);
				else
					return $provider->addListenerAfter($item->pivotId, $listener, $item->id, $event);
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
			
			// Validate event
			if (!is_string($events))
				throw new InvalidArgumentException("The given event, or list of events is invalid! Only strings or arrays of strings are allowed!");
			
			// Check if we use the built-in provider, or an external provider that requires an adapter
			$provider = $this->getConcreteListenerProvider();
			if (!$provider instanceof EventBusListenerProvider) {
				// Find the correct adapter, if we use an external package
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
				$id = call_user_func($adapter, $provider, $events, $listener, $options);
			} else {
				// Use the built-in provider
				$id = $provider->addListener($events, $listener, $options);
			}
			
			// Call the real handler implementation
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
			$this->concreteDispatcher = new EventBusDispatcher($this->getConcreteListenerProvider());
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
		if (empty($this->concreteProvider)) $this->concreteProvider = new EventBusListenerProvider();
		return $this->concreteProvider;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setProviderAdapter(string $providerClassOrInterface, callable $adapter): EventBusInterface {
		$this->providerAdapters[$providerClassOrInterface] = $adapter;
		return $this;
	}
	
	/**
	 * @inheritDoc
	 */
	public function getContainer(): ?ContainerInterface {
		return $this->container;
	}
	
	/**
	 * @inheritDoc
	 */
	public function setContainer(ContainerInterface $container): EventBusInterface {
		$this->container = $container;
		return $this;
	}
}