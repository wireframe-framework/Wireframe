<?php

namespace Wireframe;

/**
 * Abstract base implementation for Controller objects
 *
 * @version 0.5.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Controller extends \ProcessWire\Wire {

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
     * This array contains methods that cannot be directly accessed from the View. Note that all
     * methods prefixed with an underscore (_) are automatically disallowed.
     *
     * @var array
     */
    protected $disallowed_methods = [];

    /**
     * Methods that should never be cached
     *
     * The default behaviour is to cache method return values on first query. If you need a method
     * to always return a fresh value, i.e. the value should never be cached, define this array in
     * a Controller class and add the method name to it.
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
     * Instance of ProcessWire
     *
     * Note: this variable is underscore-prefixed in order to stay compatible with the Wire
     * class from ProcessWire (which we are extending here).
     *
     * @var \ProcessWire\ProcessWire
     */
    protected $_wire;

    /**
     * Instance of View
     *
     * @var View|null
     */
    protected $view;

    /**
     * Instance of Page
     *
     * @var \ProcessWire\Page
     */
    protected $page;

    /**
     * Constructor
     *
     * Note that a Controller can exist without a View. In such a case it is the
     * responsibility of the developer to make sure that the Controller doesn't
     * assume that a View is always available.
     *
     * @param \ProcessWire\ProcessWire $wire Instance of ProcessWire
     * @param \ProcessWire\Page $page Page object
     * @param View|null $view View component (optional)
     */
    public function __construct(\ProcessWire\Processwire $wire, \ProcessWire\Page $page, ?View $view = null) {

        // store a reference to ProcessWire
        $this->_wire = $wire;

        // store a reference to View
        $this->view = $view;

        // store a reference to Page
        $this->page = $page;

        // init Controller
        $this->init();

    }

    /**
     * Init method
     *
     * This method is called automatically when this class gets instantiated. As such this is a
     * good place to perform checks that need to run as early as possible: custom redirects, or
     * perhaps make sure that the user is logged in.
     *
     * Note that this method may get called multiple times even if the page isn't rendered. If
     * you perform resource-intensive tasks here, it is highly recommended that you cache their
     * results in local (object) properties.
     */
    public function init() {
        if (method_exists($this, '___init')) {
            return $this->__call('init', []);
        }
    }

    /**
     * Render method
     *
     * This method is called automatically right before a page is rendered. This is where you can,
     * for an example, pass data (properties/variables) to the View, so that you can later access
     * said data as locally scoped variables in your view files and layouts.
     */
    public function render() {
        if (method_exists($this, '___render')) {
            return $this->__call('render', []);
        }
    }

    /**
     * Render JSON method
     *
     * By default this method returns nothing (null). If you want a Controller to return values for
     * JSON API requests you need to implement this method or the hookable version `___renderJSON()`
     * in the Controller class. Basic example:
     *
     * ```
     * public function renderJSON(): ?string {
     *     return json_encode($this->wire('page')->getArray());
     * }
     * ```
     *
     * @return string|null JSON output.
     */
    public function renderJSON(): ?string {
        if (method_exists($this, '___renderJSON')) {
            return $this->__call('renderJSON', []);
        }
        return null;
    }

    /**
     * PHP magic getter method
     * 
     * Provides access to class methods as properties, and also abstracts away
     * the use of method aliases. Note: __get() is only called when trying to
     * access a non-existent or non-local and non-public property.
     *
     * @param string $name
     * @return mixed
     */
    function __get($name) {

        $return = null;
        $cache_name = null;
        $cacheable = !\in_array($name, $this->uncacheable_methods);

        // only allow access to method names that are not prefixed with an underscore and haven't
        // been specifically disallowed by adding them to the disallowed_methods array.
        if (\is_string($name) && $name[0] !== '_' && !\in_array($name, $this->disallowed_methods)) {

            if ($cacheable && isset($this->method_return_value_cache[$name])) {
                // return value from temporary runtime cache
                return $this->method_return_value_cache[$name];
            }

            if (!empty($this->cacheable_methods[$name])) {
                // attempt to return value from persistent cache (WireCache)
                $cache_name = 'wireframe'
                            . '/controller=' . static::class
                            . '/method=' . $name
                            . '/page=' . $this->wire('page');
                $return = $this->wire('cache')->get($cache_name, $this->cacheable_methods[$name]);
                if ($return !== null) {
                    $this->method_return_value_cache[$name] = $return;
                    return $return;
                }
            }

            if (\method_exists($this, $name) && \is_callable([$this, $name])) {
                // callable (public) local method
                $return = $this->$name();

            } else if (\method_exists($this, '___' . $name) && \is_callable([$this, '___' . $name])) {
                // callable (public) and hookable local method
                $return = $this->_callHookMethod($name);

            } else if (!empty($this->method_aliases[$name])) {
                // method alias
                $method_alias = $this->method_aliases[$name];
                $return = \call_user_func_array(
                    $method_alias['callable'],
                    $method_alias['params']
                );

            } else {
                // fall back to parent class getter method
                $return = parent::__get($name);
                $cacheable = false;

            }
        }

        if ($cacheable) {
            // store return value in temporary runtime cache
            $this->method_return_value_cache[$name] = $return;
        }

        if (!empty($cache_name) && $return !== null) {
            // store return value in persistent cache (WireCache)
            $this->wire('cache')->save($cache_name, $return, $this->cacheable_methods[$name]);
        }

        return $return;
    }

    /**
     * Shorthand for getting or setting alias methods
     *
     * @param string $alias Name of the method alias
     * @param null|string|callable $real_method Name of the method that being aliased or a callable. Optional, only needed if using this method as a setter.
     * @param array $params Optional array of parameters to pass to the alias method. Optional, discarded unless using this method as a setter.
     * @return null|array Array if method alias was found or set, otherwise null
     */
    public function alias(string $alias, callable $callable = null, array $params = []): ?array {
        return $callable ? $this->setAlias($alias, $callable, $params) : $this->getAlias($alias);
    }

    /**
     * Get the value of a method alias
     *
     * @param string $alias Name of the method alias
     * @return null|array Array if method alias is found, otherwise null
     */
    public function getAlias(string $alias): ?array {
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
    public function setAlias(string $alias, ?callable $callable, array $params = []): ?array {

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
     * Setter method for View
     *
     * @param View|null View instance or null
     * @return Controller Self-reference
     */
    public function setView(?View $view): Controller {
        $this->view = $view;
        return $this;
    }
}
