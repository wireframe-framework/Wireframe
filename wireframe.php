<?php namespace ProcessWire;

/**
 * Wireframe bootstrap file
 *
 * Copy this file to the /site/templates/ directory and modify it to fit your needs. You can provide
 * an associative array as a parameter to the render() method and use its contents as variables in
 * in your layout, view, and partial files.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */

// init Wireframe
$wireframe = $modules->get('Wireframe');
$wireframe->init();

// render the page
echo $wireframe->render([
    // 'site_name' => 'Lorem Ipsum',
    // 'lang' => 'en',
    // 'home' => $pages->get(1),
]);
