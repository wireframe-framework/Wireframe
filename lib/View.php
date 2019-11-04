<?php

namespace Wireframe;

/**
 * Wireframe View component
 *
 * This class is a wrapper for the ProcessWire TemplateFile class with some additional features and
 * the Wireframe namespace.
 *
 * @property ViewPlaceholders|null $placeholders ViewPlaceholders object.
 * @property \stdClass|null $partials Object containing partial paths.
 *
 * @version 0.4.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 *
 * @todo bodyClasses helper (template, delegated-template, others?)
 */
class View extends \ProcessWire\TemplateFile {

    /**
     * Wireframe View Data object
     *
     * @var ViewData
     */
    protected $_wireframe_view_data;

    /**
     * Local data, made available to layouts and views
     *
     * @var array
     */
    protected $data = [];

    /**
     * Partials object
     *
     * @var \stdClass
     */
    protected $partials;

    /**
     * View Placeholders object
     *
     * @var ViewPlaceholders
     */
    protected $placeholders;

    /**
     * Constructor method
     */
    public function __construct() {
        $this->_wireframe_view_data = new ViewData();
        parent::__construct();
    }

    /**
     * Magic __set() method
     *
     * This method provides access to certain predefined properties of the ViewData object.
     *
     * @param string $key Name of the variable
     * @param mixed $value Value for the variable
     * @return void
     */
    public function __set($key, $value) {
        if ($key == 'placeholders') {
            $this->setPlaceholders($value);
        } else if ($key == 'partials') {
            $this->setPartials($value);
        }
        parent::__set($key, $value);
    }

    /**
     * PHP's magic __get() method
     *
     * This method provides access to variables stored in the data array or the ViewData object,
     * some predefined protected properties, and the properties of the linked Controller instance.
     *
     * @param string $key Name of the Controller method
     * @return mixed Value of the key or null
     */
    public function __get($key) {
        if ($key == 'placeholders') {
            return $this->getPlaceholders();
        } else if ($key == 'partials') {
            return $this->getPartials();
        }
        $value = $this->get($key);
        if (!$value) {
            $value = $this->getFromController($key);
        }
        return $value;
    }

    /**
     * Setter method for the Controller class
     *
     * @param Controller|null Controller instance or null
     * @return View Self-reference
     */
    public function setController(?Controller $controller): View {
        $this->setViewData('controller', $controller);
        return $this;
    }

    /**
     * Getter method for the Controller class
     *
     * @return Controller|null Controller instance or null
     */
    public function getController(): ?Controller {
        return $this->getViewData('controller');
    }

    /**
     * Getter method for real or dynamically generated properties of the Controller class
     *
     * @param string $key Property name
     * @return mixed Property value or null
     */
    protected function getFromController(string $key) {
        $controller = $this->getController();
        if ($controller) {
            $value = $controller->$key;
            return $value;
        }
        return null;
    }

    /**
     * Setter method for the layout file
     *
     * @param string|null $layout Layout file name
     * @return View Self-reference
     */
    public function setLayout(?string $layout): View {
        $this->setViewData('layout', $layout);
        return $this;
    }

    /**
     * Getter method for the layout file
     *
     * @return string|null Layout file name
     */
    public function getLayout(): ?string {
        return $this->getViewData('layout');
    }

    /**
     * Getter method for the view file filename
     *
     * Note that this is the filename required by TemplateFile, which we're extending with current
     * class. As such, it's not the same thing as the view file filename in ViewData, which can be
     * accessed (internally) via View::getViewFilename() and View::setViewFilename().
     *
     * @return string|null View file filename
     */
    public function getFilename(): ?string {
        return $this->filename;
    }

    /**
     * Setter method for the view file
     *
     * @param string|null $view View file name
     * @return View Self-reference
     */
    public function setView(?string $view): View {

        // set view file name
        $this->setViewData('view', $view);

        // bail out early if view is empty (halt to make sure that we won't return any markup)
        if (empty($view)) {
            $this->halt();
            return $this;
        }

        // make sure that halt hasn't been called earlier
        $this->halt(false);

        // view data
        $page = $this->getViewData('page');
        $view = $this->getViewData('view');
        $view_filename = $this->getViewFilename();

        // if we're trying to use non-existing, non-default view file, fall back to default
        if ($view != 'default' && !is_file($view_filename)) {
            return $this->setView('default');
        }

        // if using existing, non-default view file, set filename and handle page caching
        if ($view != 'default' || is_file($view_filename)) {
            $this->setFilename($view_filename);
            if ($page->_wireframe_context != 'placeholder') {
                if ($view != 'default' && !$this->allow_cache) {
                    // not using the default view, disable page cache
                    $this->wire('session')->PageRenderNoCachePage = $page->id;
                } else if ($this->wire('session')->PageRenderNoCachePage === $page->id) {
                    // make sure that page cache isn't skipped unnecessarily
                    $this->wire('session')->remove('PageRenderNoCachePage');
                }
            }
        }

        return $this;
    }

