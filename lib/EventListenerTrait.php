<?php

namespace Wireframe;

use ProcessWire\Wire;

/**
 * Trait for adding event listener support to Wireframe objects
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
trait EventListenerTrait {

    /**
     * Event listeners
     *
     * @var array
     */
    private $event_listeners = [];

    /**
     * Events queue
     *
     * @var array
     */
    private $event_queue = [];

    /**
     * Emit an event for parents to listen to
     *
     * @param string $event Event name.
     * @param array $args Optional arguments.
     * @return Wire Self-reference.
     */
    final protected function emit(string $event, array $args = []): Wire {
        $this->event_queue[] = [
            'event' => $event,
            'args' => $args,
        ];
        $this->processEventQueue();
        return $this;
    }

    /**
     * Trigger an action on current object
     *
     * @param string $event Event name.
     * @param callable $callback Callback.
     * @param array $options Additional options.
     * @return Wire Self-reference.
     */
    final public function on(string $event, callable $callback, array $options = []): Wire {
        if (empty($this->event_listeners[$event])) {
            $this->event_listeners[$event] = [];
        }
        $run_once = !empty($options['once']);
        $this->event_listeners[$event][] = [
            'hook_id' => method_exists($this, $event) ? null : $this->addHook($event, function(\ProcessWire\HookEvent $hook_event) use($callback, $run_once) {
                call_user_func($callback, $this->wire(new \Processwire\HookEvent([
                    'object' => $this,
                    'method' => $hook_event->method,
                    'arguments' => $hook_event->arguments,
                ])));
                if ($run_once) {
                    // Note: removing local hook currently needs to be done "by hand", which is why
                    // we've duplicated some logic from WireHooks here. For more details check out
                    // https://github.com/processwire/processwire-issues/issues/1067.
                    list(, $priority, $method) = explode(':', $hook_event->id);
                    $local_hooks = $hook_event->object->getLocalHooks();
                    unset($local_hooks[$method][$priority]);
                    $hook_event->object->setLocalHooks($local_hooks);
                }
            }),
            'callback' => $callback,
            'once' => $run_once,
        ];
        return $this;
    }

    /**
     * Trigger an action on current object (but only once)
     *
     * This is an alias for EventListenerTrait::on() with predefined options.
     *
     * @param string $event Event name.
     * @param callable $callback Callback.
     * @return Wire Self-reference.
     */
    final public function once(string $event, callable $callback): Wire {
        return $this->on($event, $callback, [
            'once' => true,
        ]);
    }

    /**
     * Remove an event listener
     *
     * @param string $event Event name.
     * @return Wire Self-reference.
     */
    final public function off(string $event): Wire {
        if (!empty($this->event_listeners[$event])) {
            foreach ($this->event_listeners[$event] as $event_listener) {
                if (!empty($event_listener['hook_id'])) {
                    $this->removeHook($event_listener['hook_id']);
                }
            }
            unset($this->event_listeners[$event]);
        }
        return $this;
    }

    /**
     * Return attached event listeners
     *
     * @return array
     */
    final public function getEventListeners(): array {
        return $this->event_listeners;
    }

    /**
     * Return the events queue
     *
     * @return array
     */
    final public function getEventQueue(): array {
        return $this->event_queue;
    }

    /**
     * Process the events queue
     */
    final protected function processEventQueue() {
        foreach ($this->event_queue as $event_id => $event) {
            if (empty($this->event_listeners[$event['event']])) continue;
            $this->runHooks($event['event'], $event['args']);
            foreach ($this->event_listeners[$event['event']] as $event_listener_id => $event_listener) {
                if (empty($event_listener['hook_id'])) {
                    call_user_func($event_listener['callback'], $this->wire(new \Processwire\HookEvent([
                        'object' => $this,
                        'method' => $event['event'],
                        'arguments' => $event['args'],
                    ])));
                }
                if (!empty($event_listener['once'])) {
                    if (!empty($event_listener['hook_id'])) {
                        $this->removeHook($event_listener['hook_id']);
                    }
                    unset($this->event_listeners[$event['event']][$event_listener_id]);
                }
            }
            unset($this->event_queue[$event_id]);
        }
    }

}
