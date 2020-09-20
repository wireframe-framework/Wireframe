<?php

namespace Wireframe;

use \ProcessWire\InputfieldCheckboxes;
use \ProcessWire\InputfieldWrapper;

/**
 * Configuration helper for the Wireframe module
 *
 * @version 0.3.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Config extends \ProcessWire\Wire {

    /**
     * Config data array passed from the Wireframe module
     *
     * @var array
     */
    protected $data = [];

    /**
     * Constructor
     *
     * @param array $data Config data array.
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /**
     * Get all config fields
     *
     * @return InputfieldWrapper
     */
    public function getAllFields(): InputfieldWrapper {

        // inputfieldwrapper for config fields
        $fields = $this->wire(new InputfieldWrapper());

        // add field for the create directories feature
        $fields->add($this->getCreateDirectoriesField());

        return $fields;
    }

    /**
     * Get config field for the create directories feature
     *
     * @return InputfieldCheckboxes
     */
    protected function getCreateDirectoriesField(): InputfieldCheckboxes {

        // init and setup a checkboxes field for the create directories feature
        $field = $this->wire(new InputfieldCheckboxes());
        $field->name = 'create_directories';
        $field->label = $this->_('Create directories automatically?');
        $field->description = $this->_('Wireframe can attempt to create required directories for you automatically if you check the items you want us to create and submit the form. Note that this option  requires write access to the disk, and may not be available in all environments.');
        $field->notes = $this->_('If a checkbox is disabled, ProcessWire doesn\'t have write access to the parent of the target directory, or the target directory already exists (in which case the checkbox should also be checked).');

        // return the processed create directories field
        return $this->processCreateDirectoriesField($field);
    }

    /**
     * Process the create directories field
     *
     * This method does a few things: adds paths as options to the provided checkboxes field, checks each path (is it
     * already created, and if not can it be created), and (in case POST data is already present) attempts to create
     * specified paths.
     *
     * @param InputfieldCheckboxes $field
     * @return InputfieldCheckboxes
     */
    protected function processCreateDirectoriesField(InputfieldCheckboxes $field): InputfieldCheckboxes {

        // get an array of paths and bail out early if the paths array is empty
        $paths = $this->getPaths();
        if (empty($paths)) {
            $field->notes = $this->_('No directories found, possible configuration error. Please check your site config.');
            return $field;
        }

        // define some flags that we're going to need later on
        $all_directories_exist = true;
        $paths_include_urls = false;

        // iterate over the paths array and ...
        foreach ($paths as $key => $path) {

            // 1) check if we're dealing with a relative URL, in which case the path variable needs to be adjusted
            $real_path = $path;
            if (substr($path, 0, 2) == '@ ') {
                $paths_include_urls = true;
                $real_path = substr($path, 2);
            }

            // 2) define option attributes and attempt to create the path -- but only if it was previously selected and
            // thus can be found from the POST data property "create_directories"
            $attributes = [
                'selected' => file_exists($real_path),
                'disabled' => true,
            ];
            if (!$attributes['selected']) {
                $parent_dir = \dirname($real_path);
                if (\is_writable($parent_dir)) {

                    // writable parent and non-existing directory
                    $attributes['disabled'] = false;

                    if (\is_array($this->wire('input')->post->create_directories) && \in_array($key, $this->wire('input')->post->create_directories)) {

                        // attempt to create a directory
                        $path_created = \ProcessWire\wireMkDir($real_path);

                        if ($path_created) {

                            // directory created succesfully
                            $this->message(sprintf($this->_('Path created: %s'), $real_path));
                            $attributes['selected'] = true;
                            $attributes['disabled'] = true;

                        } else {

                            // creating directory failed
                            $this->error(sprintf($this->_('Creating path failed: %s'), $real_path));

                        }
                    }
                }
            }
            if (!$attributes['selected']) {
                $all_directories_exist = false;
            }

            // 3) add the path as a field option
            $field->addOption($key, $path, $attributes);
        }

        // if selectable options include relative URLs, let the user know what the difference is
        if ($paths_include_urls) {
            $field->notes .= "\n\n" . $this->_('Items with the "@" prefix are URL helpers for static resources. These are not strictly speaking required, but can be useful while developing the site.');
        }

        // check if all directories exist, in which case there's nothing left for us to do here
        if ($all_directories_exist) {
            $field->notes = $this->_('All directories exist, nothing to create.');
        }

        return $field;
    }

    /**
     * Get paths for the create directories feature
     *
     * @return \stdClass
     */
    protected function getPaths(): \stdClass {

        // get paths object from Wireframe
        $paths = $this->wireframe->paths;

        // append relative URLs
        $urls = $this->wire('modules')->get('Wireframe')->getConfig()['urls'] ?? [];
        if (!empty($urls)) {
            foreach ($urls as $key => $url) {
                if (strpos($url, '/') !== 0) {
                    // not a local path, skip
                    continue;
                }
                $paths->$key = '@ ' . rtrim($this->wire('config')->paths->root, '/') . $url;
            }
        }

        return $paths;
    }

}
