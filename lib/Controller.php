<?php

namespace Wireframe;

/**
 * Abstract base implementation for Controller objects
 *
 * @version 0.7.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Controller extends \ProcessWire\Wire {

    use MethodPropsTrait;

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
     * Note that a Controller can exist without a View. It is the responsibility of the developer
     * to make sure that the Controller doesn't assume that a View is always available.
     *
     * @param \ProcessWire\Page $page Page object
     * @param View|null $view View component (optional)
     */
    public function __construct(\ProcessWire\Page $page, ?View $view = null) {

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
     * Note: __get() is only called when trying to access a non-existent or non-local and non-public property.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return $this->getMethodProp($name, 'controller');
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
