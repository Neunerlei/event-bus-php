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

namespace Neunerlei\EventBus;


use Closure;
use Crell\Tukio\OrderedProviderInterface;
use Neunerlei\ContainerAutoWiringDeclaration\SingletonInterface;
use Neunerlei\EventBus\Dispatcher\EventBusDispatcher;
use Neunerlei\EventBus\Dispatcher\EventBusListenerProvider;
use Neunerlei\EventBus\Dispatcher\EventListenerListItem;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\EventSubscription;
use Neunerlei\EventBus\Subscription\InvalidSubscriberException;
use Neunerlei\EventBus\Subscription\LazyEventSubscriberInterface;
use Neunerlei\EventBus\Subscription\LazyEventSubscription;
use Neunerlei\EventBus\Util\ListenerProviderOnceProxy;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionFunction;
use TypeError;

class EventBus implements EventDispatcherInterface, ListenerProviderInterface, EventBusInterface, SingletonInterface
{

    /**
     * Holds the container instance or is null
     *
     * @var \Psr\Container\ContainerInterface
     */
    protected $container;

    /**
     * The concrete event dispatcher this instance wraps around
     *
     * @var EventDispatcherInterface
     */
    protected $concreteDispatcher;

    /**
     * The concrete listener provider implementation used by the event dispatcher
     *
     * @var ListenerProviderInterface
     */
    protected $concreteProvider;

    /**
     * The list of registered listener provider adapters
     *
     * @var array
     */
    protected $providerAdapters = [];

    /**
     * A list of registered provider adapters that can handle the "once" option
     *
     * @var array
     */
    protected $providerAdaptersWithOnce = [];

    /**
     * The list of registered provider adapters that support once proxies out of the box
     *
     * @var array
     */
    protected $providerOnceProxies = [];

    /**
     * The unique id of the listener that was added last
     *
     * @var string|integer
     */
    protected $lastListenerId;

    /**
     * Holds the class/interface name of the adapter we should use to bind to events
     *
     * @var string|null
     */
    protected $suggestedAdapter;

    /**
     * EventBus constructor.
     *
     * @param   \Psr\Container\ContainerInterface|null  $container
     */
    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container;

