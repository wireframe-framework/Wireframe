<?php

namespace ProcessWire;

/**
 * Wireframe ProcessWire module
 *
 * Wireframe is an output framework with MVC inspired architecture for ProcessWire CMS/CMF.
 * See README.md or https://wireframe-framework.com for more details.
 *
 * Methods provided by \Wireframe\Factory:
 *
 * @method static \Wireframe\Component component(string $component_name, array $args = []) Static getter (factory) method for Components.
 * @method static string|Page|NullPage page($source, $args = []) Static getter (factory) method for Pages.
 * @method static string|null partial(string $partial_name, array $args = []) Static getter (factory) method for Partials.
 *
 * @version 0.14.0
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

        // instantiate Wireframe Config and get all config inputfields
        $config = $this->wire(new \Wireframe\Config($this));
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
     * This information is stored in an array for it to work properly with multi-instance support; in such cases we
     * need to make sure that initOnce() is run once per ProcessWire instance.
     *
     * @var array
     */
    protected static $initialized = [];

    /**
     * Initialize Wireframe
     *
     * @param array $settings Array of additional settings (optional). Supported settings:
     *  - `page` (Page): current Page object
     *  - `paths` (array): custom paths for Wireframe objects; views, layouts, partials, components, etc.
     *  - `data` (array): variables for the View, does the same thing as passing an assoc array to the render method
     *  - `ext` (string|null): file extension for view, layout, and partial files; default value is pulled from site config
     *  - `renderer` (Module|string|null): name or instance of a Renderer module, or null for none
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

        // attach hooks
        $hooks = $this->wire(new \Wireframe\Hooks($this));
        $hooks->init();

        // remember that this method has been run and return true
        static::$initialized[] = $this->wire()->instanceID;

        // return true on first run
        return true;
    }

    /**
     * Check if Wireframe has already been initialized
     *
     * As long as $instanceID is provided, this method will work on a multi-instance ProcessWire setup. If $instanceID
     * is left out (null), we get it from the wire() function instead.
     *
     * @param null|int $instanceID ProcessWire instance ID. This parameter is optional but recommended.
     * @return bool True if initialized, false if not.
     */
    public static function isInitialized(int $instanceID = null): bool {
        return \in_array(
            $instanceID === null ? wire()->instanceID : $instanceID,
            static::$initialized
        );
    }

    /**
     * Define runtime config settings
     *
     * If runtime config settings already exist, those will be kept, with new values overwriting existing values with
     * the same name. In order to reset previously (manually) defined config values, provide default values for them.
     * You can get default config values by calling the getConfigDefaults() method.
     *
     * @param array $config Optional configuration settings array.
     * @return Wireframe Self-reference.
     */
    public function ___setConfig(array $config = []): Wireframe {

        // combine default config settings with custom ones
        $config_merged = array_merge(
            $this->getConfigDefaults(),
            \is_array($this->wire('config')->wireframe) ? $this->wire('config')->wireframe : [],
            $this->config,
            $config
        );

        // check if paths or include_paths have changed
        $set_paths = !isset($this->config['paths']) || $this->config['paths'] != $config_merged['paths'];
        $set_include_path = !isset($this->config['include_paths']) || $this->config['include_paths'] != $config_merged['include_paths'];

        // URL additions to global config settings
        foreach ($config_merged['urls'] as $key => $value) {
            $this->wire('config')->urls->set($key, $value);
        }

        // store config settings locally
        $this->config = $config_merged;

        // PHP include path additions
        if ($set_include_path) {
            $this->setIncludePath();
        }

        // set or update wireframe paths
        if ($set_paths) {
            $this->setPaths();
        }

        return $this;
    }

    /**
     * Getter for runtime config settings
     *
     * @return array Current config settings.
     */
    public function getConfig(): array {
        $this->initOnce();
        return $this->config;
    }

    /**
     * Getter for default config settings
     *
     * If you need to customize or override any of the default config values, you can copy this array to your site
     * config file (/site/config.php) as $config->wireframe, or call setConfig() with an array of override values.
     *
     * @return array Default config settings.
     */
    public function getConfigDefaults(): array {
        return [
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
    }

    /**
     * Set or update paths
     *
     * This method makes necessary site config additions/modifications and updates ProcessWire's class autoloader.
     *
     * Note: it's a bit of a border case, but it's probably worth noting that if this method has already been called
     * *and* you've instantiated a class found from previously set paths, you *can't* instantiate a different class
     * with exactly the same name from a new path. (PHP doesn't allow "uncaching" previously declared classes.)
     *
     * @param array $paths Paths array for overriding default values.
     * @return Wireframe Self-reference.
     */
    public function setPaths(array $paths = []): Wireframe {

        // if called with empty paths array, get paths from config; otherwise make sure that config paths are in sync
        // with values defined here (overwrite config values if they exist)
        if (empty($paths)) {
            $paths = $this->config['paths'];
        } else if (isset($this->config['paths'])) {
            $this->config['paths'] = array_merge(
                $this->config['paths'],
                $paths
            );
        }

        // if partials path is being defined, also set or update the partials path stored in global config settings
        if (isset($paths['partials'])) {
            $this->wire('config')->paths->set('partials', $paths['partials']);
        }

        // store paths locally as an object and add Wireframe namespaces to ProcessWire's class autoloader (namespaces
        // are dependent on the paths defined here, hence the tight coupling between these two methods)
        $this->paths = (object) $paths;
        $this->addNamespaces();

        return $this;
    }

    /**
     * Getter method for View paths
     *
     * @return array Paths array.
     */
    public function getViewPaths(): array {
        return $this->paths ? [
            'view' => $this->paths->views,
            'layout' => $this->paths->layouts,
            'partial' => $this->paths->partials,
            'component' => $this->paths->components,
        ] : [];
    }

    /**
     * Store template extension in a class property
     *
     * @param string|null $ext Extension string for overriding the default value.
     * @return Wireframe Self-reference.
     */
    public function setExt(string $ext = null): Wireframe {
        $this->ext = "." . ltrim($ext ?: $this->wire('config')->templateExtension, '.');
        // store ext in config for use in Factory::partial()
        $this->wire('config')->_wireframeTemplateExtension = $this->ext;
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
        if ($renderer === null) {
            $this->renderer = null;
            if ($this->view) $this->view->setRenderer(null);
        } else if (\is_string($renderer)) {
            $renderer = $this->wire('modules')->get($renderer);
            $needs_init = true;
        }
        if ($renderer instanceof Module) {
            if ($needs_init) $renderer->init($settings);
            $this->renderer = $renderer;
            $this->setExt($renderer->getExt());
            if ($this->view && $this->view->getRenderer() != $renderer) {
                $this->view->setRenderer($renderer);
            }
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

        // declare our namespaces
        $namespaces = [
            'Wireframe' => $this->wire('config')->paths->Wireframe . 'lib/',
            'Wireframe\Component' => $this->paths->components,
            'Wireframe\Controller' => $this->paths->controllers,
            'Wireframe\Lib' => $this->paths->lib,
        ];

        /** @var WireClassLoader ProcessWire's class autoloader */
        $classLoader = $this->wire('classLoader');

        // make class autoloader aware of our namespaces
        foreach ($namespaces as $namespace => $path) {

            // if namespaces have already been added, remove old ones first, just in case; this could, for an example,
            // happen if paths are changed manually after calling the init method
            if ($classLoader->hasNamespace($namespace)) {
                $classLoader->removeNamespace($namespace);
            }

            // add new namespace to class autoloader
            $classLoader->addNamespace($namespace, $path);
        }
    }

    /**
     * Set PHP include path
     */
    protected function setIncludePath() {

        // add templates directory to the include path by default
        $new_include_paths = [
            $this->wire('config')->paths->templates,
        ];

        // config settings may contain additional include paths
        if (!empty($this->config['include_paths'])) {
            $new_include_paths = array_merge(
                $new_include_paths,
                $this->config['include_paths']
            );
        }

        // validate include path(s), removing any duplicate values (new ones, as well as those that have already been
        // added to the PHP include path)
        $current_include_path = get_include_path();
        $current_include_path_parts = empty($current_include_path) ? [] : explode(PATH_SEPARATOR, $current_include_path);
        $new_include_paths = array_unique(array_filter($new_include_paths, function($path) use ($current_include_path_parts) {
            return !empty($path) && !in_array($path, $current_include_path_parts);
        }));

        // modify PHP include path if valid paths remain
        if (!empty($new_include_paths)) {
            set_include_path(
                $current_include_path .
                PATH_SEPARATOR .
                implode(PATH_SEPARATOR, $new_include_paths)
            );
        }
    }

    /**
     * Check if a redirect should occur
     *
     * Look for redirect fields within config settings. If present, check if the page has a value in one of those and
     * if a redirect should be performed.
     */
    protected function ___checkRedirects() {

        // redirect fields from Wireframe runtime configuration
        $redirect_fields = $this->config['redirect_fields'] ?? null;
        if (empty($redirect_fields)) return;

        // current Page object
        $page = $this->page;

        foreach ($redirect_fields as $field => $options) {

            // redirect_fields may be an indexed array
            if (\is_int($field) && \is_string($options)) {
                $field = $options;
            }

            // get URL from a page field
            $url = $page->get($field);
            if ($url instanceof WireArray) {
                $url = $url->first();
            }
            if (empty($url)) continue;

            // default to non-permanent redirect (302)
            $permanent = false;

            // if options is an array, read contained settings
            if (\is_array($options)) {
                if (!empty($options['property']) && \is_object($url)) {
                    $url = $url->get($options['property']);
                }
                if (!empty($options['permanent'])) {
                    $permanent = (bool) $options['permanent'];
                }
            }

            // if target URL is valid and doesn't belong to current page, perform a redirect
            if (\is_string($url) && $url != $page->url && $this->wire('sanitizer')->url($url)) {
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
    protected function ___redirect(string $url, bool $permanent) {
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
    protected function ___initView(): \Wireframe\View {

        // params
        $page_layout = $this->page->getLayout();

        // initialize the View object (note: view file is set in the Wireframe::___setView() method)
        $view = $this->wire(new \Wireframe\View);
        $view->setLayout($page_layout === null ? 'default' : $page_layout);
        $view->setTemplate($this->page->getViewTemplate());
        $view->setViewsPath($this->paths->views);
        $view->setLayoutsPath($this->paths->layouts);
        $view->setExt($this->ext);
        $view->setPage($this->page);
        $view->setData($this->data);
        $view->setPartials($this->findPartials($this->paths->partials));
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
     */
    protected function ___initController(): ?\Wireframe\Controller {

        // get a new Controller instance and store it both locally, as a run-time property of current Page object, and
        // as a reference within the View object
        $this->controller = $this->getController($this->page);
        $this->page->_wireframe_controller = $this->controller;
        $this->view->setController($this->controller);

        return $this->controller;
    }

    /**
     * Set current view
     *
     * Default value is 'default', but the setView() method of the $page object or GET param 'view' (if configured so)
     * can be used to override the default value.
     *
     * @param string|null $view Optional view name, leave blank to let Wireframe figure it out automatically.
     * @return Wireframe Self-reference.
     */
    public function ___setView(?string $view = null): Wireframe {

        // first check if a predefined view name was provided
        if ($view !== null) {
            $view = basename($view);
            $this->view->setView($view);
            $this->page->setView($view);
            return $this;
        }

        // priority for different sources: 1) View object, 2) Page object, 3) GET param, 4) "default"
        $this->view->setView(basename($this->view->getView() ?: ($this->page->getView() ?: ($this->getViewFromInput() ?: 'default'))));

        return $this;
    }

    /**
     * Get view name from input params (GET)
     *
     * @return string|null
     */
    protected function getViewFromInput(): ?string {

        // bail out early if providing view name as a GET param is not allowed
        if (!$this->config['allow_get_view']) {
            return null;
        }

        // attempt to get view name from GET param 'view'
        $input = $this->wire('input');
        $get_view = $input->get->view;
        if (!empty($get_view) && \is_array($this->config['allow_get_view'])) {
            // allowing *any* view to be accessed via a GET param might not be appropriate; using an allow list lets us
            // define the specific values that are considered safe
            $get_view = null;
            $template = $this->view->getTemplate() ?: $this->page->getViewTemplate();
            foreach ($this->config['allow_get_view'] as $get_template => $get_value) {
                if (\is_string($get_template) && \is_array($get_value) && $template == $get_template) {
                    $get_view = \in_array($input->get->view, $get_value) ? $input->get->view : null;
                    break;
                } else if (\is_int($get_template) && \is_string($get_value) && $input->get->view == $get_value) {
                    $get_view = $get_value;
                    break;
                }
            }
        }

        return $get_view;
    }

    /**
     * Set current view template
     *
     * View template is primarily used for figuring out where the view files for current page should come from. This is
     * useful in case you want to use the exact same view files for pages using different (real) templates.
     *
     * Note: this method should be used if you need to override view template in the Wireframe bootstrap file.
     *
     * @param string|null $template Template name.
     * @return Wireframe Self-reference.
     */
    public function setViewTemplate(?string $template): Wireframe {
        if ($this->page) {
            $this->page->setViewTemplate($template);
        }
        if ($this->view) {
            $this->view->setTemplate($template);
            $this->view->setView($this->view->getView());
        }
        return $this;
    }

    /**
     * Set current controller
     *
     * Note: this method should be used if you need to override controller in the Wireframe bootstrap file.
     *
     * @param string|null $template Template name.
     * @return Wireframe Self-reference.
     */
    public function setController(?string $template): Wireframe {
        $this->controller = $this->getController($this->page, $template ?? '');
        if ($this->page) {
            $this->page->setController($this->controller);
        }
        if ($this->view) {
            $this->view->setController($this->controller);
        }
        return $this;
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
        $controller = $view->getController() ?: $this->getController($this->page);

        // attempt to return prerendered value from cache
        $cache_key = implode(':', [
            'render',
            $this->page->id,
            $this->settings_hash,
            empty($data) ? '' : md5(json_encode($data)),
            $view->getTemplate(),
            $controller === null ? '' : \get_class($controller),
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
        if (!empty($controller)) {
            $controller->render();
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
                // layouts make it possible to define a common base structure for multiple otherwise separate template
                // and view files (DRY)
                $layout_filename = $paths->layouts . $filename . $ext;
                if ($ext != '.php' && !\is_file($layout_filename)) {
                    // layout filename not found and using custom file extension; try with the default extension (.php)
                    $layout_filename = $paths->layouts . $filename . '.php';
                }
                if (\is_file($layout_filename)) {
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
                if (\is_array($value)) {
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

            case 'paths':
                if (\is_array($value)) {
                    $invalid_value = false;
                    $this->setPaths($value);
                }
                break;

            case 'create_directories':
                // module config (saved values)
                $invalid_value = false;
                $this->$key = $value;
                break;

            case 'renderer':
                // renderer module
                if (\is_array($value) && !empty($value)) {
                    $invalid_value = false;
                    $this->setRenderer($value[0], $value[1] ?? []);
                } else if ($value === null || \is_string($value) || $value instanceof Module) {
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
     * This method is a wrapper for the set() method that supports multiple values as an associative array and
     * automatically generates a hash of the settings array (for caching purposes).
     *
     * @param array $values Values as an associative array.
     * @return Wireframe Self-reference.
     */
    public function setArray(array $values): Wireframe {
        if (!empty($values)) {
            $this->settings_hash = md5(serialize($values));
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        }
        return $this;
    }

    /**
     * Call static methods from other Wireframe classes
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public static function __callStatic($method, $arguments) {
        switch ($method) {
            case 'component':
            case 'page':
            case 'partial':
                if (!class_exists('\Wireframe\Factory')) {
                    include_once __DIR__ . '/lib/Factory.php';
                }
                return \call_user_func_array(['\Wireframe\Factory', $method], $arguments);
                break;
        }
    }

    /**
     * Fetch a list of Partials recursively
     *
     * This is a helper method used for fetching a list of files and folders recursively and returning the result as a
     * Partials object containing Partial objects and/or other (nested) Partials objects.
     *
     * @param string $path Base directory.
     * @param string|null $ext File extension (optional).
     * @param bool $use_cache Use cache? Defaults to `true`.
     * @return \Wireframe\Partials A container populated with Partials.
     */
    protected function findPartials(string $path, string $ext = null, bool $use_cache = true): \Wireframe\Partials {
        $files = [];
        if ($use_cache) {
            $cache_key = 'partials:' . $path . ':' . $ext;
            $files = $this->cache[$cache_key] ?? [];
        }
        if (!$use_cache || empty($files)) {
            foreach (\glob($path . '*') as $file) {
                $name = \basename($file);
                if (\strpos($name, ".") === 0) continue;
                if (\is_dir($file)) {
                    $files[$name] = $this->findPartials("{$file}/", $ext, false);
                } else {
                    $file_data = [];
                    $ext_pos = \strrpos($name, '.');
                    if ($ext === null) {
                        if ($ext_pos !== false) {
                            $temp_ext = \ltrim(\substr($name, $ext_pos), '.');
                            $file_data[$temp_ext] = $file;
                        }
                    } else {
                        $file_data[$ext] = $file;
                    }
                    $files_key = \substr($name, 0, $ext_pos);
                    $files[$files_key] = empty($files[$files_key]) ? $file_data : array_merge(
                        $files[$files_key],
                        $file_data
                    );
                }
            }
            if (\is_array($files)) {
                $temp_files = $files;
                $files = new \Wireframe\Partials();
                $files->setPath($path);
                foreach ($temp_files as $key => $file) {
                    $files->{$key} = \is_object($file) ? $file : $this->wire(new \Wireframe\Partial($file));
                }
            }
            if ($use_cache) {
                $this->cache[$cache_key] = $files;
            }
        }
        return $this->wire($files);
    }

    /**
     * Get controller object
     *
     * - If a Page object is provided as the first argument, this method returns a Controller for that Page.
     * - If a Page object is provided as the first argument and a template name as the second one, this method will
     *   attempt to instantiate and return a Controller for provided Page and template name.
     * - If no Page is provided, this method returns the controller property of current Wireframe module instance.
     *
     * @param Page|null $page
     * @param string|null $template_name
     * @return \Wireframe\Controller|null
     */
    public function getController(?Page $page = null, ?string $template_name = null): ?\Wireframe\Controller {

        // if no page was specified, return the local controller property
        if ($page === null) {
            return $this->controller;
        }

        // if no template name was provided, return the controller property from the body, or (if that is not set)
        // use the template name of the page for the next step
        if ($template_name === null) {
            if (isset($page->_wireframe_controller)) {
                return $page->_wireframe_controller == '' ? null : $page->_wireframe_controller;
            }
            $template_name = $page->template->name;
        }

        // attempt to instantiate and return a controller class based on the template name
        $controller_name = $this->wire('sanitizer')->pascalCase($template_name);
        $controller_class = '\Wireframe\Controller\\' . $controller_name . 'Controller';
        if (class_exists($controller_class)) {
            return $this->wire(new $controller_class($page, $this->view));
        }
        return null;
    }

}
