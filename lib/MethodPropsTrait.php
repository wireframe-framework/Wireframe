<?php

namespace Wireframe;

/**
 * Trait for adding public method property access support to Wireframe objects
 *
 * @version 0.3.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
trait MethodPropsTrait {

    /**
     * Alternatives (aliases) for real methods
     *
     * This parameter is an array containing callables and their optional parameters. The format of
     * the $method_aliases array is following:
     *
     * ```
     * protected $method_aliases = [
     *     'method_name' => [
     *         'callable' => callable $callable,
     *         'params' => array $params = [],
     *     ],
     * ];
     * ```
     *
     * @var array
     */
    protected $method_aliases = [];

    /**
     * Disallowed methods
     *
     * This array contains methods that cannot be directly accessed from the Component View. Note
     * that all methods prefixed with an underscore (_) are automatically disallowed.
     *
     * @var array
     */
    protected $disallowed_methods = [];

    /**
     * Methods that should never be cached
     *
     * The default behaviour is to cache method return values on first query. If you need a method
     * to always return a fresh value, i.e. the value should never be cached, define this array in
     * a Component class and add the method name to it.
     *
     * @var array
     */
    protected $uncacheable_methods = [];

    /**
     * Cacheable methods with special rules
     *
     * The default behaviour is to cache method return values on first query in a non-persistent
     * runtime cache. This array can be used to define methods that have special caching rules,
     * such as those that can be persistently cached using WireCache.
     *
     * ```
     * protected $cacheable_methods = [
     *     'method_name' => 3600, // store in persistent cache for an hour
     * ];
     * ```
     *
     * @var array
     */
    protected $cacheable_methods = [];

    /**
     * Runtime cache for method return values
     *
     * @var array
     */
    private $method_return_value_cache = [];

    /**
     * Shorthand for getting or setting alias methods
     *
     * @param string $alias Name of the method alias
     * @param null|string|callable $real_method Name of the method that being aliased or a callable. Optional, only needed if using this method as a setter.
     * @param array $params Optional array of parameters to pass to the alias method. Optional, discarded unless using this method as a setter.
     * @return null|array Array if method alias was found or set, otherwise null
     */
    final public function alias(string $alias, callable $callable = null, array $params = []): ?array {
        return $callable ? $this->setAlias($alias, $callable, $params) : $this->getAlias($alias);
    }

    /**
     * Get the value of a method alias
     *
     * @param string $alias Name of the method alias
     * @return null|array Array if method alias is found, otherwise null
     */
    final public function getAlias(string $alias): ?array {
        return $this->method_aliases[$alias] ?? null;
    }

    /**
     * Set method alias
     *
     * @param string $alias Name of the alias.
     * @param null|callable $callable Callable to set as alias method, or null to unset alias method.
     * @param array $params Optional array of parameters to pass to the alias method.
     * @return null|array Array if method alias was set, null if method alias was unset
     */
    final public function setAlias(string $alias, ?callable $callable, array $params = []): ?array {

        $return = null;

        if ($callable === null) {
            // null method provided, unset alias
            unset($this->method_aliases[$alias]);

        } else {
            // callable provided, store as method alias
            $this->method_aliases[$alias] = [
                'callable' => $callable,
                'params' => $params,
            ];
            $return = $this->method_aliases[$alias];

        }

        return $return;
    }

    /**
     * Get method prop
     *
     * Provides access to class methods as properties, and also abstracts away the use of method aliases.
     *
     * @param string $name
     * @param string $context
     * @return mixed
     */
    final public function getMethodProp(string $name, string $context) {

        // only allow access to method names that are not prefixed with an underscore and haven't
        // been specifically disallowed by adding them to the disallowed_methods array.
        if (!\is_string($name) || $name[0] === '_' || \in_array($name, $this->disallowed_methods)) {
            return null;
        }

        $value = null;
        $persistent_cache_name = null;
        $runtime_cache_enabled = !\in_array($name, $this->uncacheable_methods);

        if ($runtime_cache_enabled && isset($this->method_return_value_cache[$name])) {
            // return value from temporary runtime cache
            return $this->method_return_value_cache[$name];
        }

        if (!empty($this->cacheable_methods[$name])) {
            // attempt to return value from persistent cache (WireCache)
            $persistent_cache_name = 'Wireframe/MethodProp'
                . '/' . $context
                . '/' . static::class
                . '/' . $name
                . '/' . $this->wire('page');
            $value = $this->wire('cache')->get($persistent_cache_name, $this->cacheable_methods[$name]);
            if ($value !== null) {
                $this->cacheMethodProp($name, $value, $runtime_cache_enabled, null);
                return $value;
            }
        }

        if (\method_exists($this, $name) && \is_callable([$this, $name])) {
            // callable (public) local method
            $value = $this->$name();
        } else if (\method_exists($this, '___' . $name) && \is_callable([$this, '___' . $name])) {
            // callable (public) and hookable local method
            $value = $this->_callHookMethod($name);
        } else if (!empty($this->method_aliases[$name])) {
            // method alias
            $method_alias = $this->method_aliases[$name];
            $value = \call_user_func_array(
                $method_alias['callable'],
                $method_alias['params']
            );
        } else {
            // fall back to parent class getter method
            $value = parent::__get($name);
            $runtime_cache_enabled = false;
        }

        $this->cacheMethodProp($name, $value, $runtime_cache_enabled, $persistent_cache_name);
        return $value;
    }

    /**
     * Cache and return method prop
     *
     * @param string $name
     * @param mixed $value
     * @param bool $runtime_cache_enabled
     * @param string|null $persistent_cache_name
     */
    private function cacheMethodProp(string $name, $value, bool $runtime_cache_enabled, ?string $persistent_cache_name) {

        // store return value in temporary runtime cache?
        if ($runtime_cache_enabled) {
            $this->method_return_value_cache[$name] = $value;
        }

        // store return value in persistent cache (WireCache)?
        if ($persistent_cache_name !== null && $value !== null) {
            $this->wire('cache')->save($persistent_cache_name, $value, $this->cacheable_methods[$name]);
        }
    }

    /**
     * Get all method props
     *
     * @internal
     *
     * @param string $context
     * @param int $return_mode
     * @return array
     */
    final public function getMethodProps(string $context, int $return_mode = 1): array {
        $reflection_class = new \ReflectionClass($this);
        $props = [];
        foreach ($reflection_class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ((strpos($method->name, '_') === 0 && strpos($method->name, '___') !== 0) || in_array($method->name, ['init', 'render'])) {
                continue;
            }
            $params = $method->getParameters();
            $params_require_values = false;
            if (!empty($params)) {
                foreach ($params as $param) {
                    if (!$param->isDefaultValueAvailable()) {
                        $params_require_values = true;
                        break;
                    }
                }
            }
            if ($params_require_values) {
                // skip method if it has params that require values (won't work as plain prop)
                continue;
            }
            if ($method->class === $reflection_class->getName()) {
                if ($return_mode === 1) {
                    // key is prop name, value is prop value
                    $props[$method->name] = $this->getMethodProp($method->name, $context);
                } else if ($return_mode === 2) {
                    // key is prop name, value is prop signature and debug data
                    $return = $method->getReturnType();
                    if ($return !== null) {
                        $return_name = [];
                        if ($return instanceof \ReflectionUnionType) {
                            $return_types = $return->getTypes();
                            foreach ($return_types as $return_type) {
                                $return_name[] = $return_type->getName();
                            }
                        } else {
                            $return_name[] = $return->getName();
                        }
                        $return = ($return->allowsNull() ? '?' : '') . implode('|', $return_name);
                    }
                    $comment = $method->getDocComment();
                    if ($comment !== false) {
                        $comment = trim(preg_replace('/\s+\*\s?/m', PHP_EOL, substr($comment, 3, -2)));
                    }
                    $cacheable = \in_array($method->name, $this->uncacheable_methods) ? 'none' : 'run-time';
                    if (!empty($this->cacheable_methods[$method->name])) {
                        $cacheable = $this->cacheable_methods[$method->name];
                    }
                    $props[$method->name] = [
                        'params' => implode(', ', $method->getParameters()),
                        'return' => $return,
                        'caching' => $cacheable,
                        'comment' => $comment,
                        'value' => $this->getMethodProp($method->name, $context),
                    ];
                }
            }
        }
        return $props;
    }

}
