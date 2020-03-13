<?php

namespace Wireframe;

/**
 * Wireframe Partial
 *
 * @version 0.1.1
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
                ));
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
            $ext = '.' . ltrim($renderer->getExt(), '.');
            $view_file = $this->filenames[$ext] ?? null;
            if (empty($view_file)) {
                return '';
            }
            if (strpos($view_file, '.') === false) {
                $view_file .= $ext;
            } else {
                $ext_pos = strrpos($view_file, '.');
                $ext_now = substr($view_file, -$ext_pos);
                if ($ext_now != $ext) {
                    $view_file = substr($view_file, -$ext_pos) . $ext;
                }
            }
            if (\is_file($view_file)) {
                return $renderer->render('partial', ltrim($view_file, '/'), $args);
            }
        }

        // fall back to built-in template file rendering
        $filename = $this->filenames[$this->wire('config')->templateExtension] ?? null;
        if (empty($filename)) {
            return '';
        }
        return $this->wire('files')->render($filename, $args) ?: '';
    }

    /**
     * Return default item from the filenames array
     *
     * @return string
     */
    public function __toString() {
        $ext = $this->wire('config')->templateExtension;
        if (!empty($this->filenames[$ext])) {
            return $this->filenames[$ext];
        }
        return array_values($this->filenames)[0] ?? '';
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
