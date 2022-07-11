<?php

namespace Wireframe;

/**
 * Wireframe Component View
 *
 * This class is a wrapper for the ProcessWire TemplateFile class with some additional features and
 * the Wireframe namespace.
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class ComponentView extends \ProcessWire\TemplateFile {

    /**
     * Renderer module
     *
     * @var null|\ProcessWire\Module
     */
    private $renderer = null;

    /**
     * Wireframe View Data object
     *
     * @var ViewData
     */
    protected $_wireframe_view_data;

    /**
     * Constructor method
     *
     * @param Component $component
     */
    public function __construct(Component $component) {
        $this->_wireframe_view_data = new ViewData();
        $this->setComponent($component);
        parent::__construct();
    }

    /**
     * Render method
     *
     * @return string Rendered markup for the View.
     */
    public function ___render() {

        // attempt to render markup using a renderer
        if ($this->renderer) {
            // note: $globals is inherited from parent class TemplateFile, where it's marked as DEPRECATED; this may
            // need some attention in the near(ish) future
            $view_context = array_merge($this->getArray(), self::$globals);
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->renderer->render('component', $this->filename, $view_context);
        }

        return parent::___render();
    }

    /**
     * PHP's magic __get() method
     *
     * This method provides access to properties of the linked Component instance.
     *
     * @param string $key Name of the Component method
     * @return mixed Value of the key or null
     */
    public function __get($key) {
        $value = $this->get($key);
        if (!$value) {
            $value = $this->getFromComponent($key);
        }
        return $value;
    }

    /**
     * Getter method for the Component class
     *
     * @return Component Component instance
     */
    public function getComponent(): Component {
        return $this->getViewData('component');
    }

    /**
     * Setter method for the Component class
     *
     * @param Component $component
     * @return ComponentView Self-reference
     *
     * @throws Exception if $component argument is of unrecognized type.
     */
    public function setComponent(Component $component): ComponentView {
        $this->setViewData('component', $component);
        return $this;
    }

    /**
     * Setter method for the Renderer
     *
     * @param \ProcessWire\Module $renderer
     * @return ComponentView Self-reference
     */
    public function setRenderer(\ProcessWire\Module $renderer): ComponentView {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Getter method for real or dynamically generated properties of the Component class
     *
     * @param string $key Property name
     * @return mixed Property value or null
     */
    protected function getFromComponent(string $key) {
        $component = $this->getViewData('component');
        if ($component) {
            return $component->$key;
        }
        return null;
    }

    /**
     * Setter method for view data values
     *
     * @param string $key View data key
     * @param mixed $value View data value
     * @return ComponentView Self-reference
     */
    protected function setViewData(string $key, $value): ComponentView {
        $this->_wireframe_view_data->$key = $value;
        return $this;
    }

    /**
     * Getter method for view data values
     *
     * @param string $key View data key
     * @return mixed View data value
     */
    protected function getViewData(string $key) {
        return $this->_wireframe_view_data->$key ?? null;
    }

}
