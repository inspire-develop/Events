# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
### Fixed
- `on*` properties typed strictly as `array` are bound to the EventManager again - the event is wrapped
  into an array (`Event::wrapToArray()`), so listeners registered on Nette 3.2 lifecycle hooks
  (`Application::$onStartup`, `$onShutdown`, `Security\User::$onLoggedIn`, ...) are dispatched instead
  of being silently dropped at compile time
- PHP 8.4+ deprecations: explicit nullable parameter types in `SymfonyDispatcher::dispatch()` and
  `Panel::eventDispatch()`/`eventDispatched()`, removed `ReflectionProperty::setAccessible()` calls

## [6.0.0] - 2024-04-12
### Added
- first-class callables support when defining subscribed events
### Changed
- PHP 8.3 compatible release
