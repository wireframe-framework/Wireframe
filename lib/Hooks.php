<?php

namespace Wireframe;

use \ProcessWire\HookEvent;
use \ProcessWire\Wireframe;

/**
 * Wireframe Hooks
 *
 * @internal This class is only intended for use within the Wireframe internals.
 *
 * @version 0.1.1
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Hooks extends \ProcessWire\Wire {

    /**
     * A local instance of the Wireframe module
     *
     * @var Wireframe
     */
    protected $wireframe;

    /**
     * Constructor
     *
     * @param Wireframe $wireframe
     */
    public function __construct(Wireframe $wireframe) {
        $this->wireframe = $wireframe;
    }

    /**
     * Init method
     */
    public function init() {

        // make View properties available in TemplateFile
        $this->addHookBefore('TemplateFile::render', $this, 'viewProps');

        // helper methods for getting or setting page layout
        $this->addHookMethod('Page::layout', $this, 'pageLayout');
        $this->addHookMethod('Page::getLayout', $this, 'pageLayout');
        $this->addHookMethod('Page::setLayout', $this, 'pageLayout');

        // helper methods for getting or setting page view
        $this->addHookMethod('Page::view', $this, 'pageView');
        $this->addHookMethod('Page::getView', $this, 'pageView');
        $this->addHookMethod('Page::setView', $this, 'pageView');

        // helper methods for getting or setting page view template
        $this->addHookMethod('Page::viewTemplate', $this, 'pageViewTemplate');
        $this->addHookMethod('Page::getViewTemplate', $this, 'pageViewTemplate');
        $this->addHookMethod('Page::setViewTemplate', $this, 'pageViewTemplate');

        // helper methods for getting or setting page controller
        $this->addHookMethod('Page::getController', $this, 'pageController');
        $this->addHookMethod('Page::setController', $this, 'pageController');
    }

    /**
     * Make View properties directly available in TemplateFile
     *
     * Note: this is primarily useful for field rendering.
     *
     * @param HookEvent $event
     */
    protected function viewProps(HookEvent $event) {
        $view = $event->object->view;
        if (!$view instanceof View || $event->object instanceof View) {
            // View doesn't exist or we're currently rendering a Wireframe View
            return;
        }
        $event->object->setArray(array_merge(
            array_diff_key($view->data(), $event->object->data()),
            array_filter([
                'page' => $event->object->page, // used by field rendering
                'value' => $event->object->value, // used by field rendering
                'field' => $event->object->field, // used by field rendering
                'partials' => $view->partials,
                'placeholders' => $view->placeholders,
            ])
        ));
    }

    /**
     * This method is used by Page::layout(), Page::getLayout(), and Page::setLayout()
     *
     * Example use with combined getter/setter method:
     *
     * ```
     * The layout for current page is "<?= $page->layout() ?>".
     * <?= $page->layout('another-layout')->render() ?>
     * ```
     *
     * Example use with dedicated getter/setter methods:
     *
     * ```
     * The layout for current page is "<?= $page->getLayout() ?>".
     * <?= $page->setLayout('another-layout')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     */
    protected function pageLayout(HookEvent $event) {
        if ($event->method === 'getLayout' || $event->method === 'layout' && !isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_layout;
        } else {
            $event->object->_wireframe_layout = $event->arguments[0] ?? '';
            $event->object = \Wireframe\Factory::page($event->object, [
                'wireframe' => $this->wireframe,
            ]);
            $event->return = $event->object;
        }
    }

    /**
     * This method is used by Page::view(), Page::getView(), and Page::setView()
     *
     * Example use with combined getter/setter method:
     *
     * ```
     * The view for current page is "<?= $page->view() ?>".
     * <?= $page->view('json')->render() ?>
     * ```
     *
     * Example use with dedicated getter/setter methods:
     *
     * ```
     * The view for current page is "<?= $page->getView() ?>".
     * <?= $page->setView('json')->render() ?>
     * ```
     *
     * It's also possible to use this method as a shortcut for defining both view and view template
     * at the same time. These two are equal:
     *
     * ```
     * <?= $page->setViewTemplate('home')->setView('json')->render() ?>
     * <?= $page->setView('home/json')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     */
    protected function pageView(HookEvent $event) {
        if ($event->method === 'getView' || $event->method === 'view' && !isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_view;
        } else {
            $view = $event->arguments[0] ?? '';
            if ($view !== '' && strpos($view, '/') !== false) {
                list($view_template, $view) = explode('/', $view);
                $event->object->_wireframe_view_template = $view_template;
            }
            $event->object->_wireframe_view = $view;
            $event->object = \Wireframe\Factory::page($event->object, [
                'wireframe' => $this->wireframe,
            ]);
            $event->return = $event->object;
        }
    }

    /**
     * This method is used by Page::viewTemplate(), Page::getViewTemplate(), and Page::setViewTemplate()
     *
     * When used as a getter, this method always returns template name (string).
     *
     * Example use with combined getter/setter method:
     *
     * ```
     * The view template for current page is "<?= $page->viewTemplate() ?>".
     * <?= $page->viewTemplate('home')->render() ?>
     * ```
     *
     * Example use with dedicated getter/setter methods:
     *
     * ```
     * The view template for current page is "<?= $page->getViewtemplate() ?>".
     * <?= $page->setViewTemplate('home')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     */
    protected function pageViewTemplate(HookEvent $event) {
        if ($event->method === 'getViewTemplate' || $event->method === 'viewTemplate' && !isset($event->arguments[0])) {
            $event->return = $event->object->_wireframe_view_template ?: (string) $event->object->template;
        } else {
            $event->object->_wireframe_view_template = empty($event->arguments[0]) ? '' : (string) $event->arguments[0];
            $event->object = \Wireframe\Factory::page($event->object, [
                'wireframe' => $this->wireframe,
            ]);
            $event->return = $event->object;
        }
    }

    /**
     * This method is used by Page::getController() and Page::setController()
     *
     * Example use:
     *
     * ```
     * The controller for current page is "<?= $page->getController() ?>".
     * <?= $page->setController('home')->render() ?>
     * ```
     *
     * @param HookEvent $event The ProcessWire HookEvent object.
     */
    protected function pageController(HookEvent $event) {
        if ($event->method === 'getController') {
            $controller = $event->object->_wireframe_controller ?: null;
            if ($controller === null) {
                $controller = $this->wireframe->getController($event->object);
            }
            $event->return = $controller;
        } else {
            $controller = $event->arguments[0] ?? '';
            if ($controller != '' && !$controller instanceof \Wireframe\Controller) {
                $controller = $this->wireframe->getController($event->object, $controller);
            }
            $event->object->_wireframe_controller = $controller;
            $event->object = \Wireframe\Factory::page($event->object, [
                'wireframe' => $this->wireframe,
            ]);
            $event->return = $event->object;
        }
    }

}
