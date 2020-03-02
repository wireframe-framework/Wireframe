<?php

namespace Wireframe;

/**
 * Configuration helper for the Wireframe module
 *
 * @version 0.1.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Config extends \ProcessWire\Wire {

    /**
     * A local instance of the Wireframe module
     *
     * @param \ProcessWire\Wireframe Wireframe
     */
    protected $wireframe;

    /**
     * Constructor method
     *
     * @param Wireframe $wireframe Instance of Wireframe
     * @param \ProcessWire\ProcessWire $wire Instance of ProcessWire
     */
    public function __construct(\ProcessWire\Processwire $wire, \ProcessWire\Wireframe $wireframe) {
        $this->setWire($wire);
        $this->wireframe = $wireframe;
    }

    /**
     * Get all config fields for the Wireframe module
     *
     * @return \ProcessWire\InputfieldWrapper
     */
    public function getAllFields() {
        // inputfieldwrapper for config fields
        $fields = $this->wire(new \ProcessWire\InputfieldWrapper());

        // add field for the create directories feature
        $fields->add($this->getCreateDirectoriesField());

        return $fields;
    }

    public function getCreateDirectoriesField() {
        // init and setup a checkboxes field for the create directories feature
        $field = $this->wire(new \ProcessWire\InputfieldCheckboxes());
        $field->name = 'create_directories';
        $field->label = $this->_('Create directories automatically?');
        $field->description = $this->_('Wireframe can attempt to create required directories for you automatically if you check the items you want us to create and submit the form. Note that this option  requires write access to the disk, and may not be available in all environments.');
        $field->notes = $this->_('If a checkbox is disabled, ProcessWire doesn\'t have write access to the parent of the target directory, or the target directory already exists (in which case the checkbox should also be checked).');

        // get paths array from Wireframe
        $paths = $this->wireframe->paths;

        // check which Wireframe paths exist, and if we can create missing ones
        $all_directories_exist = true;
        if (empty($paths)) {
            // this shouldn't happen, but in case the paths array is empty show an error message
            $field->notes = $this->_('No directories found, possible configuration error. Please check your site config.');

        } else {
            // paths are defined, iterate and check existence and writability one by one
            foreach ($paths as $key => $path) {
                $attributes = [
                    'selected' => file_exists($path),
                    'disabled' => true,
                ];
                if (!$attributes['selected']) {
                    $parent_dir = \dirname($path);
                    if (\is_writable($parent_dir)) {
                        // writable parent and non-existing directory
                        $attributes['disabled'] = false;
                        if (\is_array($this->wire('input')->post->create_directories) && \in_array($key, $this->wire('input')->post->create_directories)) {
                            // attempt to create a directory
                            $path_created = \ProcessWire\wireMkDir($path);
                            if ($path_created) {
                                // directory created succesfully
                                $this->message(sprintf($this->_('Path created: %s'), $path));
                                $attributes['selected'] = true;
                                $attributes['disabled'] = true;
                            } else {
                                // creating directory failed
                                $this->error(sprintf($this->_('Creating path failed: %s'), $path));
                            }
                        }
                    }
                }
                if (!$attributes['selected']) {
                    $all_directories_exist = false;
                }
                $field->addOption($key, $path, $attributes);
            }

            // check if all directories exist, in which case there's nothing left to do here
            if ($all_directories_exist) {
                $field->notes = $this->_('All directories exist, nothing to create.');
            }
        }

        return $field;
    }

}
