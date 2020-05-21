# Event Bus
This package contains a [PSR-14](https://www.php-fig.org/psr/psr-14/) compliant event dispatcher facade, which aims to bring the listener provider and dispatcher objects closer together. 

While I see where PSR is coming from, for the most part in my daily life it feels weired to have the registration of listeners and dispatching of events in two different classes. This is were this facade comes in; it combines both the dispatcher and provider into a single class you can use in your code. 

## Version 2.0
The second version does no longer rely on the [Tukio library](https://github.com/Crell/Tukio), instead provides
it's own listener provider and dispatcher implementations.
The dependency on my [Options library](https://github.com/Neunerlei/options-php) has also been removed in order to improve performance
in event-heavy projects. 

## Installation
Install this package using composer:

```
composer require neunerlei/event-bus
```

## Basic usage
You can use the event bus like any other good'ol event bus implementation you have without psr 14.
```php
<?php
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;

$bus = new EventBus();

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    // Do stuff :)
});

$bus->dispatch(new DummyEventA());
```

## Using listener priorities
You can set a priority for a registered listener that defines the order in which they are executed. 
Priorities can be set to any integer value, where 0 is the default. The value on the "+" range defines a priority higher (earlier) than default, 
on the "-" range defines a lower (later) priority instead.

```php
<?php
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;

$bus = new EventBus();

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    echo "3";
}, ["priority" => -10]);

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    echo "2";
});

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    echo "1";
}, ["priority" => 10]);

$bus->dispatch(new DummyEventA());
```

## Using id based ordering
The library provides "id"-based ordering, meaning you can provide Ids to your listeners and order other listeners before/after said id.
The Event Bus also provides a facade for that.

```php
<?php
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;

$bus = new EventBus();

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    echo "2";
}, ["id" => "myId"]);

$bus->addListener(DummyEventA::class, function(DummyEventA $e){
    echo "1";
}, ["before" => "myId"]);

$bus->dispatch(new DummyEventA());
```

## Using event subscribers
The concept of event subscribers is a concept that was (correct me if I'm wrong) mostly pushed by [Symfony](https://symfony.com/doc/current/event_dispatcher.html#creating-an-event-subscriber). It provides you with a unified solution for having multiple listeners in a single service. 

The bus provides you with two options on how to register event subscribers.

#### Option 1: Existing instance
This option is the way to go if you already have the instance of a subscriber.
The subscriber class has to implement the ```EventSubscriberInterface``` interface to be valid.

```php
<?php
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\Subscription\EventSubscriberInterface;
use Neunerlei\EventBus\Subscription\EventSubscriptionInterface;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;

class MyEventSubscriber implements EventSubscriberInterface {
    /**
     * @inheritDoc
     */
    public function subscribeToEvents(EventSubscriptionInterface $subscription){
        // You only have to define the method you want to trigger.
        $subscription->subscribe(DummyEventA::class, "onEventHappens");
    }

    public function onEventHappens(DummyEventA $event){
        // Do Stuff
    }   

}

$bus = new EventBus();

$bus->addSubscriber(new MyEventSubscriber());

$bus->dispatch(new DummyEventA());
```

#### Option 2: Lazy instantiation
The second option is used to register a service lazily. This means that you don't have to provide the instance of your subscriber,
but pass the name of the class to the event bus. In that case the instance will only be created if
one of the subscribed events was dispatched.

To create the instance lazily you can provide a factory function (the example below), that
should return the instance of the subscriber. The other method of creating a service instance is to use a PSR-11 Container object that should be passed into the constructor of the event bus. If the event bus has a container implementation the factory definition is optional.

```php
<?php
use Neunerlei\EventBus\EventBus;
use Neunerlei\EventBus\Subscription\EventSubscriptionInterface;
use Neunerlei\EventBus\Subscription\LazyEventSubscriberInterface;
use Neunerlei\EventBus\Tests\Assets\DummyEventA;

class MyEventSubscriber implements LazyEventSubscriberInterface {
    /**
     * @inheritDoc
     */
    public static function subscribeToEvents(EventSubscriptionInterface $subscription){
        // You only have to define the method you want to trigger.
        $subscription->subscribe(DummyEventA::class, "onEventHappens");
    }

    public function onEventHappens(DummyEventA $event){
        // Do Stuff
    }   

}

$bus = new EventBus();

// The second argument (factory) is used to instantiate the event subscriber object when it is required.
$bus->addLazySubscriber(MyEventSubscriber::class, function(){
    new MyEventSubscriber();
});

$bus->dispatch(new DummyEventA());

// Example using a container
$container = new FancyContainer(); // Use the instance of your PSR-11 container here
$bus = new EventBus($container);
// The factory is now no longer required and the instance will be resolved using the container.
$bus->addLazySubscriber(MyEventSubscriber::class);
$bus->dispatch(new DummyEventA());
```

## Altering the concrete dispatcher, listener provider and container
As stated above we use our own Dispatcher and ListenerProvider implementations internally to provide the main logic of the bus.
However the event bus class is designed to be agnostic to the PSR-14 implementation you want to use. 

You may override the internal container, the listener provider and the dispatcher object by using the API:
```php
<?php
use Neunerlei\EventBus\EventBus;
$bus = new EventBus();

// Retrieve the current dispatcher implementation
$dispatcher = $bus->getConcreteDispatcher();

// Replace the dispatcher with another implementation
$bus->setConcreteDispatcher(new \Crell\Tukio\Dispatcher(new \Crell\Tukio\OrderedListenerProvider()));

// Retrieve the current implementation for the listener provider
$listenerProvider = $bus->getConcreteListenerProvider();

// Replace the listener provider with another implementation
$bus->setConcreteListenerProvider(new \Crell\Tukio\OrderedListenerProvider());

// Retrieve the current container instance
// This will be null, be cause there was no bus given to the constructor.
$container = $bus->getContainer();

// Replace the container with another implementation, or set one if the container
// did not exist at the time when the event bus was instantiated.
$bus->setContainer(new FancyContainer());
```

#### Listener provider adapter
One problem I encountered while writing the facade was, that PSR-14 does not define a unified contract for adding listeners (which is in fact one of it's strengths). 
For that reason we have to provide an adapter that translates the registration to the correct listener provider implementation. 
An adapter can be registered on a class or interface base and can be any callable. The example below shows a dummy implementation of the **already builtin adapter** to the [Tukio library](https://github.com/Crell/Tukio) by [Larry Garfield](https://github.com/Crell).

To use the library install it using composer: 
```
composer require crell/tukio ^1.1
```

After that you can register the provider adapter like so:
**IMPORTANT: This is an example on how to implement your own adapter, you don't have to do this for the crell/tukio implementation!**
```php
<?php
use Crell\Tukio\Dispatcher;
use Crell\Tukio\OrderedListenerProvider;
use Crell\Tukio\OrderedProviderInterface;
use Neunerlei\EventBus\EventBus;
use Neunerlei\Options\Options;

$bus = new EventBus();

$orderedProvider = new OrderedListenerProvider();
$bus->setConcreteDispatcher(new Dispatcher($orderedProvider));
$bus->setConcreteListenerProvider($orderedProvider);
$bus->setProviderAdapter(OrderedProviderInterface::class, 
    function (OrderedProviderInterface $provider, string $event, 
        callable $listener, array $options) {
            // Validate options
            $options = Options::make($options, [ /* Removed to keep the example somewhat short */ ]);
            
            // Register the listener
            if (!empty($options["before"]))
                return $provider->addListenerBefore($options["before"], $listener, $options["id"], $event);
            else if (!empty($options["after"]))
                return $provider->addListenerAfter($options["after"], $listener, $options["id"], $event);
            return $provider->addListener($listener, $options["priority"], $options["id"], $event);
        });
```

The adapter receives four parameters.

- $provider is the instance of the concrete provider implementation
- $event is the class of a single event the given $listener should be bound to
- $listener the listener callable that should be registered for the event
- $options the raw $options array that was passed, you can implement the option validation on your own.

## Usage as PSR-14 Dispatcher and/or Listener Provider
The event bus class implements the EventDispatcherInterface as well as the ListenerProviderInterface. That way you can use the bus instance as an aggregate for any other PSR-14 compatible project without issues.
  
## StoppableEvents
PSR-14 defines how stoppable events should be handled, so we provide an abstract for that use case. If you want to create an event which is stoppable simply extend the ```Neunerlei\EventBus\AbstractStoppableEvent``` class.
Or if you extend an existing event you may use the ```Neunerlei\EventBus\StoppableEventTrait``` as well.

## Special Thanks
Special thanks goes to the folks at [LABOR.digital](https://labor.digital/) (which is the word german for laboratory and not the english "work" :D) for making it possible to publish my code online.

## Postcardware
You're free to use this package, but if it makes it to your production environment I highly appreciate you sending me a postcard from your hometown, mentioning which of our package(s) you are using.

You can find my address [here](https://www.neunerlei.eu/). 

Thank you :D 
