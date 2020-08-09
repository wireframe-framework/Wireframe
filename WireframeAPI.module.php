<?php

namespace ProcessWire;

/**
 * Wireframe API
 *
 * This module provides a JSON API for accessing Wireframe's features.
 *
 * In order to enable the API, you need to call the "init" method somewhere in your own code and render the response.
 * To get started, you should create a new template that has URL segments enabled and is routed through the Wireframe
 * bootstrap file (via alternate template file setting). Finally insert this code snippet as the render method of the
 * controller class for your template, and the API should be good to go:
 *
 * ```
 * public function render() {
 *     echo $this->wire('modules')->get('WireframeAPI')->init()->sendHeaders()->render();
 *     $this->view->setLayout(null)->halt();
 * }
 * ```
 *
 * (Alternatively you could create a template that isn't routed through the Wireframe bootstrap file; this should work
 * fine in most cases, but obviously you won't have access to any of the settings etc. defined in the bootstrap file.)
 *
 * Requests for this page will now be served by the API. In order to get something useful out of the API, a request has
 * to match a recognized API endoint. Here's an example of a request for the "components" endpoint. Here wireframe-api
 * would be the name of the page you created for the API, and Card would be the name of one of your components:
 *
 * ```
 * https://www.yoursite.tld/wireframe-api/components/Card/
 * ```
 *
 * Note that by default all of the default endpoints ("components" etc.) are disabled, so actually the first step is to
 * enable one or more of these via module config or via the `$config->wireframeAPI` array.
 *
 * It's also possible to define the path manually, and you can pass an array of arguments for the endpoint:
 *
 * ```
 * $api->init('components/Card', ['arg' => 'value']);
 * ```
 *
 * For security reasons the API won't automatically convert GET params into arguments, but you can of course do exactly
 * that manually in the Wireframe element that handles the request (e.g. as the "Card" component class). Alternatively
 * you could hook into the `WireframeAPI::prepareArgs()` method and merge GET params with existing arguments, though
 * be sure to also filter them so that they can only contain useful/expected values:
 *
 * ```
 * $api->addHookAfter('prepareArgs', function(HookEvent $event) use ($input) {
 *     $event->return = array_merge($event->return, [
 *         $input->get->bool('some_variable'),
 *     ]);
 * });
 * ```
 *
 * In the example above you could allow *all* GET params by merging `$event->return` with `$input->get->getArray()`,
 * but keep in mind that allowing users to freely control which arguments they pass to your code can be potentially
 * dangerous.
 *
 * The return value of an API request is always JSON. Here's an example of what the returned data might look like:
 *
 * ```
 * {
 *     "success": true,
 *     "message": "",
 *     "path": "wireframe-api\/components\/Card",
 *     "args": {
 *         "exampleArg": false
 *     },
 *     "data": {
 *         "json": {
 *             "title": "Hello World",
 *         },
 *         "rendered": "<div class=\"card\"><h2>Hello World</h2></div>",
 *     }
 * }
 * ```
 *
 * In this case the "rendered" property of the "data" object contains whatever `Card::render()` returns, while "json"
 * contains whatever `Card::renderJSON()` returns. Note that in the case of components you need to implement this
 * method yourself or Wireframe API will only return `null` as the value of the "json" property.
 *
 * Here's an example of what an error returned by the API would look like:
 *
 * ```
 * {
 *     "success": false,
 *     "message": "Unknown component (Test)",
 *     "path": "wireframe-api\/components\/Test",
 *     "args": {
 *         "exampleArg": false
 *     },
 * }
 * ```
 *
 * Wireframe API attempts to return applicable HTTP status codes with each response, but the (boolean) "success" flag
 * is provided as well. "success" is `true` if HTTP status code is equal to or greater than 200 and smaller than 300.
 *
 * Access control is out of the scope of this module. You can, though, hook into the `WireframeAPI::checkAccess()`
 * method and perform your own access management this way. Just return boolean `false` and the API endpoint will
 * send an "Unauthorized" response instead of a regular API response.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframeAPI extends \ProcessWire\WireData implements Module, ConfigurableModule {

    /**
     * Available endpoints
     *
     * Note: default endpoints are populated in __construct().
     *
     * @var array
     */
    protected $available_endpoints;

    /**
     * Enabled endpoints
     *
     * @var array
     */
    protected $enabled_endpoints = [];

    /**
     * API response
     *
     * @var \Wireframe\APIResponse|null
     */
    protected $response = null;

    /**
     * Constructor
     */
    public function __construct() {

        // populate the default endpoints
        $this->available_endpoints = [
            'components' => 'components',
            'pages' => 'pages',
            'partials' => 'partials',
        ];

        // populate the default config data
        $config = array_merge(
            $this->getConfigDefaults(),
            \is_array($this->wire('config')->wireframeAPI) ? $this->wire('config')->wireframeAPI : []
        );
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'enabled_endpoints':
                    $this->setEnabledEndpoints($value);
                    break;

                default:
                    $this->$key = $value;
            }
        }
    }

    /**
     * Get default config settings
     *
     * If you need to customize or override any of the default config values, you can copy this array to your site
     * config file (/site/config.php) as $config->wireframeAPI.
     *
     * @return array Default config settings.
     */
    public function getConfigDefaults(): array {
        return [
            'enabled_endpoints' => [],
        ];
    }

    /**
     * Module configuration
     *
     * @param array $data
     * @return InputfieldWrapper
     */
    public function getModuleConfigInputfields(array $data): InputfieldWrapper {

        $fields = $this->wire(new InputfieldWrapper());

        // Merge data array with defaults.
        $data = array_merge($this->getConfigDefaults(), $data);

        // Configuration settings from site config
        $config = $this->wire('config')->wireframeAPI ?? [];

        // Enabled API endpoints
        /** @var InputfieldCheckboxes */
        $field = $this->modules->get('InputfieldCheckboxes');
        $field->name = 'enabled_endpoints';
        $field->label = $this->_('Enabled endpoints');
        $field->addOptions([
            'component' => $this->_('Component'),
        ]);
        $field->value = $data[$field->name];
        if (isset($config[$field->name])) {
            $field->notes = $this->_('Enabled endpoints are currently defined in site config. You cannot override site config settings here.');
            $field->value = $config[$field->name];
            $field->collapsed = Inputfield::collapsedNoLocked;
        }
        $fields->add($field);

        return $fields;
    }

    /**
     * Init API
     *
     * @param string|null $path API path; leave null to use current URL.
     * @param array $args Optional array of arguments for the endpoint.
     * @return WireframeAPI Self-reference.
     */
    public function init(?string $path = null, array $args = []): WireframeAPI {

        // define path
        if ($path === null) {
            $path = trim($this->wire('input')->url, '/');
        }

        // make sure that Wireframe is initialized
        if (!Wireframe::isInitialized()) {
            $this->wire('modules')->get('Wireframe')->init();
        }

        // instantiate a response object
        $this->response = (new \Wireframe\APIResponse())
            ->setPath($path);

        // if debug mode is enabled, improve response readability by enabling JSON pretty print
        if ($this->wire('config')->debug) {
            $this->response->setPretty(true);
        }

        // split path into parts and remove API root path if present
        if (!empty($path)) {
            $path = explode('/', trim($path, '/'));
            if (!empty($path) && $path[0] == $this->wire('page')->name) {
                array_shift($path);
            }
        }

        // bail out early if path is empty
        if (empty($path)) {
            $this->response->setData([
                'endpoints' => $this->enabled_endpoints,
            ]);
            return $this;
        }

        try {

            // validate endpoint and remove it from path
            $endpoint = $this->validateEndpoint($path);
            array_shift($path);

            // prepare arguments and store them in the response object
            $this->response->setArgs($this->prepareArgs($endpoint, $path, $args));

            // check access
            if (!$this->checkAccess($endpoint, $path, $this->response->getArgs())) {
                throw (new \Wireframe\APIException('Unauthorized'))
                    ->setResponseCode(401);
            }

            // call endpoint method
            $data = [];
            $method = $this->available_endpoints[$endpoint];
            if (\is_string($method)) {
                $data = (new \Wireframe\APIEndpoints())->$method($path, $this->response->getArgs());
            } else {
                $data = \call_user_func($method, $path, $this->response->getArgs());
            }
            $this->response->setData(\is_array($data) ? $data : [$data]);

        } catch (\Exception $e) {

            // handle exception
            $this->response
                ->setMessage($e->getMessage())
                ->setStatusCode($e instanceof \Wireframe\APIException ? $e->getResponseCode() : 500);

        }

        return $this;
    }

    /**
     * Validate endpoint
     *
     * @param array $path
     * @return string
     *
     * @throws \Wireframe\APIException if no API endpoint was specified (HTTP 400).
     * @throws \Wireframe\APIException if API endpoint is unknown/unavailable (HTTP 404).
     */
    protected function validateEndpoint(array $path): string {
        if (empty($path)) {
            throw (new \Wireframe\APIException('Missing API endpoint'))->setResponseCode(400);
        }
        $endpoint = $path[0];
        $endpoints = array_intersect(array_keys($this->available_endpoints), $this->enabled_endpoints);
        if (!in_array($endpoint, $endpoints)) {
            throw (new \Wireframe\APIException(sprintf(
                'Unknown API endpoint (%s)',
                $endpoint
            )))->setResponseCode(404);
        }
        return $endpoint;
    }

    /**
     * Get API response
     *
     * @return \Wireframe\APIResponse|null
     */
    public function getResponse(): ?\Wireframe\APIResponse {
        return $this->response;
    }

    /**
     * Send headers
     *
     * @return WireframeAPI Self-reference.
     */
    public function sendHeaders(): WireframeAPI {
        header('Content-Type: application/json');
        if ($this->response) {
            http_response_code($this->response->getStatusCode());
        }
        return $this;
    }

    /**
     * Render API response
     *
     * @return string
     */
    public function render(): string {
        if ($this->response) {
            return $this->response->render();
        }
        return '';
    }

    /**
     * Set enabled endpoints
     *
     * @param array
     * @return WireframeAPI Self-reference.
     */
    public function setEnabledEndpoints(array $endpoints): WireframeAPI {
        $this->enabled_endpoints = array_intersect(array_keys($this->available_endpoints), $endpoints);
        return $this;
    }

    /**
     * Get enabled endpoints
     *
     * @return array
     */
    public function getEnabledEndpoints(): array {
        return $this->enabled_endpoints;
    }

    /**
     * Enable single endpoint
     *
     * @param string $endpoint
     * @return WireframeAPI Self-reference.
     */
    public function enableEndpoint(string $endpoint): WireframeAPI {
        if (array_key_exists($endpoint, $this->available_endpoints) && !in_array($endpoint, $this->enabled_endpoints)) {
            $this->enabled_endpoints[] = $endpoint;
        }
        return $this;
    }

    /**
     * Disable single endpoint
     *
     * @param string $endpoint
     * @return WireframeAPI Self-reference.
     */
    public function disableEndpoint(string $endpoint): WireframeAPI {
        $key = array_search($endpoint, $this->enabled_endpoints);
        if ($key !== false) {
            unset($this->enabled_endpoints[$key]);
        }
        return $this;
    }

    /**
     * Add custom endpoint
     *
     * @param string $endpoint
     * @param callable $callable
     *
     * @throws WireException if specified endpoint already exists.
     */
    public function addEndpoint(string $endpoint, callable $callable) {
        if (array_key_exists($endpoint, $this->available_endpoints)) {
            throw new WireException(sprintf(
                'Unable to add endpoint: an endpoint with this name already exists (%s)',
                $endpoint
            ));
        }
        $this->available_endpoints[$endpoint] = $callable;
        $this->enableEndpoint($endpoint);
    }

    /**
     * Remove endpoint
     *
     * @param string $endpoint Endpoint name
     */
    public function removeEndpoint(string $endpoint) {
        $this->disableEndpoint($endpoint);
        unset($this->available_endpoints[$endpoint]);
    }

    /**
     * Check access to API
     *
     * You can hook into this method and provide your own access management logic.
     *
     * @param string $endpoint
     * @param array $path
     * @param array $args
     * @return bool
     */
    protected function ___checkAccess(string $endpoint, array $path, array $args = []): bool {
        return true;
    }

    /**
     * Prepare arguments
     *
     * You can hook into this method and provide your own argument handling logic.
     *
     * @param string $endpoint
     * @param array $path
     * @param array $args
     * @return array
     */
    protected function ___prepareArgs(string $endpoint, array $path, array $args = []): array {
        return $args;
    }

}
