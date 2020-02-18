<?php

namespace Wireframe;

/**
 * Wireframe Partials
 *
 * This class holds partial file references and provides method access to rendering them with
 * optional arguments.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Partials extends \ProcessWire\Wire {

    /**
     * Gateway for rendering partials with arguments
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments) {
        $file = $this->$method;
        if (!empty($file) && is_file($file)) {
            $args = is_array($arguments) && !empty($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : [];
            return \ProcessWire\wireRenderFile($file, $args);
        }
        return parent::__call($method, $arguments);
    }

}
