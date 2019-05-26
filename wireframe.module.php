<?php

namespace ProcessWire;

/**
 * wireframe ProcessWire module
 *
 * Wireframe is an output framework with MVC inspired architecture for ProcessWire CMS/CMF.
 * See README.md or https://wireframe-framework.com for more details.
 * 
 * @version 0.0.11
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class wireframe extends WireData implements Module {

    /**
     * Placeholder for config settings
     *
     * @var object
     */
    protected $config;

    /**
     * Placeholder for paths
     *
     * @var object
     */
    protected $paths;

    /**
     * Placeholder for view script extension
     *
     * @var string
     */
    protected $ext;

    /**
     * Placeholder for Page
     *
     * @var Page
     */
    protected $page;

    /**
     * Placeholder for View
     *
     * @var \wireframe\View
     */
    protected $view;

    /**
     * Placeholder for Controller
     *
     * @var \wireframe\Controller
     */
    protected $controller;

    /**
     * Placeholder for View data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Initialize wireframe
     *
     * @param array $settings Array of additional settings (optional)
     * @return wireframe Self-reference
     * @throws WireException if no valid Page object is found
     */
    public function ___init(array $settings = []): wireframe {

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

        // add wireframe namespaces to ProcessWire's classLoader
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

        // choose a view script
        $this->initViewScript();

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
                'scripts' => $this->wire('config')->paths->templates . "views/scripts/",
                'layouts' => $this->wire('config')->paths->templates . "views/layouts/",
                'partials' => $this->wire('config')->paths->templates . "views/partials/",
                'controllers' => $this->wire('config')->paths->templates . "controllers/",
            ],
            'urls' => [
                'static' => $this->wire('config')->urls->templates . "static/",
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
     * Add wireframe namespaces to ProcessWire's classLoader
     *
     */
    protected function addNamespaces() {
        $namespaces = [
            'wireframe' => $this->wire('config')->paths->wireframe . 'lib/',
            'wireframe\controller' => $this->paths->controllers,
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
            $this->paths->views,
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
     * @return \wireframe\View View object
     * @throws WireException if no valid Page is provided
     */
    public function ___initView(): \wireframe\View {

        // params
        $page = $this->page;
        $paths = $this->paths;
        $ext = $this->ext;
        $data = $this->data;

        // initialize the View object
        $view = new \wireframe\View;
        $view->setLayout($page->layout() === null ? 'default' : $page->layout());
        $view->setScript($page->view());
        $view->setData($data);
        $view->setPartials($this->getFilesRecursive($paths->partials . "*", $ext));
        $view->setPlaceholders(new \wireframe\ViewPlaceholders($page, $paths->scripts, $ext));
        $this->view = $view;

        // define $view API variable
        $this->wire('view', $view);

        return $view;

    }

    /**
     * Initialization method for the Controller
     *
     * Controller is optional, but if a Controller file is found, we'll attempt to
     * instantiate an object from it.
     *
     * @return \wireframe\Controller|null Controller object or null
     * @throws WireException if no valid Page is provided
     */
    public function ___initController(): ?\wireframe\Controller {

        // params
        $page = $this->page;
        $view = $this->view;

        // define template name and Controller class name
        $controller = null;
        $controller_name = $this->wire('sanitizer')->pascalCase($page->template);
        $controller_class = '\wireframe\controller\\' . $controller_name . 'Controller';

        if (class_exists($controller_class)) {
            $controller = new $controller_class($this->wire(), $page, $view);
        }

        $this->controller = $controller;
        return $controller;

    }

    /**
     * Choose the view script to use
     *
     * Default value is 'default', but view() method of the $page object or GET param
     * 'view' can also be used to set the view script.
     */
    public function ___initViewScript() {

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
                // allowing *any* view script to be used via a GET param might not be
                // appropriate; using a whitelist allows us to configure valid values
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
        $view->setScript(basename($view->script ?: ($page->view() ?: ($get_view ?: 'default'))));
        if ($view->script != 'default' && !is_file($paths->scripts . $template . '/' . $view->script . $ext)) {
            $view->setScript('default');
        }
        if ($view->script != 'default' || is_file($paths->scripts . $template . '/' . $view->script . $ext)) {
            $view->setFilename($paths->scripts . $template . "/" . $view->script . $ext);
            if ($page->_wireframe_context != 'placeholder') {
                if ($view->script != 'default' && !$view->allow_cache) {
                    // not using the default view script, disable page cache
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
     * Note: this method can return null if view script and layout are unspecified.
     *
     * @param array $data Array of data to send to View (optional)
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
                // multiple otherwise separate templates and view scripts (DRY)
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
     * Helper method for getting or setting page view
     * 
     * Example: <?= $page->view('json')->render() ?>
     *
     * @param HookEvent $event
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
     * Helper method for getting or setting page layout
     * 
     * Example: <?= $page->layout('default')->render() ?>
     *
     * @param HookEvent $event
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
     * Alias for the get() method
     *
     * @param string $key Name of the variable
     * @return mixed Value of the variable, or null if it doesn't exist
     */
    public function __get($key) {
        return $this->get($key);
    }

    /**
     *
     *
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
     * Alias for the set() method
     *
     * @param string $key Name of the variable
     * @param string $value Value for the variable
     * @return wireframe Self-reference
     */
    public function __set($key, $value): wireframe {
        return $this->set($key, $value);
    }

    /**
     * General purpose setter method
     *
     * @param string $key Name of the variable
     * @param string $value Value for the variable
     * @return wireframe Self-reference
     * @throws WireException if trying to set value to unrecognized property
     * @throws WireException if trying to set invalid value to a property
     */
    public function set($key, $value): wireframe {
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
     * @param array $values
     * @return wireframe Self-reference
     */
    public function setArray(array $values = []): wireframe {
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
     * @param string $path Base directory
     * @param string $ext File extension
     * @return stdClass
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