        // Register provider adapters
        $this->providerAdapters = [
            // Crell\Tukio listener provider
            OrderedProviderInterface::class => static function (
                OrderedProviderInterface $provider,
                string $event,
                EventListenerListItem $item,
                array $options
            ) {
                if ($item->pivotId === null) {
                    return $provider->addListener($item->listener, $item->priority, $item->id, $event);
                }

                if ($item->beforePivot) {
                    return $provider->addListenerBefore($item->pivotId, $item->listener, $item->id, $event);
                }

                return $provider->addListenerAfter($item->pivotId, $item->listener, $item->id, $event);
            },
        ];
    }

    /**
     * @inheritDoc
     */
    public function getLastListenerId()
    {
        return $this->lastListenerId;
    }

    /**
     * @inheritDoc
     */
    public function addListener($events, callable $listener, array $options = []): EventBusInterface
    {
        if (is_iterable($events)) {
            foreach ($events as $event) {
                $this->addListener($event, $listener, $options);
            }
        } else {
            // Validate event
            if (! is_string($events)) {
                throw new TypeError(
                    'The given event, or list of events is invalid! Only strings or arrays of strings are allowed!');
            }

            // Check if we use the built-in provider, or an external provider that requires an adapter
            $provider = $this->getConcreteListenerProvider();
            if ($provider instanceof EventBusListenerProvider) {
                // Use the built-in provider
                $this->lastListenerId = $provider->addListener($events, $listener, $options);
            } else {
                // Find the correct adapter, if we use an external package
                $adapter = null;
                if (! is_string($this->suggestedAdapter) || ! $provider instanceof $this->suggestedAdapter) {
                    $this->suggestedAdapter = null;

                    foreach ($this->providerAdapters as $class => $_adapter) {
                        if ($provider instanceof $class) {
                            $this->suggestedAdapter = $class;
                            break;
                        }
                    }

                    if (empty($this->suggestedAdapter)) {
                        throw new MissingAdapterException(
                            'Could not resolve a listener provider adapter class!');
                    }
                }

                $adapter = $this->providerAdapters[$this->suggestedAdapter];
                $item    = new EventListenerListItem('', $listener, $options);

                if ($item->once
                    && (! isset($this->providerAdaptersWithOnce[$this->suggestedAdapter])
                        || $this->providerAdaptersWithOnce[$this->suggestedAdapter] !== true)) {
                    $item = $this->makeOnceProxy($provider, $events, $item, $options);

                    // No item returned -> proxy is already registered in the listener, we are done
                    if ($item === null) {
                        return $this;
                    }
                }

                $this->lastListenerId = $adapter($provider, $events, $item, $options);
            }

            if (empty($options['id'])) {
                $options['id'] = $this->lastListenerId;
            }
        }

        // Done
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addSubscriber(EventSubscriberInterface $instance): EventBusInterface
    {
        $subscription = new EventSubscription($this, $instance);
        $instance->subscribeToEvents($subscription);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addLazySubscriber(string $subscriberClass, ?callable $factory = null): EventBusInterface
    {
        // Check if the class implements the required interface
        if (! in_array(LazyEventSubscriberInterface::class, class_implements($subscriberClass), true)) {
            throw new InvalidSubscriberException(
                'The given lazy subscriber: ' . $subscriberClass .
                ' does not implement the required interface: '
                . LazyEventSubscriberInterface::class);
        }

        // Prepare the factory
        if (empty($factory)) {
            // Check if we have a container
            if (empty($this->container)) {
                throw new MissingContainerException(
                    'Could not add a lazy subscriber, because there is neither a ' .
                    "factory, nor a container to instantiate the class: \"$subscriberClass\"!");
            }

            $factory = function () use ($subscriberClass) {
                return $this->container->get($subscriberClass);
            };
        }

        // Create the subscription
        call_user_func([$subscriberClass, 'subscribeToEvents'], new LazyEventSubscription($this, $factory));

        // Done
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event)
    {
        return $this->getConcreteDispatcher()->dispatch($event);
    }

    /**
     * @inheritDoc
     */
    public function getListenersForEvent(object $event): iterable
    {
        return $this->getConcreteListenerProvider()->getListenersForEvent($event);
    }

    /**
     * @inheritDoc
     */
    public function setConcreteDispatcher(EventDispatcherInterface $dispatcher): EventBusInterface
    {
        $this->concreteDispatcher = $dispatcher;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getConcreteDispatcher(): EventDispatcherInterface
    {
        if (empty($this->concreteDispatcher)) {
            $this->concreteDispatcher = new EventBusDispatcher($this->getConcreteListenerProvider());
        }

        return $this->concreteDispatcher;
    }

    /**
     * @inheritDoc
     */
    public function setConcreteListenerProvider(ListenerProviderInterface $provider): EventBusInterface
    {
        $this->concreteProvider = $provider;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getConcreteListenerProvider(): ListenerProviderInterface
    {
        if (empty($this->concreteProvider)) {
            $this->concreteProvider = new EventBusListenerProvider();
        }

        return $this->concreteProvider;
    }

    /**
     * @inheritDoc
     */
    public function setProviderAdapter(
        string $providerClassOrInterface,
        callable $adapter,
        bool $canHandleOnce = false
    ): EventBusInterface {
        $this->providerAdapters[$providerClassOrInterface]         = $adapter;
        $this->providerAdaptersWithOnce[$providerClassOrInterface] = $canHandleOnce;

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * @inheritDoc
     */
    public function setContainer(ContainerInterface $container): EventBusInterface
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Creates a proxy listener that is registered in listener providers that don't support
     * the "once" feature by themselves.
     *
     * It returns either an item clone with the proxy as listener or null if
     * the proxy was already registered inside the listener provider
     *
     * @param   \Psr\EventDispatcher\ListenerProviderInterface        $provider
     * @param   string                                                $eventName
     * @param   \Neunerlei\EventBus\Dispatcher\EventListenerListItem  $item
     * @param   array                                                 $options
     *
     * @return \Neunerlei\EventBus\Dispatcher\EventListenerListItem|null
     */
    protected function makeOnceProxy(
        ListenerProviderInterface $provider,
        string $eventName,
        EventListenerListItem $item,
        array $options
    ): ?EventListenerListItem {
        $optionsSerializer = static function ($v, callable $optionsSerializer) {
            $r = [];
            if (is_iterable($v)) {
                foreach ($v as $k => $_v) {
                    $r[$k] = $optionsSerializer($_v, $optionsSerializer);
                }
            } elseif (is_object($v)) {
                if ($v instanceof Closure) {
                    $ref = new ReflectionFunction($v);
                    $r[] = $ref->getFileName();
                    $r[] = $ref->getStartLine();
                    $r[] = $ref->getEndLine();
                } else {
                    $r[] = spl_object_hash($v);
                }
            } else {
                $r[] = serialize($v);
            }

            return md5(implode('.', $r));
        };

        $proxyId = 'once' . md5(implode('.', [
                spl_object_id($provider),
                $eventName,
                $optionsSerializer($options, $optionsSerializer),
            ]));

        if (! isset($this->providerOnceProxies[$proxyId])) {
            $isNew                               = true;
            $this->providerOnceProxies[$proxyId] = new ListenerProviderOnceProxy();
        }

        $this->providerOnceProxies[$proxyId]->addItem($item);

        if (isset($isNew)) {
            $itemClone           = clone $item;
            $itemClone->id       = $proxyId;
            $itemClone->listener = [$this->providerOnceProxies[$proxyId], 'call'];

            return $itemClone;
        }

        $this->lastListenerId = $proxyId;

        return null;
    }
}
