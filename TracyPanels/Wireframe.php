<?php

use \ProcessWire\Wireframe;

/**
 * Tracy Debugger Wireframe Panel
 *
 * To make your panel visible you have to add it to the public static $allPanels array in TracyDebugger.module
 * See also https://tracy.nette.org/en/extensions for docs about tracy panels
 *
 * @version 0.0.1
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
        if (\TracyDebugger::isAdditionalBar()) return;

        // Disable Wireframe panel if Wireframe hasn't been initialized
        if (!class_exists('\ProcessWire\Wireframe') || !Wireframe::isInitialized($this->wire()->instanceID)) return;

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
        $out .= $this->getPanelController();

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
     * Get Controller details section
     *
     * @return string
     */
    private function getPanelController(): string {
        $out = $this->wireframe->getController() ?: '<em>' . $this->_('Current page has no controller.') . '</em>';
        return $this->renderPanelSection('controller', 'Controller', $out, false);
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
            . "<div id='{$id}'" . ($collapsed ? " class='tracy-collapsed'" : "") . ">{$content}</div>";
    }

    /**
     * Render table header
     *
     * @param array $columns
     * @return string
     */
    private function renderTableHeader($columns = []): string {
        $out = '<table>'
           . '<thead>'
           . '<tr>';
        foreach ($columns as $column) {
            $out .= '<th>' . $column . '</th>';
        }

        $out .= '</tr>'
            . '</thead>'
            . '</tbody>';

        return $out;
    }

}
