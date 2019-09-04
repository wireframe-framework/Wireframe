<?php

namespace Wireframe;

/**
 * Abstract base implementation for Controller objects
 *
 * @version 0.2.0
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
     * $method_aliases = [
     *     'method_name' => [
     *         'callable' => callable $callable,
     *         'params' => array $params = [],
     *     ],
     * ];
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
     * @var View
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

        if ($view) {
            // store a reference to View and make View aware of its Controller
            $this->view = $view;
            $this->view->setController($this);
        }

        // store a reference to Page
        $this->page = $page;

        // init Controller
        $this->init();

    }

    /**
     * Init method
     *
     * This method is called automatically when this class gets instantiated.
     */
    public function init() {}

    /**
     * Render method
     *
     * This method is called automatically right before a page is rendered.
     */
    public function render() {}

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

        // only allow access to method names that are not prefixed with an underscore and haven't
        // been specifically disallowed by adding them to the disallowed_methods array.
        if (is_string($name) && $name[0] !== '_' && !in_array($name, $this->disallowed_methods)) {

            if (method_exists($this, $name) && is_callable([$this, $name])) {
                // callable (public) local method
                $return = $this->$name();

            } else if (method_exists($this, '___' . $name) && is_callable([$this, '___' . $name])) {
                // callable (public) and hookable local method
                $return = $this->_callHookMethod($name);

            } else if (!empty($this->method_aliases[$name])) {
                // method alias
                $method_alias = $this->method_aliases[$name];
                $return = call_user_func_array(
                    $method_alias['callable'],
                    $method_alias['params']
                );

            } else {
                // fall back to parent class getter method
                $return = parent::__get($name);

            }
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
