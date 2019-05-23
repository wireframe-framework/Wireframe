<?php namespace ProcessWire;

/**
 * wireframe bootstrap file
 *
 * Copy this file to the /site/templates/ directory and modify it to fit your needs. You can provide
 * an associative array as a parameter to the render() method and use its contents as variables in
 * in your layout files and view scripts.
 *
 * @version 0.0.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

// init wireframe and render the page
$wireframe = $modules->get('wireframe');
$wireframe->init();
echo $wireframe->render();
