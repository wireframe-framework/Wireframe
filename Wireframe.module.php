<?php

namespace ProcessWire;

/**
 * Wireframe ProcessWire module
 *
 * Wireframe is an output framework with MVC inspired architecture for ProcessWire CMS/CMF.
 * See README.md or https://wireframe-framework.com for more details.
 *
 * @version 0.9.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Wireframe extends WireData implements Module, ConfigurableModule {

    /**
     * Config settings
     *
     * @var array
     */
    protected $config = [];

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
     * Renderer object
     *
     * @var null|Module
     */
    protected $renderer;

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
     * Used by the module configuration screen. Contains an array of directories that should be automatically created.
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
     * This information is stored in an array for it to work properly with multi-instance support;
     * in such cases we need to make sure that initOnce() is run once per ProcessWire instance.
     *
     * @var array
     */
    protected static $initialized = [];

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

        // check for redirects
        $this->checkRedirects();

        // store template extension locally
        if (!$this->ext) $this->setExt();

        // initialize View and Controller
        $this->initView();
        $this->initController();

        // choose the view to use
        $this->setView();

        return $this;
    }

    /**
     * This method performs init tasks that should only run once
     *
     * @return bool True on first run, false if already initialized.
     */
    public function initOnce(): bool {

        // bail out early if already initialized
        if (static::isInitialized($this->wire()->instanceID)) return false;

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
        static::$initialized[] = $this->wire()->instanceID;

        // return true on first run
        return true;
    }

    /**
     * Check if Wireframe has already been initialized
     *
     * As long as $instanceID is provided, this method will work on a multi-instance ProcessWire
     * setup. If $instanceID is left out (null), we get it from the wire() function instead.
     *
     * @param null|int $instanceID ProcessWire instance ID. This parameter is optional but recommended.
     * @return bool True if initialized, false if not.
     */
    public static function isInitialized(int $instanceID = null): bool {
        return in_array(
            is_null($instanceID) ? wire()->instanceID : $instanceID,
            static::$initialized
        );
    }

    /**
     * Define runtime config settings
     *
     * @param array $config Optional configuration settings array.
     * @return Wireframe Self-reference.
     */
    public function ___setConfig(array $config = []): Wireframe {

        // default config settings; if you need to customize or override any of these, copy this array to
        // your site config file (/site/config.php) as $config->wireframe
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
                'components' => $this->wire('config')->paths->templates . "components/",
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
     * Getter for runtime config settings
     *
     * @return array Config settings.
     */
    public function getConfig(): array {
        return $this->config;
    }

    /**
     * Store paths in a class property
     *
     * @param array $paths Paths array for overriding the default value.
     * @return Wireframe Self-reference.
     */
    public function setPaths(array $paths = []): Wireframe {
        if (empty($paths)) $paths = $this->config['paths'];
        $this->paths = (object) $paths;
        return $this;
    }

    /**
     * Getter method for View related paths
     *
     * @return array|null Paths array or null.
     */
    public function getViewPaths(): ?array {
        return $this->paths ? [
            'view' => $this->paths->views,
            'layout' => $this->paths->layouts,
            'partial' => $this->paths->partials,
            'component' => $this->paths->components,
        ] : null;
    }

    /**
     * Store template extension in a class property
     *
     * @param string|null $ext Extension string for overriding the default value.
     * @return Wireframe Self-reference.
     */
    public function setExt(string $ext = null): Wireframe {
        $this->ext = "." . ltrim($ext ?: $this->wire('config')->templateExtension, '.');
        if ($this->view) {
            $this->view->setExt($this->ext);
            $this->view->setView($this->view->getView());
        }
        return $this;
    }

    /**
     * Store renderer object in a class property and update View renderer
     *
     * @param Module|string|null $renderer Renderer module, name of a renderer module, or null to unset.
     * @param array $settings Optional array of settings for the renderer module.
     * @return Wireframe Self-reference.
     */
    public function setRenderer($renderer, array $settings = []): Wireframe {
        $needs_init = !empty($settings);
        if (is_null($renderer)) {
            $this->renderer = null;
            if ($this->view) $this->view->setRenderer(null);
        } else if (is_string($renderer)) {
            $renderer = $this->wire('modules')->get($renderer);
            $needs_init = true;
        }
        if ($renderer instanceof Module) {
            if ($needs_init) $renderer->init($settings);
            $this->renderer = $renderer;
            $this->setExt($renderer->getExt());
            if ($this->view) $this->view->setRenderer($renderer);
        }
        return $this;
    }

    /**
     * Add Wireframe namespaces to ProcessWire's class autoloader
     *
     * This method makes ProcessWire's class autoloader aware of the Wireframe namespaces, which enables us to
     * instantiate – or call static methods from – Wireframe objects without first requiring the related PHP file.
     *
     * If you want to add additional namespaces (or additional paths for the namespaces added here), you can access
     * the $classLoader API variable directly via your own code. If you want to override namespaces added here, you
     * should call $classLoader->removeNamespace($namespace, $path) before re-adding the namespace with a new path.
     */
    protected function addNamespaces() {
        $namespaces = [
            'Wireframe' => $this->wire('config')->paths->Wireframe . 'lib/',
            'Wireframe\Component' => $this->paths->components,
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
     *
     * @todo Page::getViewTemplate + Page::setViewTemplate
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
     * Look for redirect fields within config settings. If present, check if the page has a value in one of those and
     * if a redirect should be performed.
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
            if ($url instanceof WireArray) {
                $url = $url->count() ? $url->first() : null;
            }
            if (empty($url)) continue;

            // default to non-permanent redirect (302)
            $permanent = false;

            // if options is an array, read contained settings
            if (is_array($options)) {
                if (!empty($options['property']) && is_object($url)) {
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

        // initialize the View object (note: not setting view file yet at this point, that's a task
        // for the Wireframe::___setView() method)
        $view = new \Wireframe\View;
        $view->setLayout($page->getLayout() === null ? 'default' : $page->getLayout());
        $view->setTemplate($page->template);
        $view->setViewsPath($paths->views);
        $view->setLayoutsPath($paths->layouts);
        $view->setExt($ext);
        $view->setPage($this->page);
        $view->setData($data);
        $view->setPartials($this->getFilesRecursive($paths->partials . "*", $ext));
        $view->setPlaceholders(new \Wireframe\ViewPlaceholders($view));
        $view->setRenderer($this->renderer);
        $this->view = $view;

        // define the $view API variable
        $this->wire('view', $view);

        return $view;
    }

    /**
     * Initialization method for the Controller
     *
     * Controller is optional component in Wireframe, but if a Controller file is found, we'll attempt to instantiate
     * an object from it.
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
     * Default value is 'default', but the setView() method of the $page object or GET param 'view' (if configured so)
     * can be used to override the default value.
     *
     * @todo Page::getViewTemplate()
     */
    public function ___setView() {

        // params
        $config = $this->config;
        $page = $this->page;
        $view = $this->view;
        $template = $view->getTemplate() ?: $page->template;

        // $input API variable
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

        // priority for different sources: 1) View object, 2) Page object, 3) GET param, 4) "default".
        $view->setView(basename($view->getView() ?: ($page->getView() ?: ($get_view ?: 'default'))));
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
            $view->getTemplate(),
            $view->getFilename(),
            $view->getLayout(),
            $view->getExt(),
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

        // execute optional Controller::render()
        if (!empty($this->controller)) {
            $this->controller->render();
        }

        // render output
        $output = null;
        $filename = $view->getFilename();
        if ($filename || $view->getLayout()) {
            if ($filename) {
                $view->setContext('view');
                $output = $view->render();
            }
            if ($filename = basename($view->getLayout())) {
                // layouts make it possible to define a common base structure for
                // multiple otherwise separate template and view files (DRY)
                $layout_filename = $paths->layouts . $filename . $ext;
                if ($ext != '.php' && !is_file($layout_filename)) {
                    // layout filename not found and using custom file extension,
                    // try with the default extension (.php) instead
                    $layout_filename = $paths->layouts . $filename . '.php';
                }
                if (is_file($layout_filename)) {
                    $view->setContext('layout');
                    $view->setFilename($layout_filename);
                    if (!$view->placeholders->has('default')) {
                        $view->placeholders->default = $output;
                    }
                    $output = $view->render();
                }
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
            $event->object = Wireframe::page($event->object, [
                'wireframe' => $this,
            ]);
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
     * Note that this differs notably from the parent class' get() method: unlike in WireData, here we limit the scope
     * of the method to specific, predefined class properties instead of returning any index from the "data" array. We
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
            case 'renderer':
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
            case 'renderer':
                // renderer module
                if (is_array($value) && !empty($value)) {
                    $invalid_value = false;
                    $this->setRenderer($value[0], $value[1] ?? []);
                } else if (is_null($value) || is_string($value) || $value instanceof Module) {
                    $invalid_value = false;
                    $this->setRenderer($value);
                }
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
     * This method is a wrapper for the set() method, with support for multiple values as an associative array.
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
     * This is a helper method used for fetching a list of files and folders recursively and returning the result as an
     * object. Originally added for storing partial file references in an easy to access way.
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

    /**
     * Static getter (factory) method for Components
     *
     * Note: keep in mind that due to file system differences and the use of an autoloader, the name of the component
     * should *always* be treated as case sensitive. If actual class name is `Card` and the name is provided for this
     * method as `card`, this will fail in some environments, resulting in an exception.
     *
     * @param string $component_name Component name.
     * @param array $args Arguments for the Component.
     * @return \Wireframe\Component Instance of the Component.
     *
     * @since 0.8.0
     *
     * @throws WireException if Component class isn't found.
     */
    public static function component(string $component_name, array $args = []): \Wireframe\Component {

        $component = null;
        $component_class = '\Wireframe\Component\\' . $component_name;

        if (class_exists($component_class)) {
            $reflector = new \ReflectionClass($component_class);
            $component = $reflector->newInstanceArgs($args);
        } else {
            throw new WireException(sprintf(
                'Component class %s was not found.',
                $component_class
            ));
        }

        return $component;
    }

    /**
     * Static getter (factory) method for Pages
     *
     * This utility method can be used to render Page objects via Wireframe even if they don't have the expected
     * altFilename Template setting in place. Particularly useful for cases where you don't want a page to be publicly
     * viewable, but you still want to render it manually on some occasion (e.g. content that is only shown in lists.)
     *
     * Note that this method will accept different types of parameters, and the return value also depends on provided
     * parameters. Basic usage:
     *
     * ```
     * <?= Wireframe::page('id=1234', ['layout' => null, 'view' => 'list-item'])->render() ?>
     * ```
     *
     * Or a shorter version with string provided for args:
     *
     * ```
     * <?= Wireframe::page('id=1234', 'list-item') ?>
     * ```
     *
     * @param int|string|Page $source Page object, Page ID (integer), or selector string (string).
     * @param array|string $args Optional arguments. If the value is a string, it is assumed to be the name of a view
     *                           file and the default value for layout is set to `null` – except if the string contains
     *                           a forward slash in it, in which case it is assumed to hold both layout and view file
     *                           names ([layout]/[view]). If the value is an array, following options are supported:
     *                           - parent [Page]: the page on/for which current page is being rendered
     *                           - wireframe [Wireframe]: an instance of the Wireframe module
     *                           - wire [ProcessWire]: an instance of ProcessWire, defaults to Page's Wire instance if
     *                             $source is a Page object, or the Wire instance returned by wire() method if not.
     *                           - filename [string]: template file, defaults to 'wireframe'
     *                           - ext [string]: extension for the template file, defaults to '.php'
     *                           - layout [string]: layout to render the page with, defaults to 'default'
     *                           - view [string]: view file to render the page with, defaults to 'default'
     *                           - render [bool]: defines if we should return rendered content, defaults to 'false'
     * @return string|Page|NullPage Returns string if 'render' option was 'true' **or** the args param was a string,
     *                              otherwise returns a Page, or NullPage (if page wasn't found).
     *
     * @since 0.8.0
     *
     * @throws WireException if source param is of an unexpected type.
     * @throws WireException if args param is of an unexpected type.
     */
    public static function page($source, $args = []) {

        // ProcessWire instance
        $wire = $args['wire'] ?? ($source instanceof Page ? $source->getWire() : wire());

        // get a page
        $page = null;
        if ($source instanceof Page) {
            $page = $source;
        } else if (is_int($source) || is_string($source)) {
            $page = $wire->pages->get($source);
        } else {
            throw new WireException(sprintf(
                'Invalid argument type supplied for param source (%s)',
                gettype($source) . (is_object($source) ? ' ' . get_class($source) : '')
            ));
        }

        // bail out early if page wasn't found
        if ($page instanceof NullPage) return $page;

        // parse arguments and merge with defaults
        if (is_string($args)) {
            $args = [
                'layout' => null,
                'view' => $args,
                'render' => true,
            ];
            if (strpos($args['view'], '/') !== false) {
                $args_parts = explode('/', $args['view']);
                $args = [
                    'layout' => $args_parts[0],
                    'view' => $args_parts[1],
                    'render' => true,
                ];
            }
        }
        if (is_array($args)) {
            $args = array_merge([
                'parent' => null,
                'wireframe' => null,
                'wire' => $wire,
                'filename' => null,
                'ext' => '.php',
                'layout' => 'default',
                'view' => 'default',
                'render' => false,
            ], $args);
        } else {
            throw new WireException(sprintf(
                'Invalid argument type supplied for param args (%s)',
                gettype($args) . (is_object($args) ? ' ' . get_class($args) : '')
            ));
        }

        // make sure that the page gets rendered with Wireframe
        $page->_wireframe_filename = $args['filename'] ?? 'wireframe';
        if (empty($args['filename']) && !empty($args['parent'])) {
            $page->_wireframe_filename = $args['parent']->template->altFilename;
        }
        if (empty($page->template->altFilename) || $page->template->altFilename != $page->_wireframe_filename) {
            $page->addHookBefore('render', function(HookEvent $event) use ($args) {
                if (!empty($event->object->_wireframe_filename) && empty($event->object->template->altFilename)) {
                    $options = $event->arguments[0] ?? [];
                    if (empty($options['filename'])) {
                        $options['filename'] = $event->object->_wireframe_filename . $args['ext'];
                        $event->arguments(0, $options);
                    }
                }
            });
            if (!empty($args['wireframe']) && !empty($args['parent'])) {
                $page->addHookAfter('render', function(HookEvent $event) use ($args) {
                    if (!empty($event->object->_wireframe_page)) {
                        $args['wireframe']->page = $args['parent'];
                    }
                });
            }
        }

        // make sure that basic Wireframe features have been intiialized
        if (!Wireframe::isInitialized($wire->instanceID)) {
           ($args['wireframe'] ?? $wire->modules->get('Wireframe'))->initOnce();
        }

        // set view and layout
        if ($args['layout'] != 'default') $page->setLayout($args['layout']);
        if ($args['view'] != 'default') $page->setView($args['view']);

        return $args['render'] ? $page->render() : $page;
    }

}
