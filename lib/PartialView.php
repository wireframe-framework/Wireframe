<?php

namespace Wireframe;

use ProcessWire\WireException;

/**
 * Wireframe Partial View component
 *
 * This class is a wrapper for the ProcessWire TemplateFile class with some modifications and the
 * addition of the Wireframe namespace.
 *
 * @version 0.0.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class PartialView extends \ProcessWire\TemplateFile {

    /**
     * Render the partial
     *
     * This method is a copy of the TemplateFile::render() method with unnecessary parts, such as
     * prepend/append file handling and various configuration flags, removed.
     *
     * @return string The output of the partial file
     * @throws WireException if file not exist or if any exceptions is thrown by included file(s)
     */
    public function ___render() {

        if (!$this->filename) return '';

        if (!file_exists($this->filename)) {
            $error = "Partial file does not exist: $this->filename";
            throw new WireException($error);
        }

        $this->renderReady();

        // make API variables available to PHP file
        $fuel = array_merge($this->getArray(), self::$globals); // so that script can foreach all vars to see what's there
        extract($fuel);
        ob_start();

        // include main file to render
        try {
            $this->fileReady($this->filename);
            $this->returnValue = require($this->filename);
            $this->fileFinished();
        } catch(\Exception $e) {
            if ($this->fileFailed($this->filename, $e)) throw $this->renderFailed($e);
        }

        $out = ob_get_contents();
        ob_end_clean();

        $this->renderFinished();

        $out = trim($out);

        return $out;
    }

}
