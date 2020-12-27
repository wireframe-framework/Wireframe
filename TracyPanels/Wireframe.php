<?php

use \ProcessWire\Wireframe;
use \ProcessWire\WireframeAPI;
use \Tracy\Dumper;

/**
 * Tracy Debugger Wireframe Panel
 *
 * See https://tracy.nette.org/en/extensions for docs about Tracy panels.
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class WireframePanel extends BasePanel {

    /**
     * Panel name
     *
     * @var string
     */
    private $name = 'wireframe';

    /**
     * Panel label
     *
     * @var string
     */
    private $label = 'Wireframe';

    /**
     * Panel icon (SVG)
     *
     * @var string
     */
    private $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!-- Font Awesome Free 5.15.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) --><path fill="#354B60" d="M12.41 148.02l232.94 105.67c6.8 3.09 14.49 3.09 21.29 0l232.94-105.67c16.55-7.51 16.55-32.52 0-40.03L266.65 2.31a25.607 25.607 0 00-21.29 0L12.41 107.98c-16.55 7.51-16.55 32.53 0 40.04zm487.18 88.28l-58.09-26.33-161.64 73.27c-7.56 3.43-15.59 5.17-23.86 5.17s-16.29-1.74-23.86-5.17L70.51 209.97l-58.1 26.33c-16.55 7.5-16.55 32.5 0 40l232.94 105.59c6.8 3.08 14.49 3.08 21.29 0L499.59 276.3c16.55-7.5 16.55-32.5 0-40zm0 127.8l-57.87-26.23-161.86 73.37c-7.56 3.43-15.59 5.17-23.86 5.17s-16.29-1.74-23.86-5.17L70.29 337.87 12.41 364.1c-16.55 7.5-16.55 32.5 0 40l232.94 105.59c6.8 3.08 14.49 3.08 21.29 0L499.59 404.1c16.55-7.5 16.55-32.5 0-40z"/></svg>';

    /**
     * Help icon
     *
     * @var string
     */
    private $iconHelp = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!-- Font Awesome Free 5.15.1 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free (Icons: CC BY 4.0, Fonts: SIL OFL 1.1, Code: MIT License) --><path fill="#354B60" d="M256 8C119.043 8 8 119.083 8 256c0 136.997 111.043 248 248 248s248-111.003 248-248C504 119.083 392.957 8 256 8zm0 448c-110.532 0-200-89.431-200-200 0-110.495 89.472-200 200-200 110.491 0 200 89.471 200 200 0 110.53-89.431 200-200 200zm107.244-255.2c0 67.052-72.421 68.084-72.421 92.863V300c0 6.627-5.373 12-12 12h-45.647c-6.627 0-12-5.373-12-12v-8.659c0-35.745 27.1-50.034 47.579-61.516 17.561-9.845 28.324-16.541 28.324-29.579 0-17.246-21.999-28.693-39.784-28.693-23.189 0-33.894 10.977-48.942 29.969-4.057 5.12-11.46 6.071-16.666 2.124l-27.824-21.098c-5.107-3.872-6.251-11.066-2.644-16.363C184.846 131.491 214.94 112 261.794 112c49.071 0 101.45 38.304 101.45 88.8zM298 368c0 23.159-18.841 42-42 42s-42-18.841-42-42 18.841-42 42-42 42 18.841 42 42z"/></svg>';

    /**
     * Panel body
     *
     * @var string
     */
    private $body = '';

    /**
     * Resources URL
     *
     * @var string
     */
    private $resURL = '';

    /**
     * Wireframe instance
     *
     * @var Wireframe
     */
    private $wireframe;

    /**
     * Tracy debug bar tab
     *
     * @return string
     */
    public function getTab() {

        // Bail out early if this is an additional bar (AJAX, redirect)
        if (\TracyDebugger::isAdditionalBar()) return '';

        // Disable Wireframe panel if Wireframe hasn't been initialized
        if (!class_exists('\ProcessWire\Wireframe') || !Wireframe::isInitialized($this->wire()->instanceID)) return '';

        \Tracy\Debugger::timer($this->name);
        return "<span title='{$this->label}'>"
            . "{$this->icon} "
            . (\TracyDebugger::getDataValue('showPanelLabels') ? $this->label : '')
            . "</span>";
    }

    /**
     * Panel contents (markup)
     *
     * @return string
     */
    public function getPanel() {

        // Get Wireframe
        $this->wireframe = $this->wire('modules')->get('Wireframe');

        // Set up resources URL
        $this->resURL = $this->wire('config')->urls->get('Wireframe') . 'res/';

        // Return panel content
        return parent::loadResources()
            . "<h1>{$this->icon} {$this->label}</h1>"
            . "<link rel='stylesheet' href='{$this->resURL}css/TracyPanel.css'>"
            . "<span class='tracy-icons'><span class='resizeIcons'><a href='#' title='Maximize / Restore' onclick='tracyResizePanel(\"{$this->className}\")'>+</a></span></span>"
            . "<div class='tracy-inner'>"
            . $this->getPanelBody()
            . "<script>
            (function() {
                const script = document.createElement('script');
                script.src = '{$this->resURL}js/TracyPanel.js';
                document.getElementById('tracy-debug-panel-WireframePanel').appendChild(script);
            })();
            </script>"
            . $this->getPanelFooter()
            . "</div>";
    }

    /**
     * Get panel body
     *
     * @return string
     */
    private function getPanelBody(): string {

        // Prepare panel content
        $content = array_filter([
            'data' => [
                'label' => 'Data',
                'value' => $this->getPanelConfig()
                    . $this->getPanelController()
                    . $this->getPanelControllerProps()
                    . $this->getPanelView()
                    . $this->getPanelViewData()
            ],
            'api' => [
                'label' => 'API',
                'value' => $this->getPanelAPI(),
            ],
        ], function($tab) {
            return $tab['value'] !== '';
        });

        // Construct and return panel body
        $tab_class = ' wireframe-tracy-tab-link--current';
        foreach ($content as $key => $tab) {
            $this->body .= "<a class='wireframe-tracy-tab-link{$tab_class}' href='#wireframe-tracy-tab-{$key}'>{$tab['label']}</a>";
            $tab_class = '';
        }
        $tab_attrs = '';
        foreach ($content as $key => $tab) {
            $this->body .= "<div class='wireframe-tracy-tab' id='wireframe-tracy-tab-{$key}'{$tab_attrs}>{$tab['value']}</div>";
            $tab_attrs = ' hidden';
        }
        return $this->body;
    }

    /**
     * Get panel footer
     *
     * @return string
     */
    private function getPanelFooter(): string {
        return \TracyDebugger::generatePanelFooter(
            $this->name,
            \Tracy\Debugger::timer($this->name),
            strlen($this->body),
            null
        );
    }

    /**
     * Get Config panel section
     *
     * @return string
     */
    private function getPanelConfig(): string {
        $out = "<a class='wireframe-tracy-doc-link' target='_blank' href='https://wireframe-framework.com/docs/configuration-settings/' title='Config settings passed to the Wireframe module.'>{$this->iconHelp}</a>";
        $config = $this->wireframe->getConfig();
        $out .= empty($config) ? '<pre>[]</pre>' : $this->renderTable($config);
        return $this->renderPanelSection('config', 'Config', $out, false);
    }

    /**
     * Get Controller panel section
     *
     * @return string
     */
    private function getPanelController(): string {
        $out = "<a class='wireframe-tracy-doc-link' target='_blank' href='https://wireframe-framework.com/docs/controllers/' title='Current Controller object.'>{$this->iconHelp}</a>";
        $controller = $this->wireframe->getController() ?: null;
        $out .= $controller === null ? '<pre>null</pre>' : $this->renderTable([
            'class' => get_class($controller),
            'page' => $controller->page,
        ]);
        return $this->renderPanelSection('controller', 'Controller', $out, false);
    }

    /**
     * Get Controller Props panel section
     *
     * @return string
     */
    private function getPanelControllerProps(): string {
        $out = "<a class='wireframe-tracy-doc-link' target='_blank' href='https://wireframe-framework.com/docs/controllers/' title='Public methods exposed by the Controller class, also known as Controller props.'>{$this->iconHelp}</a>";
        $controller = $this->wireframe->getController() ?: null;
        if ($controller === null) {
            $out .= '<pre>null</pre>';
        } else {
            $out .= '<table><tr>';
            foreach (['Name', 'Return', 'Caching', 'Current value'] as $key) {
                $out .= '<th>' . $key . '</th>';
            }
            $out .= '</tr>';
            $props = $controller->getMethodProps('controller', 2);
            foreach ($props as $prop_name => $prop) {
                $out .= '<tr><td>' . implode('</td><td>', [
                    empty($prop['comment']) ? $prop_name : '<span style="text-decoration: underline; text-decoration-style: dotted" title="' . $this->sanitizer->entities1($prop['comment']) . '">' . $prop_name . '</span>',
                    $prop['return'],
                    $prop['caching'],
                    $this->formatValue($prop['value']),
                ]) . '</td></tr>';
            }
            $out .= '</table>';
        }
        return $this->renderPanelSection('controllerProps', 'Controller Props', $out, false);
    }

    /**
     * Get View panel section
     *
     * @return string
     */
    private function getPanelView(): string {
        $out = "<a class='wireframe-tracy-doc-link' target='_blank' href='https://wireframe-framework.com/docs/view/' title='Current View object.'>{$this->iconHelp}</a>";
        $view = $this->wireframe->view;
        $out .= $view === null ? '<pre>null</pre>' : $this->renderTable([
            'page' => $view->getPage(),
            'template' => $view->getTemplate(),
            'layout' => $view->getLayout(),
            'view' => $view->getView(),
            'ext' => $view->getExt(),
            'layouts_path' => $view->getLayoutsPath(),
            'views_path' => $view->getViewsPath(),
            'placeholders' => $view->getPlaceholders(),
            'partials' => $view->getPartials(),
        ]);
        return $this->renderPanelSection('view', 'View', $out, false);
    }

    /**
     * Get View Data panel section
     *
     * @return string
     */
    private function getPanelViewData(): string {
        $out = "<a class='wireframe-tracy-doc-link' target='_blank' href='https://wireframe-framework.com/docs/view/' title='Data (variables) passed via the bootstrap file and/or the Controller class to the View.'>{$this->iconHelp}</a>";
        $out .= $this->wireframe->view === null ? '<pre>null</pre>' : $this->renderTable($this->wireframe->view->data());
        return $this->renderPanelSection('viewData', 'View Data', $out, false);
    }

    /**
     * Get API panel section
     *
     * @return string
     */
    private function getPanelAPI(): string {

        // Get Wireframe API
        $api = $this->maybeGetAPI();
        if (!$api instanceof WireframeAPI) {
            return $api === '' ? '' : '<p>' . $api . '</p>';
        }

        // API root path
        $out = $this->renderInput('API root', 'api_root', 'text', null, $api->getAPIRoot());

        // API endpoint
        $out .= $this->renderInput('Endpoint', 'endpoint', 'select', null, $api->getEnabledEndpoints());

        // Page ID
        $out .= $this->renderInput('Page ID', 'page_id', 'number', 'pages', 1);

        // Component
        $available_components = array_map(function($component) {
            return basename($component, '.php');
        }, glob($this->wireframe->getConfig()['paths']['components'] . '*.php'));
        $out .= $this->renderInput('Component', 'component', 'select', 'components', $available_components);

        // Partial
        $available_partials = [];
        if ($this->wire('view')) {
            $partials = $this->wire('view')->getPartials();
            $partials_path_size = strlen($partials->getPath());
            $available_partials = array_map(function($partial) use ($partials_path_size) {
                return substr($partial, $partials_path_size);
            }, $partials->getFilenames(null, false));
        }
        $out .= $this->renderInput('Partial', 'partial', 'select', 'partials', $available_partials);

        // Return format
        $out .= $this->renderInput('Return format', 'return_format', 'select', 'components+pages', [
            '',
            'json',
            'rendered',
        ]);

        // Arguments
        $out .= $this->renderInput('Arguments', 'api_args', 'textarea', null, "{\n\t\"argument\": \"value\"\n}", 'js-wireframe-tracy-api-args');
        $out .= '<p style="opacity: .9">You can provide arguments as JSON, in which case they will be passed to the API as GET param "api_args", or in URL format (param1=value1&amp;param2=value2) in which case they will be appended to the API GET request as is. Note that the default API root used by this debugger only supports JSON format arguments.</p>';

        // Render and return form
        return "<form class='wireframe-tracy-api-form' id='js-wireframe-tracy-api-form'>"
            . $out
            . "<div class='wireframe-tracy-api-code wireframe-tracy-api-code--break' id='js-wireframe-tracy-api-query'></div>"
            . "<div class='wireframe-tracy-api-code' id='js-wireframe-tracy-api-response' tabindex=-1 hidden></div>"
            . "<div class='wireframe-tracy-api-form-row'><input type='submit' id='js-wireframe-tracy-api-submit' value='Send request'></div>"
            . "</form>";
    }

    /**
     * Attempt to get Wireframe API
     *
     * @return string|WireframeAPI Message string indicating an issue, or Wireframe API module if all checks out
     */
    protected function maybeGetAPI() {

        // Superuser role is required
        if (!$this->wire('user')->isSuperuser()) {
            return 'Permission denied.';
        }

        // Wireframe API module needs to be installed
        if (!$this->wire('modules')->isInstalled('WireframeAPI')) {
            return 'Wireframe API module is not installed, API debug tool disabled.';
        }

        /** @var WireframeAPI */
        $api = $this->wire('modules')->get('WireframeAPI');
        $api_config_url = $this->wire('modules')->getModuleEditUrl($api);

        // At least one endpoint needs to be enabled
        if (empty($api->getEnabledEndpoints())) {
            return sprintf(
                'In order to perform API queries you need to <a href="%s">enable at least one API endpoint</a>.',
                $api_config_url
            );
        }

        return $api;
    }

    /**
     * Render form input
     *
     * @param string $label
     * @param string $name
     * @param string $type
     * @param string|null $endpoint
     * @param array|string|null $value
     * @param string $class
     * @return string
     */
    private function renderInput(string $label, string $name, string $type, ?string $endpoint = null, $value = null, $class = 'js-wireframe-tracy-api-param'): string {
        $out = "<label class='wireframe-tracy-api-form-row";
        if ($endpoint !== null) {
            $out .= " wireframe-tracy-api-form-row--endpoint";
            $endpoint_names = explode('+', $endpoint);
            foreach ($endpoint_names as $endpoint_name) {
                $out .= " wireframe-tracy-api-form-row--endpoint-{$endpoint_name}";
            }
        }
        $out .= "'"
            . ($endpoint !== null ? " hidden" : "")
            . ">"
            . "<span>{$label}</span>";
        if ($type == 'number') {
            $out .= "<input type='number' min=1 step=1 name='{$name}' value='{$value}' id='js-wireframe-tracy-api-{$name}' class='{$class}'>";
        } else if ($type == 'text') {
            $out .= "<input type='text' name='{$name}' value='{$value}' id='js-wireframe-tracy-api-{$name}' class='{$class}'>";
        } else if ($type == 'textarea') {
            $out .= "<textarea name='{$name}' id='js-wireframe-tracy-api-{$name}' class='{$class}' rows=5>{$value}</textarea>";
        } else if ($type == 'select') {
            $out .= "<select name='{$name}' id='js-wireframe-tracy-api-{$name}' class='{$class}'>"
                . implode(array_map(function($value) {
                    return "<option value='{$value}'>{$value}</option>";
                }, $value))
                . "</select>";
        }
        $out .= "</label>";
        return $out;
    }

    /**
     * Render panel section
     *
     * @param string $id
     * @param string $label
     * @param string|null $content
     * @param bool $collapsed
     * @return string
     */
    private function renderPanelSection(string $id, string $label, ?string $content, bool $collapsed = true): string {
        if ($content === null) return '';
        $id = $this->wire('sanitizer')->name($id);
        $label = $this->wire('sanitizer')->entities($label);
        return "<a href='#' rel='wireframe-tracy-panel-section-{$id}' class='tracy-toggle" . ($collapsed ? " tracy-collapsed" : "") . "'>"
            . $label
            . "</a>"
            . "<div class='wireframe-tracy-panel-section' id='wireframe-tracy-panel-section-{$id}'" . ($collapsed ? " class='tracy-collapsed'" : "") . ">{$content}</div>"
            . "<br>";
    }

    /**
     * Render data table
     *
     * @param array $array
     * @return string
     */
    private function renderTable(array $array): string {
        return '<table>' . implode(array_map(function($key, $value) {
            return '<tr><th>' . $key . '</th><td>' . $this->formatValue($value) . '</td></tr>';
        }, array_keys($array), $array)) . '</table>';
    }

    /**
     * Format value for data table
     *
     * This method is a wrapper for Tracy Dumper.
     *
     * @param mixed $value
     * @return string
     */
    private function formatValue($value): string {
        return Dumper::toHtml($value, [
            Dumper::LIVE => true,
            Dumper::DEBUGINFO => \TracyDebugger::getDataValue('debugInfo'),
            Dumper::DEPTH => 99,
            Dumper::TRUNCATE => \TracyDebugger::getDataValue('maxLength'),
            Dumper::COLLAPSE_COUNT => 1,
            Dumper::COLLAPSE => false,
        ]);
    }

}
