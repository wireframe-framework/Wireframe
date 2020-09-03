<?php

namespace Wireframe;

/**
 * Container for View Data
 *
 * This class is used as a container for (internal) view data.
 *
 * @internal This class is only intended for use within the Wireframe internals.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
final class ViewData {

    /**
     * Controller instance
     *
     * @var Controller|null
     */
    public $controller;

    /**
     * View file name
     *
     * @param string
     */
    public $view;

    /**
     * Layout file name
     *
     * @param string
     */
    public $layout;

    /**
     * Template name
     *
     * @param string
     */
    public $template;

    /**
     * Path to the views directory
     *
     * @var string
     */
    public $views_path;

    /**
     * The extension for view, layout, and partial files
     *
     * @var string
     */
    public $ext;

    /**
     * Current Page object
     *
     * @var Page
     */
    public $page;

}
