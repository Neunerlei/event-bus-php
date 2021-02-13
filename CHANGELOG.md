# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

## [3.0.0](https://github.com/Neunerlei/event-bus-php/compare/v2.0.2...v3.0.0) (2021-02-13)


### ⚠ BREAKING CHANGES

* **EventSubscriberInterface:** breaks the contract of EventSubscriberInterface so
you have to adjust your implementation
* **LazyEventSubscriberInterface:** breaks the contract of LazyEventSubscriberInterface so
you have to adjust your implementation
* addListener now throws a TypeError instead of an
InvalidArgumentException if $events is neither a string nor an array of
strings
* the signature of setProviderAdapter() changed and might
break implementations. Also the getLastListenerId() method was introduced, breaking the existing contract as well.

### Features

* **EventSubscriberInterface:** add return type to subscribeToEvents ([d9e6400](https://github.com/Neunerlei/event-bus-php/commit/d9e6400cc80623b594b11a8f77890ac04356238d))
* **LazyEventSubscriberInterface:** add return type to subscribeToEvents ([978c1ce](https://github.com/Neunerlei/event-bus-php/commit/978c1cea27d374877776ed07e9cfc2446a77b456))
* introduce "once" option for addListener ([a3fed8b](https://github.com/Neunerlei/event-bus-php/commit/a3fed8bc689239bbb5e72edeea08677fc9449f11))


### Bug Fixes

* **EventBus:** return correct proxyId in makeOnceProxy() ([239e71e](https://github.com/Neunerlei/event-bus-php/commit/239e71ec40bd2c5f0014f752b9d3ffda41b57781))
* throw type error on invalid event type ([0a94f0a](https://github.com/Neunerlei/event-bus-php/commit/0a94f0a99fce23f4b74641874add29355b376bd1))

### [2.0.2](https://github.com/Neunerlei/event-bus-php/compare/v2.0.1...v2.0.2) (2021-02-13)

### [2.0.1](https://github.com/Neunerlei/event-bus-php/compare/v2.0.0...v2.0.1) (2021-02-13)


### Bug Fixes

* **EventBusListenerProvider:** allow dependency injection ([05d9d75](https://github.com/Neunerlei/event-bus-php/commit/05d9d75a25d1dd341cf61a5c812f33b885189597))

## [2.0.0](https://github.com/Neunerlei/event-bus-php/compare/v1.2.0...v2.0.0) (2020-05-21)


### ⚠ BREAKING CHANGES

* removes crell/tukio as dispatcher and listener provider

### Features

* update to version 2.0 ([f8c8ef5](https://github.com/Neunerlei/event-bus-php/commit/f8c8ef51db269c2f9546069574a24356930175e8))

## [1.2.0](https://github.com/Neunerlei/event-bus-php/compare/v1.1.0...v1.2.0) (2020-03-22)


### Features

* add getContainer() and setContainer() methods to the event bus to update the container after instantiation ([017edcc](https://github.com/Neunerlei/event-bus-php/commit/017edcc7663bc472b91686afd3c5006079f57430))


### Bug Fixes

* fix broken pipeline definition ([53cf89f](https://github.com/Neunerlei/event-bus-php/commit/53cf89f1891fee8742f120d91753582ae785fbc2))

## 1.1.0 (2020-03-02)


### Features

* initial commit ([07921d3](https://github.com/Neunerlei/event-bus-php/commit/07921d34c62af1089fd0cc77219caad38813f9fc))
