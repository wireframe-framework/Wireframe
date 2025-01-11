<?php

namespace Wireframe;

/**
 * Wireframe Partial
 *
 * @version 0.3.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Partial extends \ProcessWire\Wire {

    use RendererTrait;

    /**
     * Partial filenames
     *
     * @var array
     */
    protected $filenames = [];

    /**
     * Partial View
     *
     * @var PartialView
     */
    protected $partial_view;

    /**
     * A local instance of the Wireframe module
     *
     * Note: at least for now this gets populated automatically when needed, so it's not necessarily set at all times;
     * this may change in the future in case we need to access Wireframe module in more places.
     *
     * @var \ProcessWire\Wireframe|null
     */
    protected $_wireframe = null;

    /**
     * Constructor
     *
     * @param array $filenames Partial filenames as an assoc array (ext => filename).
     * @throws \ProcessWire\WireException if a partial file isn't found.
     */
    public function __construct(array $filenames) {
        foreach ($filenames as $ext => $filename) {
            if (!\is_file($filename)) {
                throw new \ProcessWire\WireException(\sprintf(
                    'Partial file not found: %s',
                    $filename
                ), 404);
            }
            $this->filenames[$ext] = $filename;
        }
    }

    /**
     * Render markup for the Partial
     *
     * @param array $args Arguments.
     * @return string Rendered Partial markup.
     */
    public function ___render(array $args = []): string {

        // prepare arguments
        if (\is_array($args) && !empty($args[0]) && \is_array($args[0])) {
            $args = $args[0];
        }

        // attempt to render markup using a renderer
        $renderer = $this->getRenderer();
        if ($renderer) {
            /** @noinspection PhpUndefinedMethodInspection */
            $ext = ltrim($renderer->getExt(), '.');
            $view_file = $this->filenames[$ext] ?? null;
            if (!empty($view_file) && \is_file($view_file)) {
                $view_prefix = $this->getViewPrefix();
                $view_file_with_prefix = $view_prefix == ''
                    ? ''
                    : \dirname($view_file) . '/' . $view_prefix . \basename($view_file);
                /** @noinspection PhpUndefinedMethodInspection */
                if ($view_file_with_prefix != '' && \is_file($view_file_with_prefix)) {
                    $view_file = $view_file_with_prefix;
                }
                $partials_path = $this->getPartialsPath();
                if ($partials_path !== null && strpos($view_file, $partials_path) === 0) {
                    $view_file = substr($view_file, \strlen($partials_path));
                }
                return $renderer->render('partial', $view_file, $args);
            }
        }

        // fall back to built-in template file rendering
        $fallback_filename = $this->filenames[$this->wire('config')->templateExtension] ?? null;
        if (empty($fallback_filename)) {
            return '';
        }
        $view_prefix = $this->getViewPrefix();
        if ($view_prefix != '') {
            $fallback_filename_with_prefix = \dirname($fallback_filename) . '/' . $view_prefix . \basename($fallback_filename);
            if (\is_file($fallback_filename_with_prefix)) {
                $fallback_filename = $fallback_filename_with_prefix;
            }
        }
        if (!$this->partial_view) {
            $this->partial_view = $this->wire(new PartialView());
        }
        $this->partial_view->setFilename($fallback_filename);
        $this->partial_view->data($args);
        return $this->partial_view->render() ?: '';
    }

    /**
     * Return default item from the filenames array
     *
     * @return string
     */
    public function __toString() {
        return $this->getFilename();
    }

    /**
     * Get filename for this partial
     *
     * @param string|null $ext
     * @param bool $with_ext
     * @return string
     */
    public function getFilename(?string $ext = null, bool $with_ext = true): string {
        $ext = $ext ?: $this->wire('config')->templateExtension;
        $filename = $this->filenames[$ext] ?? (array_values($this->filenames)[0] ?? '');
        return $with_ext ? $filename : substr($filename, 0, -strlen($ext)-1);
    }

    /**
     * Get partials path
     *
     * @return string|null
     *
     * @internal
     */
    protected function getPartialsPath(): ?string {
        if ($this->_wireframe === null) {
            $this->_wireframe = $this->wire('modules')->get('Wireframe');
        }
        return $this->_wireframe->getViewPaths()['partial'] ?? null;
    }

    /**
     * Get view prefix
     *
     * @return string View prefix
     *
     * @internal
     */
    protected function getViewPrefix(): string {
        if ($this->_wireframe === null) {
            $this->_wireframe = $this->wire('modules')->get('Wireframe');
        }
        return $this->_wireframe->getViewPrefix();
    }

    /**
     * debugInfo magic method
     *
     * @return array
     */
    public function __debugInfo() {
        return [
            'count' => \count($this->filenames),
            'items' => $this->filenames,
        ];
    }

}
