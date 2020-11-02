<?php

use \ProcessWire\Page;
use \ProcessWire\Wireframe;
use \Tracy\Dumper;

/**
 * Tracy Debugger Wireframe Panel
 *
 * To make your panel visible you have to add it to the public static $allPanels array in TracyDebugger.module
 * See also https://tracy.nette.org/en/extensions for docs about tracy panels
 *
 * @version 0.1.0
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
    private $icon = '<svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="svg-inline--fa fa-layer-group fa-w-16 fa-3x"><path fill="#354B60" d="M12.41 148.02l232.94 105.67c6.8 3.09 14.49 3.09 21.29 0l232.94-105.67c16.55-7.51 16.55-32.52 0-40.03L266.65 2.31a25.607 25.607 0 00-21.29 0L12.41 107.98c-16.55 7.51-16.55 32.53 0 40.04zm487.18 88.28l-58.09-26.33-161.64 73.27c-7.56 3.43-15.59 5.17-23.86 5.17s-16.29-1.74-23.86-5.17L70.51 209.97l-58.1 26.33c-16.55 7.5-16.55 32.5 0 40l232.94 105.59c6.8 3.08 14.49 3.08 21.29 0L499.59 276.3c16.55-7.5 16.55-32.5 0-40zm0 127.8l-57.87-26.23-161.86 73.37c-7.56 3.43-15.59 5.17-23.86 5.17s-16.29-1.74-23.86-5.17L70.29 337.87 12.41 364.1c-16.55 7.5-16.55 32.5 0 40l232.94 105.59c6.8 3.08 14.49 3.08 21.29 0L499.59 404.1c16.55-7.5 16.55-32.5 0-40z"/></svg>';

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

        // Heading
        $out = "<h1>{$this->icon} {$this->label}</h1>";

        // Maximize toggle
        $out .= '<span class="tracy-icons"><span class="resizeIcons"><a href="#" title="Maximize / Restore" onclick="tracyResizePanel(\'' . $this->className . '\')">+</a></span></span>';

        // Panel body
        $out .= '<div class="tracy-inner">';
        $out .= $this->getPanelConfig();
        $out .= $this->getPanelController();
        $out .= $this->getPanelControllerProps();
        $out .= $this->getPanelView();
        $out .= $this->getPanelViewData();

        // Panel footer
        $out .= \TracyDebugger::generatePanelFooter(
            $this->name,
            \Tracy\Debugger::timer($this->name),
            strlen($out . '</div>'),
            null
        );
        $out .= '</div>';

        return parent::loadResources()
            . $out;
    }

    /**
     * Get Config panel section
     *
     * @return string
     */
    private function getPanelConfig(): string {
        $out = '<p><em>Config settings passed to the Wireframe module. <a href="https://wireframe-framework.com/docs/configuration-settings/">Documentation for config settings.</a></em></p>';
        $out .= $this->renderTable($this->wireframe->getConfig());
        return $this->renderPanelSection('config', 'Config', $out, false);
    }

    /**
     * Get Controller panel section
     *
     * @return string
     */
    private function getPanelController(): string {
        $out = '<p><em>Current Controller object. <a href="https://wireframe-framework.com/docs/controllers/">Documentation for Controllers.</a></em></p>';
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
        $out = '<p><em>Public methods exposed by the Controller class, also known as Controller props. <a href="https://wireframe-framework.com/docs/controllers/">Documentation for Controllers.</a></em></p>';
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
        $out = '<p><em>Current View object. <a href="https://wireframe-framework.com/docs/view/">Documentation for the View layer.</a></em></p>';
        $view = $this->wireframe->view;
        $out .= $this->renderTable([
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
        $out = '<p><em>Data (variables) passed via the bootstrap file and/or the Controller class to the View. <a href="https://wireframe-framework.com/docs/view/">Documentation for the View layer.</a></em></p>';
        $out .= $this->renderTable($this->wireframe->view->data());
        return $this->renderPanelSection('viewData', 'View Data', $out, false);
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
        return "<a href='#' rel='{$id}' class='tracy-toggle" . ($collapsed ? " tracy-collapsed" : "") . "'>"
            . $label
            . "</a>"
            . "<div id='{$id}'" . ($collapsed ? " class='tracy-collapsed'" : "") . ">{$content}</div>"
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
     * This method attempts to use Tracy Dumper, and only if that fails it falls back to local custom implementation.
     *
     * @param mixed $value
     * @return string
     */
    private function formatValue($value): string {
        // trycatch is to prevent panel errors if an image is missing
        // log the error to the Tracy error logs instead
        try {
            $value = Dumper::toHtml($value, [
                Dumper::LIVE => true,
                Dumper::DEBUGINFO => \TracyDebugger::getDataValue('debugInfo'),
                Dumper::DEPTH => 99,
                Dumper::TRUNCATE => \TracyDebugger::getDataValue('maxLength'),
                Dumper::COLLAPSE_COUNT => 1,
                Dumper::COLLAPSE => false,
            ]);
        } catch (Exception $e) {
            if (is_array($value)) {
                $value = $this->getJSON($value);
            } else if (is_object($value)) {
                if ($value instanceof \Wireframe\ViewPlaceholders) {
                    $value = $this->getJSON($value->getData());
                } else if ($value instanceof \Wireframe\Partials) {
                    $value = $this->getJSON($value->__debugInfo());
                } else if ($value instanceof Page) {
                    $value = $this->getPageInfo($value);
                } else if (method_exists($value, '__toString')) {
                    $value = get_class($value) . ' ' . $value;
                }
            } else if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $value = '<pre>' . $value . '</pre>';
        }
        return $value;
    }

    /**
     * Get Page debug output
     *
     * @param Page $page
     * @return string
     */
    private function getPageInfo(Page $page): string {
        return get_class($page) . ' ' . $this->getJSON([
            'id' => $page->id,
            'name' => $page->name,
            'template' => $page->template->name,
            'url' => $page->url,
        ]);
    }

    /**
     * Get JSON encoded value
     *
     * @param mixed $value
     * @return string
     */
    private function getJSON($value): string {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

}
