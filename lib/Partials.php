<?php

namespace Wireframe;

/**
 * Wireframe Partials
 *
 * This class holds Partial objects and provides method access to rendering them with optional arguments.
 *
 * @version 0.3.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Partials extends \ProcessWire\WireArray {

    /**
     * Path to the root directory for partial files
     *
     * @var string
     */
    private $path;

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

    /**
     * Enables derefencing of WireArray elements in object notation.
     *
     * Note that unlike regular WireArray, we're going to prioritize local data items.
     *
     * @param int|string $property
     * @return Partial|Partials|null Partial or Partials object or null if no matching item found
     *
     */
    public function __get($property) {
        $value = null;
        if ((\is_string($property) || \is_int($property)) && isset($this->data[$property])) {
            $value = $this->data[$property];
        } else {
            $value = parent::__get($property);
        }
        if ($value === null) {
            $ext = $this->wire('config')->templateExtension;
            return new Partial([
                $ext => $this->path . $property . '.' . $ext,
            ]);
        }
        return $value;
    }

    /**
     * Get a new/blank Partial object
     *
     * @return Partial
     *
     */
    public function makeBlankItem() {
        return new Partial([]);
    }

    /**
     * Set path
     *
     * @param string $path
     * @return Partials Self-reference
     */
    public function setPath(string $path): Partials {
        $this->path = $path;
        return $this;
    }

    /**
     * debugInfo magic method
     *
     * @return array
     */
    public function __debugInfo() {
        $items = [];
        foreach ($this->getArray() as $key => $item) {
            $items[$key] = $item->__debugInfo();
        }
        return $items;
    }

}
