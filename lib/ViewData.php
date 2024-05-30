<?php

namespace Wireframe;

/**
 * Container for View Data
 *
 * This class is used as a container for (internal) view data.
 *
 * @internal This class is only intended for use within the Wireframe internals.
 *
 * @version 0.1.1
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
     * Component instance
     *
     * @var Component|null
     */
    public $component;

    /**
     * View file name
     *
     * @var string
     */
    public $view;

    /**
     * Layout file name
     *
     * @var string
     */
    public $layout;

    /**
     * Template name
     *
     * @var string
     */
    public $template;

    /**
     * Path to the views directory
     *
     * @var string
     */
    public $views_path;

    /**
     * Path to the layouts directory
     *
     * @var string
     */
    public $layouts_path;

    /**
     * Rendering context
     *
     * @var string
     */
    public $context;

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
