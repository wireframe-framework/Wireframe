<?php

namespace ProcessWire;

/**
 * Wireframe API
 *
 * This module provides a JSON API for accessing Wireframe's features.
 *
 * In order to enable the API, you need to call the "serve" method somewhere in your own code. Typically you'd put this
 * in a file that gets called for all (or at least all the applicable) requests, such as the Wireframe bootstrap file
 * (/site/templates/wireframe.php):
 *
 * ```
 * // serve API request using current path ($input->url)
 * $wire->modules->get('WireframeAPI')->serve();
 * ```
 *
 * After enabling the API, requests that match the configured root path of the API will be automatically handled by the
 * WireframeAPI module. Default root path is /wireframe-api/, so an example of a request that goes to the API would be
 * something along these lines:
 *
 * ```
 * https://www.yoursite.tld/wireframe-api/components/ComponentName/
 * ```
 *
 * Note, though, that by default all of the default endpoints ("components" etc.) are disabled, so the first step is to
 * enable one or more of these via module config, or by defining the `$config->wireframeAPI` array. It's also possible
 * to define the served path manually, and you can also pass an array of arguments to the endpoint:
 *
 * ```
 * $api = $wire->modules->get('WireframeAPI');
 * $api->serve('/wireframe-api/component/Card/', ['arg' => 'value']);
 * ```
 *
 * Note that for security reasons the API itself won't automatically convert GET params into arguments, but you can
 * do this manually if you want to. Or, alternatively, you could hook into the `WireframeAPI::prepareArgs()` method
 * and merge GET params with existing arguments, while also filtering them as you see fit:
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
 * Access control is out of the scope of this module. You can, though, hook into the `WireframeApi::checkAccess()`
 * method and perform your own access management this way. Just return boolean `false` and the API endpoint will
 * send an "Unauthorized" response instead of a regular API response.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframeAPI extends \ProcessWire\WireData implements Module, ConfigurableModule {

    /**
     * Root path name format
     *
     * @var string
     */
    const ROOT_PATH_FORMAT = '[-_.a-zA-Z0-9]*';

    /**
     * Available endpoints
     *
     * Note: default endpoints are populated in __construct().
     *
     * @var array
     */
    protected $available_endpoints;

    /**
     * API root path
     *
     * @var string
     */
    protected $root_path = '';

    /**
     * Enabled endpoints
     *
     * @var array
     */
    protected $enabled_endpoints = [];

    /**
     * Return format
     *
     * @var string 'json', 'markup', or 'both'.
     */
    protected $return_format = 'both';

    /**
     * Constructor
     */
    public function __construct() {

        // populate the default endpoints
        $this->available_endpoints = [
            'components' => 'componentsEndpoint',
            'pages' => 'pagesEndpoint',
            'partials' => 'partialsEndpoint',
        ];

        // populate the default config data
        $config = array_merge(
            $this->getConfigDefaults(),
            \is_array($this->wire('config')->wireframeAPI) ? $this->wire('config')->wireframeAPI : []
        );
        foreach ($config as $key => $value) {
            switch ($key) {
                case 'root_path':
                    $this->setRootPath($value);
                    break;

                case 'enabled_endpoints':
                    $this->setEnabledEndpoints($value);
                    break;

                default:
                    $this->$key = $value;
            }
        }
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

        // API root path
        /** @var InputfieldText */
        $field = $this->modules->get('InputfieldText');
        $field->name = 'root_path';
        $field->label = $this->_('API root path');
        $field->value = $data[$field->name];
        $field->pattern = self::ROOT_PATH_FORMAT;
        $field->notes = sprintf($this->_('Expected format: %s'), $field->pattern);
        if (isset($config[$field->name])) {
            $field->notes = $this->_('Root path is currently defined in site config. You cannot override site config settings here.');
            $field->value = $config[$field->name];
            $field->collapsed = Inputfield::collapsedNoLocked;
        }
        $fields->add($field);

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
     * Serve specified API endpoint
     *
     * @param string|null $path API path; leave null to use current URL.
     * @param array $args Optional array of arguments for the endpoint.
     *
     * @throws \Wireframe\APIException if no API endpoint was specified.
     * @throws \Wireframe\APIException if API endpoint is unknown/unavailable.
     */
    public function serve(?string $path = null, array $args = []): void {

        // define path
        if ($path === null) {
            $path = trim($this->wire('input')->url, '/');
        }

        // bail out early if this doesn't look like a real API request
        if (empty($path) || (!empty($this->root_path) && strpos($path, $this->root_path . '/') !== 0)) {
            return;
        }

        // instantiate response object
        $response = (new \Wireframe\APIResponse())
            ->setPath($path);

        // split path into parts and remove API root path if present
        $path = explode('/', trim($path, '/'));
        if (!empty($path) && $path[0] == $this->root_path) {
            array_shift($path);
        }

        try {

            // validate endpoint
            if (empty($path)) {
                throw (new \Wireframe\APIException('Missing API endpoint'))->setResponseCode(400);
            }
            $endpoint = array_shift($path);
            $endpoints = array_intersect(array_keys($this->available_endpoints), $this->enabled_endpoints);
            if (!in_array($endpoint, $endpoints)) {
                throw (new \Wireframe\APIException(sprintf(
                    'Invalid API endpoint (%s)',
                    $endpoint
                )))->setResponseCode(400);
            }

            // prepare arguments
            $args = $this->prepareArgs($endpoint, $path, $args);
            $response->setArgs($args);

            // check access
            if (!$this->checkAccess($endpoint, $path, $args)) {
                throw (new \Wireframe\APIException('Unauthorized'))
                    ->setResponseCode(401);
            }

            // call endpoint method
            $method = $this->available_endpoints[$endpoint];
            $data = \is_string($method) ? $this->$method($path, $args) : \call_user_func($method, $path, $args);
            $response->setData(\is_array($data) ? $data : [$data]);

        } catch (\Exception $e) {

            // handle exception
            $response
                ->setMessage($e->getMessage())
                ->setStatusCode($e instanceof \Wireframe\APIException ? $e->getResponseCode() : 500);

        }

        // output response
        header('Content-Type: application/json');
        http_response_code($response->getStatusCode());
        exit($response->render());
    }

    /**
     * Components endpoint
     *
     * Note that for the JSON output to return something, component must implement renderJSON() method.
     * While we could also pull data using getData(), this isn't necessarily expected behaviour, and/or
     * JSON output could require different set of data compared to HTML rendering.
     *
     * @param array $path
     * @param array $args
     * @return array
     *
     * @throws \Wireframe\APIException if no component name was specified.
     * @throws \Wireframe\APIException if unknown component was requested.
     */
    protected function componentsEndpoint(array $path, array $args = []): array {
        if (empty($path)) {
            throw (new \Wireframe\APIException('Missing component name'))
                ->setResponseCode(400);
        }
        $component_name = $path[0] ?? null;
        try {
            $component = \Wireframe\Factory::component($component_name, $args);
            $data = [];
            if ($this->return_format == 'json') {
                $data['json'] = json_decode($component->renderJSON());
            } else if ($this->return_format == 'markup') {
                $data['markup'] = $component->render();
            } else {
                $data = [
                    'json' => json_decode($component->renderJSON()),
                    'markup' => $component->render(),
                ];
            }
            return $data;
        } catch (\ProcessWire\WireException $e) {
            throw (new \Wireframe\APIException(sprintf(
                'Unknown component (%s)',
                $component_name
            )))->setResponseCode(400);
        }
    }

    /**
     * Pages endpoint
     *
     * Note: this method is a temporary placeholder.
     *
     * @param array $path
     * @param array $args
     * @return array
     */
    protected function pagesEndpoint(array $path, array $args = []): array {
        return [];
    }

    /**
     * Partials endpoint
     *
     * Note: this method is a temporary placeholder.
     *
     * @param array $path
     * @param array $args
     * @return array
     */
    protected function partialsEndpoint(array $path, array $args = []): array {
        return [];
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
            'root_path' => 'wireframe-api',
            'enabled_endpoints' => [],
        ];
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
     * Set API root path
     *
     * @param string
     * @return WireframeAPI Self-reference.
     *
     * @throws WireException if root path is invalid.
     */
    public function setRootPath(string $root_path): WireframeAPI {
        if (!preg_match('/' . self::ROOT_PATH_FORMAT . '/', $root_path)) {
            throw new WireException(sprintf(
                'Invalid root path (%s)',
                $root_path
            ));
        }
        $this->root_path = $root_path;
        return $this;
    }

    /**
     * Get API root path
     *
     * @return string
     */
    public function getRootPath(): string {
        return $this->root_path;
    }

    /**
     * Set return format
     *
     * @param string $format 'json', 'markup', or 'both'.
     * @return WireframeAPI Self-reference.
     */
    public function setReturnFormat(string $format): WireframeAPI {
        if (!in_array($format, ['both', 'json', 'markup'])) {
            throw new WireException(sprintf(
                'Invalid value provided for return format (%s)',
                $format
            ));
        }
        $this->return_format = $format;
        return $this;
    }

    /**
     * Get return format
     *
     * @return string
     */
    public function getReturnFormat(): string {
        return $this->return_format;
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
