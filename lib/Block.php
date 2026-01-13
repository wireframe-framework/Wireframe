<?php

namespace Wireframe;

use ProcessWire\RepeaterMatrixPage;

/**
 * Abstract base implementation for Block objects
 *
 * Blocks are used to render RepeaterMatrix content blocks with a structured
 * approach similar to Components. Each block type can have its own class
 * extending this base, with view files for different rendering variants.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Block extends \ProcessWire\WireData {

    use RendererTrait;
    use MethodPropsTrait;

    /**
     * The RepeaterMatrixPage being rendered
     *
     * @var RepeaterMatrixPage
     */
    protected $page;

    /**
     * Block type identifier (e.g., 'text-content', 'image')
     *
     * @var string
     */
    protected $type;

    /**
     * CSS classes for the block wrapper
     *
     * @var array
     */
    protected $classes = [];

    /**
     * HTML attributes for the block wrapper
     *
     * @var array
     */
    protected $props = [];

    /**
     * Anchor ID for the block
     *
     * @var string
     */
    protected $anchor = '';

    /**
     * View file name
     *
     * @var string
     */
    private $view = 'default';

    /**
     * Block name for view directory lookup
     *
     * @var string|null
     */
    protected $blockName = null;

    /**
     * Local instance of the Wireframe module
     *
     * @var \ProcessWire\Wireframe|null
     */
    private $_wireframe = null;

    /**
     * Constructor
     *
     * @param RepeaterMatrixPage $page The repeater matrix page to render
     */
    public function __construct(RepeaterMatrixPage $page) {
        $this->page = $page;
        $this->type = $this->resolveType($page);
        $this->anchor = $page->anchor_name ?? '';
        $this->initClasses();
        $this->init();
    }

    /**
     * Resolve block type from matrix type
     *
     * @param RepeaterMatrixPage $page
     * @return string
     */
    protected function resolveType(RepeaterMatrixPage $page): string {
        $type = str_replace('_', '-', $page->matrix('type'));
        if (str_ends_with($type, '-block')) {
            $type = substr($type, 0, -6);
        }
        return $type;
    }

    /**
     * Initialize default CSS classes
     */
    protected function initClasses(): void {
        $this->classes = [
            'content-block',
            'content-block--type-' . $this->type,
            $this->type . '-block',
        ];
    }

    /**
     * Init method called after construction
     *
     * Override this in subclasses for custom initialization.
     */
    protected function init(): void {}

    /**
     * PHP magic getter method
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        // First check if it's a page field
        if ($this->page->hasField($name)) {
            return $this->page->get($name);
        }
        return $this->getMethodProp($name, 'block');
    }

    /**
     * Add a CSS class to the block wrapper
     *
     * @param string $class
     * @return self
     */
    public function addClass(string $class): self {
        $this->classes[] = $class;
        return $this;
    }

    /**
     * Add HTML attributes to the block wrapper
     *
     * @param array $props
     * @return self
     */
    public function addProps(array $props): self {
        $this->props = array_merge($this->props, $props);
        return $this;
    }

    /**
     * Get CSS classes as a string
     *
     * @return string
     */
    public function getClass(): string {
        return implode(' ', $this->classes);
    }

    /**
     * Get the anchor ID
     *
     * @return string
     */
    public function getAnchor(): string {
        return $this->anchor;
    }

    /**
     * Get the block type
     *
     * @return string
     */
    public function getType(): string {
        return $this->type;
    }

    /**
     * Get the RepeaterMatrixPage
     *
     * @return RepeaterMatrixPage
     */
    public function getPage(): RepeaterMatrixPage {
        return $this->page;
    }

    /**
     * Render HTML attributes for the block wrapper
     *
     * @return string
     */
    public function renderProps(): string {
        $sanitizer = $this->wire('sanitizer');
        $output = 'class="' . $sanitizer->entities1($this->getClass()) . '"';
        if ($this->anchor) {
            $output .= ' id="' . $sanitizer->entities1($this->anchor) . '"';
        }
        foreach ($this->props as $key => $value) {
            $output .= ' ' . $sanitizer->entities1($key);
            if ($value !== '') {
                $output .= '="' . $sanitizer->entities1($value) . '"';
            }
        }
        return $output;
    }

    /**
     * Render the block
     *
     * @return string
     */
    public function ___render(): string {
        return $this->renderView();
    }

    /**
     * Render the block view
     *
     * @param string|null $view View file name
     * @return string
     */
    public function ___renderView(string $view = null): string {
        $view = $view ?? $this->getView();
        if (empty($view)) {
            return '';
        }

        $view_root = $this->wire('config')->paths->templates . 'blocks';
        $block_name = $this->blockName ?? $this->className();
        $view_file = '/' . $block_name . '/' . $view;

        // Attempt to render using a renderer (Twig, Latte, etc.)
        $renderer = $this->getRenderer();
        if ($renderer) {
            $view_ext = '.' . ltrim($renderer->getExt(), '.');
            if (\is_file($view_root . $view_file . $view_ext)) {
                return $renderer->render('block', ltrim($view_file, '/') . $view_ext, $this->getData());
            }
        }

        // Fall back to PHP template
        $php_file = $view_root . $view_file . '.php';
        if (\is_file($php_file)) {
            $block_view = $this->wire(new BlockView($this));
            $block_view->data($this->getData());
            $block_view->setFilename($php_file);
            return $block_view->render();
        }

        return '';
    }

    /**
     * Get data for the block view
     *
     * @return array
     */
    public function getData(): array {
        return array_merge(
            parent::data(null, null),
            [
                'page' => $this->page,
                'block' => $this,
            ]
        );
    }

    /**
     * Set the block name for view directory lookup
     *
     * @param string $name
     * @return self
     */
    public function setBlockName(string $name): self {
        $this->blockName = $name;
        return $this;
    }

    /**
     * Set the view file name
     *
     * @param string $view
     * @return self
     */
    final public function setView(string $view): self {
        $this->view = $view;
        return $this;
    }

    /**
     * Get the view file name
     *
     * @return string
     */
    final public function getView(): string {
        return $this->view;
    }

    /**
     * Return rendered block
     *
     * @return string
     */
    public function __toString(): string {
        return $this->render();
    }

}
