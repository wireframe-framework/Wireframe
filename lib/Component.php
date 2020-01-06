<?php

namespace Wireframe;

/**
 * Abstract base implementation for Component objects
 *
 * @version 0.0.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Component extends \ProcessWire\WireData {

    /**
     * Default view file for the Component
     *
     * @var string
     */
    private $view = 'default';

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
            $view_file = dirname((new \ReflectionClass($this))->getFileName())
                       . (strpos($view, '/') ? '' : '/' . $this->className())
                       . '/' . $view
                       . $this->wire('view')->getExt();
            if (file_exists($view_file)) {
                return $this->wire('files')->render($view_file, $this->data);
            }
        }
        return '';
    }

    /**
     * Set the view file name for the Component
     *
     * @param string $view View file name.
     */
    public function setView(string $view) {
        $this->view = $view;
    }

    /**
     * Get the view file name for the Component
     *
     * @return string View file name.
     */
    public function getView(): string {
        return $this->view;
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
