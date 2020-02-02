<?php

namespace Wireframe;

/**
 * Abstract base implementation for Component objects
 *
 * By extending ProcessWire's WireData we get certain benefits, including the ability to easily set
 * up the array passed as context when rendering the component. This does, though, mean that if you
 * define class properties and set values to those (in order to make use of strong typing etc.), we
 * can't automatically pass such values to the component view for rendering.
 *
 * In aforementioned use case you should override the getData() method and return the data you want
 * the render process to have access to.
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Component extends \ProcessWire\WireData {

    use EventListenerTrait;

    /**
     * Default view file for the Component
     *
     * @var string
     */
    private $view = 'default';

    /**
     * Renderer used for the Component
     *
     * @var null|\ProcessWire\Module
     */
    private $renderer;

    /**
     * Render markup for the Component
     *
     * @return string Rendered Component markup.
     */
    public function ___render(): string {
        return $this->renderView();
    }

    /**
     * Render markup for the Component using a separate Component View file
     *
     * @param string|null $view Component view file name (optional). If the name contains slash, it
     *                          will be assumed to contain a path and file name starting from the
     *                          components directory.
     * @return string Rendered Component markup.
     */
    public function ___renderView(string $view = null): string {
        $view = $view ?? $this->getView();
        if (!empty($view)) {

            // view file root path and view file
            $view_root = dirname((new \ReflectionClass($this))->getFileName());
            $view_file = (strpos($view, '/') ? '' : '/' . $this->className()) . '/' . $view;

            // attempt to render markup using a renderer
            $renderer = $this->getRenderer();
            if ($renderer) {
                $view_ext = '.' . ltrim($renderer->getExt(), '.');
                if (is_file($view_root . $view_file . $view_ext)) {
                    return $renderer->render('component', ltrim($view_file, '/') . $view_ext, $this->getData());
                }
            }

            // fall back to built-in PHP template renderer if necessary
            $view_ext = $this->wire('view')->getExt();
            if (is_file($view_root . $view_file . $view_ext)) {
                return $this->wire('files')->render($view_root . $view_file . $view_ext, $this->getData());
            }
        }
        return '';
    }

    /**
     * Get data for the Component
     *
     * Override this method if you want to have full control over the data that is used while rendering
     * the component. The method should return an associative array.
     *
     * @return array Associative array of data.
     */
    public function getData(): array {
        return parent::data(null, null);
    }

    /**
     * Set the view file name for the Component
     *
     * @param string $view View file name.
     * @return Component Self-reference.
     */
    final public function setView(string $view): Component {
        $this->emit('setView', ['view' => $view]);
        $this->view = $view;
        return $this;
    }

    /**
     * Get the view file name for the Component
     *
     * @return string View file name.
     */
    final public function getView(): string {
        return $this->view;
    }

    /**
     * Set renderer for the Component
     *
     * @param \ProcessWire\Module|string|null $renderer Renderer module, name of a renderer module, or null to unset.
     * @param array $settings Optional array of settings for the renderer module.
     * @return Component Self-reference.
     */
    final public function setRenderer($renderer, array $settings = []): Component {
        $needs_init = !empty($settings);
        if (is_null($renderer)) {
            $this->renderer = null;
        } else if (is_string($renderer)) {
            $renderer = $this->wire('modules')->get($renderer);
            $needs_init = true;
        }
        if ($renderer instanceof \ProcessWire\Module) {
            if ($needs_init) $renderer->init($settings);
            $this->renderer = $renderer;
        }
        return $this;
    }

    /**
     * Get renderer for the Component
     *
     * @return \ProcessWire\Module|null $renderer Wireframe renderer module or null.
     */
    final public function getRenderer(): ?\ProcessWire\Module {
        return $this->renderer ?: $this->wire('view')->getRenderer();
    }

    /**
     * Return rendered Component
     *
     * @return string
     */
    public function __toString() {
        return $this->render();
    }

}
