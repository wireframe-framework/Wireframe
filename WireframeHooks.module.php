<?php

namespace ProcessWire;

/**
 * Wireframe Hooks
 *
 * This is an autoloaded companion module for Wireframe and Wireframe API.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframeHooks extends WireData implements Module {

    /**
     * Init method
     */
    public function init() {
        // hook into page not found event to provide endpoint for API
        if ($this->modules->isInstalled('WireframeAPI') && $this->user->isSuperuser()) {
            $this->addHookBefore('ProcessPageView::pageNotFound', $this, 'handleAPIRequest');
        }
    }

    /**
     * Set up Wireframe API endpoint
     *
     * @param HookEvent $event
     */
    protected function handleAPIRequest(HookEvent $event) {

        // params from event
        $page = $event->arguments[0];
        $url = $event->arguments[1];

        // get Wireframe API module and API root
        /** @var WireframeAPI */
        $api = $this->modules->get('WireframeAPI');
        $api_root = $api->getAPIRoot();
        if (empty($api_root)) return;

        // compare API root with current request
        $is_mb = function_exists("mb_strpos");
        if ($is_mb && mb_strpos($url, $api_root) !== 0 || !$is_mb && strpos($url, $api_root) !== 0) {
            return;
        }

        // make sure that field rendering works as expected in admin (PageRender won't normally add
        // this hook if current page's template is admin, which is something we actually need here)
        if ($page !== null && $page->template == 'admin') {
            $pageRender = $this->modules->get('PageRender');
            $pageRender->addHookBefore('Page::render', $pageRender, 'beforeRenderPage', [
                'priority' => 1,
            ]);
        }

        // prepare args and API query
        $api_args = $this->input->get('api_args') ? json_decode($this->input->get('api_args'), true) : [];
        if ($api_args === null) {
            $api_args = [];
        }
        $api_query = $this->input->get('api_query');
        if ($api_query === null) {
            $api_query = $is_mb ? mb_substr($url, mb_strlen($api_root)) : substr($url, strlen($api_root));
        }

        // init API and render API response
        $api->init($api_query, $api_args);
        $api->sendHeaders();
        echo $api->render();
        exit();
    }

}
