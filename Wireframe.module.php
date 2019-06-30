<?php

namespace ProcessWire;

/**
 * Wireframe ProcessWire module
 *
 * Wireframe is an output framework with MVC inspired architecture for ProcessWire CMS/CMF.
 * See README.md or https://wireframe-framework.com for more details.
 *
 * @version 0.2.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Wireframe extends WireData implements Module {

    /**
     * Config settings
     *
     * @var object
     */
    protected $config;

    /**
     * Paths from config
     *
     * @var object
     */
    protected $paths;

    /**
     * The extension for view, layout, and partial files
     *
     * @var string
     */
    protected $ext;

    /**
     * Current Page object
     *
     * @var Page
     */
    protected $page;

    /**
     * View object
     *
     * @var \Wireframe\View
     */
    protected $view;

    /**
     * Controller object
     *
     * @var \Wireframe\Controller
     */
    protected $controller;

    /**
     * View data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Initialize Wireframe
     *
     * @param array $settings Array of additional settings (optional)
     * @return Wireframe Self-reference
     *
     * @throws WireException if no valid Page object is found
     */
    public function ___init(array $settings = []): Wireframe {

        // initialize config settings
        $this->initConfig();

        // set any additional settings
        $this->setArray($settings);

        // make sure that we have a valid Page
        $this->page = $this->page ?? $this->wire('page');
        if (!$this->page || !$this->page->id) {
            throw new WireException('No valid Page object found');
        }

        // store paths and template extension as local variables
        $this->paths = (object) $this->config['paths'];
        $this->ext = "." . $this->wire('config')->templateExtension;

        // add Wireframe namespaces to ProcessWire's classLoader
        $this->addNamespaces();

        // set PHP include path
        $this->setIncludePath();

        // attach hooks
        $this->addHooks();

        // check for redirects
        $this->checkRedirects();

        // initialize View and Controller
        $this->initView();
        $this->initController();

        // choose the view to use
        $this->setView();

        // return self-reference
        return $this;

    }

    /**
     * Initialize config settings
     *
     * @param array $config Optional configuration settings array
     */
    public function ___initConfig(array $config = []) {

        // default config settings; if you need to customize or override any of
        // these, copy this array to /site/config.php as $config->wireframe
        $config_defaults = [
            'include_paths' => [
                // '/path/to/shared/libraries/',
            ],
            'redirect_fields' => [
                // 'redirect_to_url',
                // 'redirect_to_page' => [
                //     'property' => 'url',
                //     'permanent' => true,
                // ],
            ],
            'allow_get_view' => false,
            // 'allow_get_view' => [
            //     'home' => [
            //         'json',
            //         'rss',
            //     ],
            //     'json',
            // ],
            'paths' => [
                'lib' => $this->wire('config')->paths->templates . "lib/",
                'views' => $this->wire('config')->paths->templates . "views/",
                'layouts' => $this->wire('config')->paths->templates . "layouts/",
                'partials' => $this->wire('config')->paths->templates . "partials/",
                'controllers' => $this->wire('config')->paths->templates . "controllers/",
            ],
            'urls' => [
                'dist' => $this->wire('config')->urls->assets . "dist/",
                'resources' => $this->wire('config')->urls->templates . "resources/",
            ],
        ];

        // combine default config settings with custom ones
        $this->config = array_merge(
            $config_defaults,
            is_array($this->wire('config')->wireframe) ? $this->wire('config')->wireframe : [],
            $config
        );

        // URL additions to global config settings
        foreach ($this->config['urls'] as $key => $value) {
            $this->wire('config')->urls->set($key, $value);
        }

    }

    /**
     * Add Wireframe namespaces to ProcessWire's classLoader
     *
     */
    protected function addNamespaces() {
        $namespaces = [
            'Wireframe' => $this->wire('config')->paths->Wireframe . 'lib/',
            'Wireframe\Controller' => $this->paths->controllers,
        ];
        foreach ($namespaces as $namespace => $path) {
            $this->wire('classLoader')->addNamespace($namespace, $path);
        }
    }

    /**
     * Set PHP include path
     *
     */
    protected function setIncludePath() {
        $include_paths = [
            $this->wire('config')->paths->templates,
        ];
        if (!empty($this->config['include_paths'])) {
            $include_paths = array_merge(
                $include_paths,
                $this->config['include_paths']
            );
        }
        if (strpos(get_include_path(), $include_paths[0]) === false) {
            set_include_path(
                get_include_path() .
                PATH_SEPARATOR .
                implode(PATH_SEPARATOR, $include_paths)
            );
        }
    }

    /**
     * Attach hooks
     *
     * @see pageLayout() for the Page::layout implementation
     * @see pageView() for the Page::view implementation
     */
    protected function addHooks() {

        // helper method for getting or setting page layout
        $this->addHookMethod('Page::layout', $this, 'pageLayout');

        // helper method for getting or setting page view
        $this->addHookMethod('Page::view', $this, 'pageView');

    }

    /**
     * Check if a redirect should occur
     *
     * Look for redirect fields within config settings. If present, check if the
     * page has a value in one of those and if a redirect should be performed.
     */
    public function ___checkRedirects() {

        // params
        $config = $this->config;
        $page = $this->page;

        if (!empty($config['redirect_fields'])) {
            foreach ($config['redirect_fields'] as $field => $options) {
                if (is_int($field) && is_string($options)) {
                    $field = $options;
                }
                if ($page->$field) {
                    $url = $page->$field;
                    $permanent = false;
                    if (is_array($options)) {
                        if (isset($options['property'])) {
                            $url = $url->$options['property'];
                        }
                        if (isset($options['permanent'])) {
                            $permanent = (bool) $options['permanent'];
                        }
                    }
                    if (is_string($url) && $url != $page->url && $this->wire('sanitizer')->url($url)) {
                        $this->redirect($url, $permanent);
                    }
                }
            }
        }

    }

    /**
     * Perform a redirect
     *
     * @param string $url Redirect URL
     * @param bool $permanent Is this a ermanent (301) redirect?
     */
    public function ___redirect(string $url, bool $permanent) {
        $this->wire('session')->redirect($url, $permanent);
    }

    /**
     * Initialization method for the View
     *
     * This method initializes the View object and the $view API variable.
     *
     * @return \Wireframe\View View object
     *
     * @throws WireException if no valid Page has been defined
     */
    public function ___initView(): \Wireframe\View {

        // params
        $page = $this->page;
        $paths = $this->paths;
        $ext = $this->ext;
        $data = $this->data;

        // initialize the View object
        $view = new \Wireframe\View;
        $view->setLayout($page->layout() === null ? 'default' : $page->layout());
        $view->setView($page->view());
        $view->setData($data);
        $view->setPartials($this->getFilesRecursive($paths->partials . "*", $ext));
        $view->setPlaceholders(new \Wireframe\ViewPlaceholders($page, $paths->views, $ext));
        $this->view = $view;

        // define the $view API variable
        $this->wire('view', $view);

        return $view;

    }

    /**
     * Initialization method for the Controller
     *
     * Controller is optional component in Wireframe, but if a Controller file is found, we'll
     * attempt to instantiate an object from it.
     *
     * @return \Wireframe\Controller|null Controller object or null
     *
     * @throws WireException if no valid Page has been defined
     */
    public function ___initController(): ?\Wireframe\Controller {

        // params
        $page = $this->page;
        $view = $this->view;

        // define template name and Controller class name
        $controller = null;
        $controller_name = $this->wire('sanitizer')->pascalCase($page->template);
        $controller_class = '\Wireframe\Controller\\' . $controller_name . 'Controller';

        if (class_exists($controller_class)) {
            $controller = new $controller_class($this->wire(), $page, $view);
        }

        $this->controller = $controller;

        return $controller;

    }

    /**
     * Set current view
     *
     * Default value is 'default', but view() method of the $page object or GET param
     * 'view' (if configured) can be used to override the default value.
     */
    public function ___setView() {

        // params
        $config = $this->config;
        $paths = $this->paths;
        $page = $this->page;
        $view = $this->view;
        $template = $view->template ?: $page->template;
        $ext = $this->ext;

        // ProcessWire's $input API variable
        $input = $this->wire('input');

        $get_view = null;
        if ($input->get->view && $allow_get_view = $config['allow_get_view']) {
            if (is_array($allow_get_view)) {
                // allowing *any* view to be accessed via a GET param might not be
                // appropriate; using a whitelist lets us define the allowed values
                foreach ($allow_get_view as $get_template => $get_value) {
                    if (is_string($get_template) && is_array($get_value) && $template == $get_template) {
                        $get_view = in_array($input->get->view, $get_value) ? $input->get->view : null;
                        break;
                    } else if (is_int($get_template) && is_string($get_value) && $input->get->view == $get_value) {
                        $get_view = $input->get->view;
                        break;
                    }
                }
            } else {
                $get_view = $input->get->view;
            }
        }
        $view->setView(basename($view->view ?: ($page->view() ?: ($get_view ?: 'default'))));
        if ($view->view != 'default' && !is_file($paths->views . $template . '/' . $view->view . $ext)) {
            $view->setView('default');
        }
        if ($view->view != 'default' || is_file($paths->views . $template . '/' . $view->view . $ext)) {
            $view->setFilename($paths->views . $template . "/" . $view->view . $ext);
            if ($page->_wireframe_context != 'placeholder') {
                if ($view->view != 'default' && !$view->allow_cache) {
                    // not using the default view, disable page cache
                    $this->wire('session')->PageRenderNoCachePage = $page->id;
                } else if ($this->wire('session')->PageRenderNoCachePage === $page->id) {
                    // make sure that page cache isn't skipped unnecessarily
                    $this->wire('session')->remove('PageRenderNoCachePage');
                }
            }
        }

    }

    /**
     * Render the Page with specified View and Layout
     *
     * Note: this method returns null if both view and layout file are undefined.
     *
     * @param array $data Array of data to send to View
     * @return string|null Rendered Page markup or null
     */
    public function ___render(array $data = []): ?string {

        // params
        $view = $this->view;
        $paths = $this->paths;
        $ext = $this->ext;

        // process optional data array
        if (!empty($data)) {
            $this->set('data', array_merge(
                $this->data,
                $data
            ));
            $view->addData($this->data);
        }

        // render output
        $out = null;
        if ($view->filename || $view->layout) {
            $out = $view->render();
            if ($filename = basename($view->layout)) {
                // layouts make it possible to define a common base structure for
                // multiple otherwise separate template and view files (DRY)
                $view->setFilename($paths->layouts . $filename . $ext);
                if (!$view->placeholders->default) {
                    $view->placeholders->default = $out;
                }
                $out = $view->render();
            }
        }

        // return rendered output
        return $out;

    }

    /**
     * Helper method for getting or setting page layout
     *
     * Example: <?= $page->layout('default')->render() ?>
     *
     * @param HookEvent $event
     * @see addHooks() for the code that attaches Wireframe hooks
     */
    public function pageLayout(HookEvent $event) {
        if (!isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_layout;
        } else {
            $event->object->_wireframe_layout = $event->arguments[0];
            $event->return = $event->object;
        }
    }

    /**
     * Helper method for getting or setting page view
     *
     * Example: <?= $page->view('json')->render() ?>
     *
     * @param HookEvent $event
     * @see addHooks() for the code that attaches Wireframe hooks
     */
    public function pageView(HookEvent $event) {
        if (!isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_view;
        } else {
            $event->object->_wireframe_view = $event->arguments[0];
            $event->return = $event->object;
        }
    }

    /**
     * PHP magic getter method
     *
     * This is an alias for the get() method.
     *
     * @param string $key Name of the variable
     * @return mixed Value of the variable, or null if it doesn't exist
     */
    public function __get($key) {
        return $this->get($key);
    }

    /**
     * PHP magic setter method
     *
     * This is an alias for the set() method.
     *
     * @param string $key Name of the variable
     * @param string $value Value for the variable
     * @return Wireframe Self-reference
     */
    public function __set($key, $value): Wireframe {
        return $this->set($key, $value);
    }

    /**
	 * Getter method for specific class properties
     *
     * Note that this differs notably from the parent class' get() method: unlike
     * in WireData, here we limit the scope of the method to specific, predefined
     * class properties instead of returning any index from the "data" array. We
     * also don't support pipe ("|") separated strings or objects as arguments.
     *
     * @param string $key Name of property you want to retrieve
     * @return mixed Property value, or null if requested property is unrecognized
     */
    public function get($key) {
        $return = null;
        switch ($key) {
            case 'paths':
            case 'ext':
            case 'page':
            case 'view':
            case 'controller':
                $return = $this->$key;
                break;
        }
        return $return;
    }

    /**
     * General purpose setter method
     *
     * @param string $key Name of the variable
     * @param string $value Value for the variable
     * @return Wireframe Self-reference
     *
     * @throws WireException if trying to set value to unrecognized property
     * @throws WireException if trying to set invalid value to a property
     */
    public function set($key, $value): Wireframe {
        $invalid_value = true;
        switch ($key) {
            case 'data':
                if (is_array($value)) {
                    $this->$key = $value;
                    $invalid_value = false;
                }
                break;
            case 'page':
                if ($value instanceof Page && $value->id) {
                    $this->$key = $value;
                    $invalid_value = false;
                }
                break;
            default:
                throw new WireException(sprintf(
                    'Unable to set value for unrecognized property "%s"',
                    $key
                ));
        }
        if ($invalid_value) {
            throw new WireException(sprintf(
                'Invalid value provided for "%s"',
                $key
            ));
        }
        return $this;
    }

    /**
     * Set values from an array
     *
     * This method is a wrapper for the set() method, with support for multiple
     * values as an associative array.
     *
     * @param array $values Values as an associative array
     * @return Wireframe Self-reference
     */
    public function setArray(array $values = []): Wireframe {
        if (!empty($values)) {
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        }
        return $this;
    }

    /**
     * Fetch a list of files recursively
     *
     * This is a helper method used for fetching a list of files and folders
     * recursively and returning the result as an object. Originally added
     * for storing partial file references in an easy to access way.
     *
     * @param string $path Base directory
     * @param string $ext File extension
     * @return \stdClass
     */
    protected function getFilesRecursive(string $path, string $ext): \stdClass {
        $files = [];
        foreach (glob($path) as $file) {
            $name = basename($file);
            if (strpos($name, ".") === 0) continue;
            if (is_dir($file)) {
                $files[$name] = $this->getFilesRecursive("{$file}/*", $ext);
            } else if (strrpos($name, $ext) === strlen($name)-strlen($ext)) {
                $files[substr($name, 0, strrpos($name, "."))] = $file;
            }
        }
        return (object) $files;
    }

}
