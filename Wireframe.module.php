<?php

namespace ProcessWire;

/**
 * Wireframe ProcessWire module
 *
 * Wireframe is an output framework with MVC inspired architecture for ProcessWire CMS/CMF.
 * See README.md or https://wireframe-framework.com for more details.
 *
 * @version 0.6.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Wireframe extends WireData implements Module, ConfigurableModule {

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
     * Settings hash for comparison purposes
     *
     * @var string
     */
    protected $settings_hash;

    /**
     * Create directories automatically?
     *
     * This property is only used by the module configuration screen. Contains an array of
     * directories to create automatically.
     *
     * @var array
     */
    protected $create_directories = [];

    /**
     * Return inputfields necessary to configure the module
     *
     * @param array $data Data array.
     * @return InputfieldWrapper Wrapper with inputfields needed to configure the module.
     */
    public function getModuleConfigInputfields(array $data) {

        // init necessary parts of Wireframe
        $this->setConfig();
        $this->setPaths();
        $this->addNamespaces();

        // instantiate Wireframe Config and get all config inputfields
        $config = new \Wireframe\Config($this->wire(), $this);
        $fields = $config->getAllFields();

        return $fields;
    }

    /**
     * General purpose cache array
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Keep track of whether Wireframe has already been initialized
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * Initialize Wireframe
     *
     * @param array $settings Array of additional settings (optional).
     * @return Wireframe Self-reference.
     *
     * @throws WireException if no valid Page object is found.
     */
    public function ___init(array $settings = []): Wireframe {

        // perform init tasks that should only run once
        $this->initOnce();

        // set any additional settings
        $this->setArray($settings);

        // make sure that we have a valid Page
        $this->page = $settings['page'] ?? $this->wire('page');
        if (!$this->page || !$this->page->id) {
            throw new WireException('No valid Page object found');
        }

        // store template extension locally
        $this->setExt();

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
     * This method performs init tasks that should only run once
     *
     * @return bool True on first run, false if already initialized.
     */
    protected function initOnce(): bool {

        // bail out early if already initialized
        if ($this->initialized) return false;

        // set config settings
        $this->setConfig();

        // store paths locally (unless already manually defined)
        if (empty($this->paths)) $this->setPaths();

        // add Wireframe namespaces to ProcessWire's class autoloader
        $this->addNamespaces();

        // set PHP include path
        $this->setIncludePath();

        // attach hooks
        $this->addHooks();

        // remember that this method has been run and return true
        $this->initialized = true;

        // return true on first run
        return true;

    }

    /**
     * Define runtime config settings
     *
     * @param array $config Optional configuration settings array.
     * @return Wireframe Self-reference.
     */
    public function ___setConfig(array $config = []): Wireframe {

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

        return $this;
    }

    /**
     * Store paths in a class property
     *
     * @param array $paths Paths array for overriding the default value.
     * @return Wireframe Self-reference.
     */
    public function setPaths(array $paths = []): Wireframe {
        $this->paths = (object) $this->config['paths'];
        return $this;
    }

    /**
     * Store template extension in a class property
     *
     * @param string|null $ext Extension string for overriding the default value.
     * @return Wireframe Self-reference.
     */
    public function setExt(string $ext = null): Wireframe {
        $this->ext = "." . ($ext ?? $this->wire('config')->templateExtension);
        return $this;
    }

    /**
     * Add Wireframe namespaces to ProcessWire's class autoloader
     *
     * This method makes ProcessWire's class autoloader aware of the Wireframe namespaces, which
     * enables us to instantiate – or call static methods from – Wireframe objects without first
     * requiring the related PHP file.
     *
     * If you need to add additional namespaces (or additional paths for namespaces added here),
     * access the $classLoader API variable directly from your own code. If you want to override
     * these definitions, you should first call $classLoader->removeNamespace($namespace, $path)
     * – and then re-add the same namespace with your own path.
     */
    protected function addNamespaces() {
        $namespaces = [
            'Wireframe' => $this->wire('config')->paths->Wireframe . 'lib/',
            'Wireframe\Controller' => $this->paths->controllers,
            'Wireframe\Lib' => $this->paths->lib,
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
     * @see pageLayout() for the Page::layout(), Page::getLayout(), and Page::setLayout() implementation.
     * @see pageView() for the Page::view(), Page::getView(), and Page::setView() implementation.
     */
    protected function addHooks() {

        // helper methods for getting or setting page layout
        $this->addHookMethod('Page::layout', $this, 'pageLayout');
        $this->addHookMethod('Page::getLayout', $this, 'pageLayout');
        $this->addHookMethod('Page::setLayout', $this, 'pageLayout');

        // helper methods for getting or setting page view
        $this->addHookMethod('Page::view', $this, 'pageView');
        $this->addHookMethod('Page::getView', $this, 'pageView');
        $this->addHookMethod('Page::setView', $this, 'pageView');

    }

    /**
     * Check if a redirect should occur
     *
     * Look for redirect fields within config settings. If present, check if the
     * page has a value in one of those and if a redirect should be performed.
     */
    public function ___checkRedirects() {

        // redirect fields from Wireframe runtime configuration
        $redirect_fields = $this->config['redirect_fields'] ?? null;
        if (empty($redirect_fields)) return;

        // current Page object
        $page = $this->page;

        foreach ($redirect_fields as $field => $options) {

            // redirect_fields may be an indexed array
            if (is_int($field) && is_string($options)) {
                $field = $options;
            }

            // get URL from a page field
            $url = $page->get($field);
            if (empty($url)) continue;

            // default to non-permanent redirect (302)
            $permanent = false;

            // if options is an array, read contained settings
            if (is_array($options)) {
                if (!empty($options['property'])) {
                    $url = $url->get($options['property']);
                }
                if (!empty($options['permanent'])) {
                    $permanent = (bool) $options['permanent'];
                }
            }

            // if target URL is valid and doesn't belong to current page, perform a redirect
            if (is_string($url) && $url != $page->url && $this->wire('sanitizer')->url($url)) {
                $this->redirect($url, $permanent);
            }
        }
    }

    /**
     * Perform a redirect
     *
     * @param string $url Redirect URL.
     * @param bool $permanent Is this a permanent (301) redirect?
     */
    public function ___redirect(string $url, bool $permanent) {
        $this->wire('session')->redirect($url, $permanent);
    }

    /**
     * Initialization method for the View
     *
     * This method initializes the View object and the $view API variable.
     *
     * @return \Wireframe\View View object.
     *
     * @throws WireException if no valid Page has been defined.
     */
    public function ___initView(): \Wireframe\View {

        // params
        $page = $this->page;
        $paths = $this->paths;
        $ext = $this->ext;
        $data = $this->data;

        // initialize the View object
        $view = new \Wireframe\View;
        $view->setLayout($page->getLayout() === null ? 'default' : $page->getLayout());
        $view->setView($page->getView());
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
     * @return \Wireframe\Controller|null Controller object or null.
     *
     * @throws WireException if no valid Page has been defined.
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
     * @param array $data Array of data to send to View.
     * @return string|null Rendered Page markup or null.
     */
    public function ___render(array $data = []): ?string {

        // params
        $view = $this->view;
        $paths = $this->paths;
        $ext = $this->ext;

        // attempt to return prerendered value from cache
        $cache_key = implode(':', [
            'render',
            $this->page->id,
            $this->settings_hash,
            empty($data) ? '' : md5(json_encode($data)),
            $view->filename,
            $view->layout,
            $ext,
        ]);
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        // process optional data array
        if (!empty($data)) {
            $this->set('data', array_merge(
                $this->data,
                $data
            ));
            $view->addData($this->data);
        }

        // render output
        $output = null;
        if ($view->filename || $view->layout) {
            $output = $view->render();
            if ($filename = basename($view->layout)) {
                // layouts make it possible to define a common base structure for
                // multiple otherwise separate template and view files (DRY)
                $view->setFilename($paths->layouts . $filename . $ext);
                if (!$view->placeholders->default) {
                    $view->placeholders->default = $output;
                }
                $output = $view->render();
            }
        }

        // store value in cache
        $this->cache[$cache_key] = $output;

        return $output;
    }

    /**
     * This method is used by Page::layout(), Page::getLayout(), and Page::setLayout()
     *
     * Example use with combined getter/setter method:
     *
     * ```
     * The layout for current page is "<?= $page->layout() ?>".
     * <?= $page->layout('another-layout')->render() ?>
     * ```
     *
     * Example use with dedicated getter/setter methods:
     *
     * ```
     * The layout for current page is "<?= $page->getLayout() ?>".
     * <?= $page->setLayout('another-layout')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     *
     * @see addHooks() for the code that attaches Wireframe hooks.
     */
    public function pageLayout(HookEvent $event) {
        if ($event->method == 'getLayout' || $event->method == 'layout' && !isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_layout;
        } else {
            $event->object->_wireframe_layout = $event->arguments[0] ?? '';
            $event->return = $event->object;
        }
    }

    /**
     * This method is used by Page::view(), Page::getView(), and Page::setView()
     *
     * Example use with combined getter/setter method:
     *
     * ```
     * The view for current page is "<?= $page->view() ?>".
     * <?= $page->view('json')->render() ?>
     * ```
     *
     * Example use with dedicated getter/setter methods:
     *
     * ```
     * The view for current page is "<?= $page->getView() ?>".
     * <?= $page->setView('json')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     *
     * @see addHooks() for the code that attaches Wireframe hooks.
     */
    public function pageView(HookEvent $event) {
        if ($event->method == 'getView' || $event->method == 'view' && !isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_view;
        } else {
            $event->object->_wireframe_view = $event->arguments[0] ?? '';
            $event->return = $event->object;
        }
    }

    /**
     * PHP magic getter method
     *
     * This is an alias for the get() method.
     *
     * @param string $key Name of the variable.
     * @return mixed Value of the variable, or null if it doesn't exist.
     */
    public function __get($key) {
        return $this->get($key);
    }

    /**
     * PHP magic setter method
     *
     * This is an alias for the set() method.
     *
     * @param string $key Name of the variable.
     * @param string $value Value for the variable.
     * @return Wireframe Self-reference.
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
     * @param string $key Name of property you want to retrieve.
     * @return mixed Property value, or null if requested property is unrecognized.
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
     * @param string $key Name of the variable.
     * @param string $value Value for the variable.
     * @return Wireframe Self-reference.
     *
     * @throws WireException if trying to set value to unrecognized property.
     * @throws WireException if trying to set invalid value to a property.
     */
    public function set($key, $value): Wireframe {

        // value is invalid until proven valid
        $invalid_value = true;

        switch ($key) {
            case 'data':
                if (is_array($value)) {
                    $invalid_value = false;
                    $this->$key = $value;
                }
                break;
            case 'page':
                if ($value instanceof Page && $value->id) {
                    $invalid_value = false;
                    $this->$key = $value;
                }
                break;
            case 'create_directories':
                // module config (saved values)
                $invalid_value = false;
                $this->$key = $value;
                break;
            case 'uninstall':
            case 'submit_save_module':
                // module config (skipped values)
                $invalid_value = false;
                break;
            default:
                throw new WireException(sprintf(
                    'Unable to set value for unrecognized property "%s"',
                    $key
                ));
        }

        // if value is invalid, throw an exception
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
     * @param array $values Values as an associative array.
     * @return Wireframe Self-reference.
     */
    public function setArray(array $values = []): Wireframe {
        if (!empty($values)) {
            $this->settings_hash = md5(serialize($values));
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
     * @param string $path Base directory.
     * @param string $ext File extension.
     * @return \stdClass An object containing list of files as its properties.
     */
    protected function getFilesRecursive(string $path, string $ext): \stdClass {
        $cache_key = 'files:' . $path . ':' . $ext;
        $files = $this->cache[$cache_key] ?? [];
        if (empty($files)) {
            foreach (glob($path) as $file) {
                $name = basename($file);
                if (strpos($name, ".") === 0) continue;
                if (is_dir($file)) {
                    $files[$name] = $this->getFilesRecursive("{$file}/*", $ext);
                } else if (strrpos($name, $ext) === strlen($name)-strlen($ext)) {
                    $files[substr($name, 0, strrpos($name, "."))] = $file;
                }
            }
            $files = (object) $files;
            $this->cache[$cache_key] = $files;
        }
        return $files;
    }

}
