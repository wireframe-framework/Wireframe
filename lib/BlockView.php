<?php

namespace Wireframe;

/**
 * Wireframe Block View
 *
 * This class is a wrapper for the ProcessWire TemplateFile class with some additional features
 * for rendering Block views.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class BlockView extends \ProcessWire\TemplateFile {

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
     * @param Block $block
     */
    public function __construct(Block $block) {
        $this->_wireframe_view_data = new ViewData();
        $this->setBlock($block);
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
            $view_context = array_merge($this->getArray(), self::$globals);
            /** @noinspection PhpUndefinedMethodInspection */
            return $this->renderer->render('block', $this->filename, $view_context);
        }

        return parent::___render();
    }

    /**
     * PHP's magic __get() method
     *
     * This method provides access to properties of the linked Block instance.
     *
     * @param string $key Name of the Block property or method
     * @return mixed Value of the key or null
     */
    public function __get($key) {
        $value = $this->get($key);
        if ($value === null) {
            $value = $this->getFromBlock($key);
        }
        return $value;
    }

    /**
     * Getter method for the Block class
     *
     * @return Block Block instance
     */
    public function getBlock(): Block {
        return $this->getViewData('block');
    }

    /**
     * Setter method for the Block class
     *
     * @param Block $block
     * @return BlockView Self-reference
     */
    public function setBlock(Block $block): BlockView {
        $this->setViewData('block', $block);
        return $this;
    }

    /**
     * Setter method for the Renderer
     *
     * @param \ProcessWire\Module $renderer
     * @return BlockView Self-reference
     */
    public function setRenderer(\ProcessWire\Module $renderer): BlockView {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Getter method for real or dynamically generated properties of the Block class
     *
     * @param string $key Property name
     * @return mixed Property value or null
     */
    protected function getFromBlock(string $key) {
        $block = $this->getViewData('block');
        if ($block) {
            return $block->$key;
        }
        return null;
    }

    /**
     * Setter method for view data values
     *
     * @param string $key View data key
     * @param mixed $value View data value
     * @return BlockView Self-reference
     */
    protected function setViewData(string $key, $value): BlockView {
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
