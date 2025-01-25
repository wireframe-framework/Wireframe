<?php

namespace Wireframe;

use ProcessWire\Wire;

/**
 * Trait for adding renderer support to Wireframe objects
 *
 * @property \ProcessWire\Module|null $renderer Renderer object.
 *
 * @version 0.1.5
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
trait RendererTrait {

    /**
     * Renderer module
     *
     * @var null|\ProcessWire\Module
     */
    private $renderer;

    /**
     * Set renderer
     *
     * @param \ProcessWire\Module|string|null $renderer Renderer module, name of a renderer module, or null to unset.
     * @param array $settings Optional array of settings for the renderer module.
     * @return Wire Self-reference.
     */
    final public function setRenderer($renderer, array $settings = []): Wire {
        $needs_init = !empty($settings);
        if ($renderer === null) {
            $this->renderer = null;
        } else if (\is_string($renderer)) {
            $renderer = $this->wire('modules')->get($renderer);
            $needs_init = true;
        }
        if ($renderer instanceof \ProcessWire\Module) {
            /**
             * @noinspection PhpUndefinedMethodInspection
             * @disregard P1013 as it's a false positive; renderers are expected to have init() method
             */
            if ($needs_init) $renderer->init($settings);
            $this->renderer = $renderer;
            if (method_exists($this, 'setExt')) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 * @disregard P1013 as it's a false positive; renderers are expected to have getExt() method
                 */
                $this->setExt($renderer->getExt());
            }
        }
        return $this;
    }

    /**
     * Get renderer
     *
     * @return \ProcessWire\Module|null Wireframe renderer module or null.
     */
    final public function getRenderer(): ?\ProcessWire\Module {
        return $this->renderer
            ?: (!$this instanceof View
                ? ($this->wire('view') !== null
                    ? $this->wire('view')->getRenderer()
                    : null
                )
                : null
            );
    }

}
