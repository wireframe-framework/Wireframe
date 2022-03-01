<?php

namespace Wireframe;

/**
 * Wireframe Partials
 *
 * This class holds Partial objects and provides method access to rendering them with optional arguments.
 *
 * @version 0.5.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Partials extends \ProcessWire\WireArray {

    /**
     * Path to the root directory for partial files
     *
     * @var string
     */
    private $path = '';

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
     * Return the rendered value of named partial, or null if none found
     *
     * This is an alias for Partials::get($key)->render($args).
     *
     * @param string $key
     * @param array $arguments
     * @return string|null
     */
    public function render(string $key, array $arguments = []): ?string {
        $partial = $this->get($key);
        if ($partial instanceof Partial) {
            return $partial->render($arguments);
        }
        return null;
    }

    /**
     * Returns the value of the item at the given index, or null if not set.
     *
     * This method is overridden so that we can support getting partials recursively based on provided path.
     *
     * This version also supports a second argument, which is not derived from WireArray::get(). When a Partial matching
     * provided path is found *and* an array has been provided as the second argument, said array is then passed to the
     * Partial::render() method, and resulting markup is returned instead of the Partial object itself.
     *
     * @param int|string|array $key
     * @param array $arguments Optional array of arguments
     * @return WireData|Page|mixed|array|null Value of item requested, or null if it doesn't exist.
     * @throws WireException
     *
     * @see \ProcessWire\WireArray::get()
     */
    public function get($key) {
        $partial = null;
        if (\is_string($key)) {
            $names = strpos($key, '/') ? array_filter(explode('/', $key)) : [$key];
            if (!empty($names)) {
                foreach ($names as $name) {
                    $partial = ($partial ?? $this)->data[$name] ?? null;
                    if ($partial === null) break;
                }
                if ($partial !== null && \func_num_args() > 1) {
                    $arguments = \func_get_arg(1);
                    if (\is_array($arguments)) {
                        return $partial->render($arguments);
                    }
                }
            }
        }
        return $partial ?? parent::get($key);
    }

    /**
     * Enables derefencing of WireArray elements in object notation.
     *
     * Note that unlike regular WireArray, we're going to prioritize local data items.
     *
     * @param int|string $property
     * @return Partial|Partials Partial or Partials object
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
     * Get path
     *
     * @return string
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Get array of partial filenames
     *
     * @internal
     *
     * @param string|null $ext
     * @param bool $with_ext
     * @return array
     */
    public function getFilenames(string $ext = null, bool $with_ext = true): array {
        $filenames = [];
        foreach ($this->getArray() as $partial) {
            if ($partial instanceof Partials) {
                $filenames += $partial->getFilenames($ext, $with_ext);
                continue;
            }
            $filenames[] = $partial->getFilename($ext, $with_ext);
        }
        return $filenames;
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