    /**
     * Getter method for the view file
     *
     * @return string|null View file name
     */
    public function getView(): ?string {
        return $this->getViewData('view');
    }

    /**
     * Setter method for the template
     *
     * @param string|null $template Template name
     * @return View Self-reference
     */
    public function setTemplate(?string $template): View {
        $this->setViewData('template', $template);
        return $this;
    }

    /**
     * Getter method for the template
     *
     * @return string|null Template name
     */
    public function getTemplate(): ?string {
        return $this->getViewData('template');
    }

    /**
     * Set, validate, and format path to the views directory
     *
     * @internal
     *
     * @param string $views_path Path to the views directory
     * @return View Self-reference
     * @throws Exception if path to the views directory is missing or unreadable.
     */
    public function setViewsPath(string $views_path): View {
        if (!is_dir($views_path)) {
            throw new \Exception(sprintf(
                'Missing or unreadable path to the views directory: "%"',
                $views_path
            ));
        }
        $views_path = rtrim($views_path, '/') . '/';
        $this->setViewData('views_path', $views_path);
        return $this;
    }

    /**
     * Getter for the path to the views directory
     *
     * @internal
     *
     * @return string Path to the views directory
     */
    public function getViewsPath(): string {
        return $this->getViewData('views_path');
    }

    /**
     * Getter for complete view file path
     *
     * @internal
     *
     * @param string $view Optional name of the view file
     * @param string $template Optional name of the template
     * @return string View file path
     */
    public function getViewFilename(string $view = null): string {

        // view data
        $views_path = $this->getViewData('views_path');
        $template = $this->getViewData('template');
        $view = $view ?: $this->getViewData('view');
        $ext = $this->getViewData('ext');

        return $views_path . $template . '/' . $view . $ext;
    }

    /**
     * Set, validate, and format view file extension
     *
     * @param string $ext File extension
     * @return View Self-reference
     *
     * @throws Exception if invalid format is used for view file extension.
     */
    public function setExt(string $ext): View {
        if (basename($ext) !== $ext) {
            throw new \Exception(sprintf(
                'View file extension does not match expected format: "%s".',
                $ext
            ));
        }
        if (strpos($ext, '.') !== 0) {
            $ext = '.' . $ext;
        }
        return $this->setViewData('ext', $ext);
    }

    /**
     * Getter for view file extension
     *
     * @return string File extension
     */
    public function getExt(): string {
        return $this->getViewData('ext');
    }

    /**
     * Setter method for current Page object
     *
     * @param \ProcessWire\Page $page Page object
     * @return View Self-reference
     */
    public function setPage(\ProcessWire\Page $page): View {
        return $this->setViewData('page', $page);
    }

    /**
     * Getter method for current Page object
     *
     * @return \ProcessWire\Page Page object
     */
    public function getPage(): \ProcessWire\Page {
        return $this->getViewData('page');
    }

    /**
     * Setter method for view placeholders
     *
     * @param ViewPlaceholders|null ViewPlaceholders instance or null
     * @return View Self-reference
     */
    public function setPlaceholders(?ViewPlaceholders $placeholders): View {
        $this->placeholders = $placeholders;
        return $this;
    }

    /**
     * Getter method for view placeholders
     *
     * @return ViewPlaceholders|null ViewPlaceholders instance or null
     */
    public function getPlaceholders(): ?ViewPlaceholders {
        return $this->placeholders;
    }

    /**
     * Setter method for the partials object
     *
     * @param \stdClass|null Object containing partial paths or null
     * @return View Self-reference
     */
    public function setPartials(?\stdClass $partials): View {
        $this->partials = $partials;
        return $this;
    }

    /**
     * Getter method for the partials object
     *
     * @return \stdClass|null Object containing partial paths or null
     */
    public function getPartials(): ?\stdClass {
        return $this->partials;
    }

    /**
     * Setter method for the data array
     *
     * @param array $data Data array
     * @return View Self-reference
     */
    public function setData(array $data = []): View {
        $this->data = $data;
        return $this;
    }

    /**
     * Setter method for view data values
     *
     * @param string $key View data key
     * @param mixed $value View data value
     * @return View Self-reference
     */
    protected function setViewData(string $key, $value): View {
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

    /**
     * Add new data to the view data array
     *
     * @param array $data Data array
     * @return View Self-reference
     */
    public function addData(array $data = []): View {
        $this->data = array_merge(
            $this->data,
            $data
        );
        return $this;
    }

    /**
     * Get an array of all variables accessible (locally scoped) to layouts and views
     *
     * We're overriding parent class method here so that we can add some additional variables to the
     * mix. Basically all protected or private properties we want layouts and views to see need to
     * be included here.
     *
     * @return array
     */
    public function getArray(): array {
        return array_merge(parent::getArray(), [
            'placeholders' => $this->placeholders,
            'partials' => $this->partials,
        ]);
    }

}
