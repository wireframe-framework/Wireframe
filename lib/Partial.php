<?php

namespace Wireframe;

/**
 * Wireframe Partial
 *
 * @version 0.2.0
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
                /** @noinspection PhpUndefinedMethodInspection */
                $partials_path = $this->wire('modules')->get('Wireframe')->getViewPaths()['partial'] ?? null;
                if ($partials_path !== null && strpos($view_file, $partials_path) === 0) {
                    $view_file = substr($view_file, \strlen($partials_path));
                }
                return $renderer->render('partial', $view_file, $args);
            }
        }

        // fall back to built-in template file rendering
        $filename = $this->filenames[$this->wire('config')->templateExtension] ?? null;
        if (empty($filename)) {
            return '';
        }
        $template = $this->wire(new \ProcessWire\TemplateFile());
        $template->setFilename($filename);
        $template->data($args);
        return $template->render() ?: '';
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
