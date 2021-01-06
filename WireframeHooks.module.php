<?php

namespace ProcessWire;

/**
 * Wireframe Hooks
 *
 * This is an autoloaded companion module for Wireframe and Wireframe API.
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframeHooks extends WireData implements Module {

    /**
     * Wireframe API instance
     *
     * @var null|WireframeAPI
     */
    protected $api;

    /**
     * Is multibyte support enabled?
     *
     * @var bool
     */
    protected $is_mb = false;

    /**
     * Init method
     */
    protected function init() {
        // hook into page not found event to provide endpoint for API
        if ($this->modules->isInstalled('WireframeAPI') && $this->user->isSuperuser()) {
            $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'interceptAPIRequest');
        }
    }

    /**
     * Intercept API requests
     *
     * @param HookEvent $event
     *
     * @throws WireException if required config::http404PageID doesn't exist
     */
    protected function interceptAPIRequest(HookEvent $event) {

        // params from event
        $page = $event->arguments[0];
        $url = $event->arguments[1];

        // get Wireframe API module and API root
        $this->api = $this->modules->get('WireframeAPI');
        $api_root = $this->api->getAPIRoot();
        if (empty($api_root)) return;

        // check if multibyte encoding is enabled
        $this->is_mb = function_exists('mb_strpos');

        // compare API root with current request
        if ($this->is_mb && mb_strpos($url, $api_root) !== 0 || !$this->is_mb && strpos($url, $api_root) !== 0) {
            return;
        }

        // set page
        if ($page === null || !$page->id) {
            if ($this->config->http404PageID) {
                $page = $this->pages->get($this->config->http404PageID);
                if ($page === null || !$page->id) {
                    throw new WireException("config::http404PageID does not exist - please check your config");
                }
            }
        }
        $this->wire('page', $page);

        // set system ready state
        $event->object->ready();

        // handle API request
        $event->return = $this->renderAPIResponse($url);
        $event->replace = true;
    }

    /**
     * Render API response
     *
     * @param string $url
     * @return string
     */
    protected function renderAPIResponse(string $url): string {

        // params for API query
        $args = $this->input->get('args') ? json_decode($this->input->get('args'), true) : [];
        if ($args === null) {
            $args = [];
        }
        $root = $this->api->getAPIRoot();
        $path = $this->is_mb ? mb_substr($url, mb_strlen($root)) : substr($url, strlen($root));

        // init API, render and return API response
        $this->api->init($path, $args);
        $this->api->sendHeaders();
        return $this->api->render();
    }

}
