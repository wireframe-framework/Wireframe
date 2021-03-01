<?php

namespace Wireframe;

/**
 * Abstract base implementation for Controller objects
 *
 * @property View $view
 * @property \ProcessWire\Page $page
 *
 * @version 0.8.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Controller extends \ProcessWire\Wire {

    use MethodPropsTrait;

    /**
     * Instance of View
     *
     * @var View
     */
    private $view;

    /**
     * Instance of Page
     *
     * @var \ProcessWire\Page
     */
    private $page;

    /**
     * Constructor
     *
     * @param \ProcessWire\Page|null $page Optional Page object
     * @param View|null $view Optional View object
     */
    public function __construct(?\ProcessWire\Page $page = null, ?View $view = null) {

        if ($page !== null) {
            // store a reference to Page
            $this->setPage($page);
        }

        if ($view !== null) {
            // store a reference to View
            $this->setView($view);
        }

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
     *
     * If you return a string from the render method of a Controller, Page rendering stops and that
     * value is used as the rendered value, short-circuiting the process.
     *
     * @return void|string
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
        if ($name === 'page' || $name === 'view') {
            return $this->$name ?: $this->wire($name);
        }
        return $this->getMethodProp($name, 'controller');
    }

    /**
     * PHP magic setter method
     *
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value) {
        if ($name === 'page') {
            $this->setPage($value, true);
        } else if ($name === 'view') {
            $this->setView($value);
        }
    }

    /**
     * Setter method for page
     *
     * Note: if you set page property directly in Controller instance ($this->page = ...) the page property of the View
     * instance is updated automatically. If you *don't* want to update the View, set the page property via this method
     * instead.
     *
     * @param \ProcessWire\Page $page Page instance
     * @param bool $propagate_to_view Update View instance page property as well?
     * @return Controller Self-reference
     */
    public function setPage(\ProcessWire\Page $page, bool $propagate_to_view = false): Controller {
        $this->page = $page;
        if ($propagate_to_view && $this->view) {
            $this->view->setPage($page);
        }
        return $this;
    }

    /**
     * Setter method for View
     *
     * @param View $view View instance
     * @return Controller Self-reference
     */
    public function setView(View $view): Controller {
        $this->view = $view;
        return $this;
    }

}
