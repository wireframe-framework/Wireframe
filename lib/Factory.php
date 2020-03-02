<?php

namespace Wireframe;

use ProcessWire\{HookEvent, NullPage, Page, WireException, Wireframe};
use function ProcessWire\wire;

/**
 * Factory class for Wireframe
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Factory {

    /**
     * This class should never be instantiated.
     */
    private function __construct() {}

    /**
     * Static getter (factory) method for Components
     *
     * Note: keep in mind that due to file system differences and the use of an autoloader, the name of the component
     * should *always* be treated as case sensitive. If actual class name is `Card` and the name is provided for this
     * method as `card`, this will fail in some environments, resulting in an exception.
     *
     * @param string $component_name Component name.
     * @param array $args Arguments for the Component.
     * @return \Wireframe\Component Instance of the Component.
     *
     * @since 0.8.0
     *
     * @throws WireException if Component class isn't found.
     */
    public static function component(string $component_name, array $args = []): \Wireframe\Component {

        $component = null;
        $component_class = '\Wireframe\Component\\' . $component_name;

        if (class_exists($component_class)) {
            $reflector = new \ReflectionClass($component_class);
            $component = $reflector->newInstanceArgs($args);
        } else {
            throw new WireException(sprintf(
                'Component class %s was not found.',
                $component_class
            ));
        }

        return $component;
    }

    /**
     * Static getter (factory) method for Pages
     *
     * This utility method can be used to render Page objects via Wireframe even if they don't have the expected
     * altFilename Template setting in place. Particularly useful for cases where you don't want a page to be publicly
     * viewable, but you still want to render it manually on some occasion (e.g. content that is only shown in lists.)
     *
     * Note that this method will accept different types of parameters, and the return value also depends on provided
     * parameters. Basic usage:
     *
     * ```
     * <?= Wireframe::page('id=1234', ['layout' => null, 'view' => 'list-item'])->render() ?>
     * ```
     *
     * Or a shorter version with string provided for args:
     *
     * ```
     * <?= Wireframe::page('id=1234', 'list-item') ?>
     * ```
     *
     * @param int|string|Page $source Page object, Page ID (integer), or selector string (string).
     * @param array|string $args Optional arguments. If the value is a string, it is assumed to be the name of a view
     *                           file and the default value for layout is set to `null` – except if the string contains
     *                           a forward slash in it, in which case it is assumed to hold both layout and view file
     *                           names ([layout]/[view]). If the value is an array, following options are supported:
     *                           - parent [Page]: the page on/for which current page is being rendered
     *                           - wireframe [Wireframe]: an instance of the Wireframe module
     *                           - wire [ProcessWire]: an instance of ProcessWire, defaults to Page's Wire instance if
     *                             $source is a Page object, or the Wire instance returned by wire() method if not.
     *                           - filename [string]: template file, defaults to 'wireframe'
     *                           - ext [string]: extension for the template file, defaults to '.php'
     *                           - layout [string]: layout to render the page with, defaults to 'default'
     *                           - view [string]: view file to render the page with, defaults to 'default'
     *                           - viewTemplate [string]: view template to render the page with, defaults to null
     *                           - render [bool]: defines if we should return rendered content, defaults to 'false'
     * @return string|Page|NullPage Returns string if 'render' option was 'true' **or** the args param was a string,
     *                              otherwise returns a Page, or NullPage (if page wasn't found).
     *
     * @since Wireframe 0.8.0
     *
     * @throws WireException if source param is of an unexpected type.
     * @throws WireException if args param is of an unexpected type.
     */
    public static function page($source, $args = []) {

        // ProcessWire instance
        $wire = $args['wire'] ?? ($source instanceof Page ? $source->getWire() : wire());

        // get a page
        $page = null;
        if ($source instanceof Page) {
            $page = $source;
        } else if (is_int($source) || is_string($source)) {
            $page = $wire->pages->get($source);
        } else {
            throw new WireException(sprintf(
                'Invalid argument type supplied for param source (%s)',
                gettype($source) . (is_object($source) ? ' ' . get_class($source) : '')
            ));
        }

        // bail out early if page wasn't found
        if ($page instanceof NullPage) return $page;

        // parse arguments and merge with defaults
        if (is_string($args)) {
            $args = [
                'layout' => null,
                'view' => $args,
                'render' => true,
            ];
            if (strpos($args['view'], '/') !== false) {
                $args_parts = explode('/', $args['view']);
                $args = [
                    'layout' => $args_parts[0],
                    'view' => $args_parts[1],
                    'render' => true,
                ];
            }
        }
        if (is_array($args)) {
            $args = array_merge([
                'parent' => null,
                'wireframe' => null,
                'wire' => $wire,
                'filename' => null,
                'ext' => '.php',
                'layout' => 'default',
                'view' => 'default',
                'viewTemplate' => null,
                'render' => false,
            ], $args);
        } else {
            throw new WireException(sprintf(
                'Invalid argument type supplied for param args (%s)',
                gettype($args) . (is_object($args) ? ' ' . get_class($args) : '')
            ));
        }

        // make sure that the page gets rendered with Wireframe
        $page->_wireframe_filename = $args['filename'] ?? 'wireframe';
        if (empty($args['filename']) && !empty($args['parent'])) {
            $page->_wireframe_filename = $args['parent']->template->altFilename;
        }
        if (empty($page->template->altFilename) || $page->template->altFilename != $page->_wireframe_filename) {
            // due to the way PageRender works (calling Page::output(true) internally), a template file check occurs
            // even if the filename argument is used; we detect this situation in order to avoid unnecessary errors.
            $page_has_no_file = empty($page->template->altFilename) && (empty($page->template->filename) || !is_file($page->template->filename));
            $page->addHookBefore('render', function(HookEvent $event) use ($args, $page_has_no_file) {
                if (!empty($event->object->_wireframe_filename)) {
                    $options = $event->arguments[0] ?? [];
                    if (empty($options['filename'])) {
                        $options['filename'] = $event->object->_wireframe_filename . $args['ext'];
                        $event->arguments(0, $options);
                    }
                    if ($page_has_no_file) {
                        $event->object->template->_altFilename = $event->object->template->altFilename;
                        $event->object->template->altFilename = $event->object->_wireframe_filename;
                    }
                }
            });
            if ((!empty($args['wireframe']) && !empty($args['parent'])) || $page_has_no_file) {
                $page->addHookAfter('render', function(HookEvent $event) use ($args, $page_has_no_file) {
                    if (!empty($event->object->_wireframe_page)) {
                        $args['wireframe']->page = $args['parent'];
                    }
                    if ($page_has_no_file) {
                        $event->object->template->altFilename = $event->object->template->_altFilename;
                    }
                });
            }
        }

        // make sure that basic Wireframe features have been intiialized
        if (!Wireframe::isInitialized($wire->instanceID)) {
           ($args['wireframe'] ?? $wire->modules->get('Wireframe'))->initOnce();
        }

        // set view, layout, and view template
		if ($args['layout'] != 'default') $page->setLayout($args['layout']);
		if ($args['view'] != 'default') $page->setView($args['view']);
        if ($args['viewTemplate'] != null) $page->setViewTemplate($args['viewTemplate']);

        return $args['render'] ? $page->render() : $page;
    }

    /**
     * Static getter (factory) method for partials
     *
     * @param string $partial_name Name of the partial. If the name contains a dot, it is assumed to include a file extension.
     * @param array|null $args Optional arguments for rendering the Partial. If provided, the Partial is automatically rendered.
     * @return Partial|string Instance of the Partial, or rendered markup if $args array was provided.
     *
     * @since Wireframe 0.10.0
     *
     * @throws WireException if partials path isn't found from config.
     */
    public static function partial(string $partial_name, array $args = null) {
		$config = wire('config');
        $partials_path = $config->paths->partials;
        if (empty($partials_path)) {
            throw new WireException('Partials path not found from config.');
        }
		$ext = '';
        if (\strpos($partial_name, '.') === false) {
			$ext = $config->_wireframeTemplateExtension;
			if (!$ext) {
				$ext = $config->templateExtension;
				if (\is_array($config->wireframe) && !empty($config->wireframe->ext)) {
					$ext = $config->wireframe->ext;
				}
			}
        }
		$partial = new Partial([
			\ltrim($ext, '.') => $partials_path . $partial_name . $ext,
		]);
		return \is_array($args) ? $partial->render($args) : $partial;
    }

}
