<?php

namespace ProcessWire;

/**
 * Wireframe API
 *
 * This module provides a JSON API for accessing Wireframe's features. For more details check out the documentation at
 * https://wireframe-framework.com/docs/wireframe-api/.
 *
 * @version 0.2.0
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
     * API root path
     *
     * This value is used by the Wireframe API Hooks module to provide automatic API endpoint.
     * Note that this endpoint is only intended for Tracy Debugger Wireframe panel and requires
     * superuser access.
     *
     * @var string|null
     */
    protected $api_root = null;

    /**
     * Constructor
     *
     * @throws WireException if config settings contain unrecognized properties.
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

                case 'api_root':
                    $this->api_root = $value;
                    break;

                default:
                    throw new WireException(sprintf(
                        'Unable to set value for unrecognized property "%s"',
                        $key
                    ));
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
            'api_root' => '',
        ];
    }

    /**
     * Set config data
     *
     * @param array $data
     */
    public function setConfigData(array $data) {
        if (isset($data['enabled_endpoints'])) {
            $this->setEnabledEndpoints($data['enabled_endpoints']);
        }
        if (isset($data['api_root'])) {
            $this->api_root = '/' . trim($data['api_root'], '/') . '/';
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

        // Enabled API endpoints
        /** @var InputfieldCheckboxes */
        $field = $this->modules->get('InputfieldCheckboxes');
        $field->name = 'enabled_endpoints';
        $field->label = $this->_('Enabled endpoints');
        $field->addOptions([
            'components' => $this->_('Components'),
            'pages' => $this->_('Pages'),
            'partials' => $this->_('Partials'),
        ]);
        $field->value = $data[$field->name];
        if (isset($config[$field->name])) {
            $field->notes = $this->_('Enabled endpoints are currently defined in site config. You cannot override site config settings here.');
            $field->value = $config[$field->name];
            $field->collapsed = Inputfield::collapsedNoLocked;
        }
        $fields->add($field);

        // API root
        /** @var InputfieldText */
        $field = $this->modules->get('InputfieldText');
        $field->name = 'api_root';
        $field->label = $this->_('API root path');
        $field->description = $this->_('This setting is primarily for the Tracy Wireframe panel API debugger. Accessing the path configured here requires the Wireframe API Hooks module to be intalled, and current user needs to have superuser access.');
        $field->value = $data[$field->name];
        if (isset($config[$field->name])) {
            $field->notes = $this->_('API root path is defined in site config. You cannot override site config settings here.');
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
    public function ___init(?string $path = null, array $args = []): WireframeAPI {

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
            if (\is_string($method) && strpos($method, '::') === false) {
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
        if (!\in_array($endpoint, $endpoints)) {
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
     * @param array $endpoints
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
     * Get Debugger API root path
     *
     * @return null|string
     */
    public function getAPIRoot(): ?string {
        return $this->api_root;
    }

    /**
     * Enable single endpoint
     *
     * @param string $endpoint
     * @return WireframeAPI Self-reference.
     */
    public function enableEndpoint(string $endpoint): WireframeAPI {
        if (array_key_exists($endpoint, $this->available_endpoints) && !\in_array($endpoint, $this->enabled_endpoints)) {
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
     * Add and enable a custom endpoint
     *
     * @param string $endpoint
     * @param callable $callable
     * @return WireframeAPI Self-reference.
     *
     * @throws WireException if specified endpoint already exists.
     */
    public function addEndpoint(string $endpoint, callable $callable): WireframeAPI {
        if (array_key_exists($endpoint, $this->available_endpoints)) {
            throw new WireException(sprintf(
                'Unable to add endpoint: an endpoint with this name already exists (%s)',
                $endpoint
            ));
        }
        $this->available_endpoints[$endpoint] = $callable;
        $this->enableEndpoint($endpoint);
        return $this;
    }

    /**
     * Disable and remove an endpoint
     *
     * @param string $endpoint Endpoint name
     * @return WireframeAPI Self-reference.
     */
    public function removeEndpoint(string $endpoint): WireframeAPI {
        $this->disableEndpoint($endpoint);
        unset($this->available_endpoints[$endpoint]);
        return $this;
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
