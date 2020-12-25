<?php

namespace ProcessWire;

/**
 * Wireframe Hooks
 *
 * This is an autoloaded companion module for Wireframe.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframeHooks extends \ProcessWire\WireData implements Module {

    /**
     * Init method
     */
    public function init() {
        // hook into page not found event to provide endpoint for API
        if ($this->wire('modules')->isInstalled('WireframeAPI') && $this->wire('user')->isSuperuser()) {
            $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'handleAPIRequest');
        }
    }

    /**
     * Set up Wireframe API endpoint
     *
     * @param HookEvent $event
     */
    protected function handleAPIRequest(HookEvent $event) {

        // require page (could be missing in some border cases)
        if (!$this->wire('page')) return;

        // get Wireframe API module and API root
        /** @var WireframeAPI */
        $api = $this->modules->get('WireframeAPI');
        $api_root = $api->getAPIRoot();
        if (empty($api_root)) return;

        // compare API root with current reques
        $is_api_request = function_exists("mb_strpos") ? mb_strpos($this->input->url, $api_root) === 0 : strpos($this->input->url, $api_root);
        if (!$is_api_request) return;

        // make sure that field rendering works as expected (PageRender won't normally add this
        // hook if current page's template is admin, which is something we actually need here)
        if ($this->wire('page')->template == 'admin') {
            $pageRender = $this->wire('modules')->get('PageRender');
            $pageRender->addHookBefore('Page::render', $pageRender, 'beforeRenderPage', ['priority' => 1]);
        }

        // prepare args and API query
        $api_args = $this->wire('input')->get('api_args') ? json_decode($this->wire('input')->get('api_args'), true) : [];
        $api_query = $this->wire('input')->get('api_query');
        if ($api_query === null) {
            $api_query = $this->wire('input')->url;
            if (strpos($api_query, $api_root) === 0) {
                $api_query = substr($api_query, strlen($api_root));
            }
        }

        // init API and render API response
        $api->init($api_query, $api_args);
        $api->sendHeaders();
        echo $api->render();
        exit();
    }

}
