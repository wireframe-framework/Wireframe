<?php

namespace Wireframe;

/**
 * Wireframe Partials
 *
 * This class holds Partial objects and provides method access to rendering them with optional arguments.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Partials extends \ProcessWire\WireArray {

    /**
     * Gateway for rendering partials with arguments
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     */
    public function __call($method, $arguments) {
        $partial = $this->$method;
        if ($partial instanceof Partial) {
            return $partial->render($arguments);
        }
        return parent::__call($method, $arguments);
    }

}
