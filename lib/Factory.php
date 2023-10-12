<?php

namespace Wireframe;

use ProcessWire\ProcessWire;
use ProcessWire\HookEvent;
use ProcessWire\NullPage;
use ProcessWire\Page;
use ProcessWire\WireException;
use ProcessWire\Wireframe;

use function ProcessWire\wire;

/**
 * Factory class for Wireframe
 *
 * @version 0.3.2
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
     * The args array can be used to provide arguments that will be used while instantiating the component. If you
     * provide an associative array and the keys of said array match component class constructor argument names, the
     * order of the values doesn't matter - otherwise they will be used in order.
     *
     * Assuming that you have a component called "Card" and the constructor method for this component accepts "title"
     * and "summary" as arguments (`__constructor($title, $summary) { ... }`), these two would be equal:
     *
     * ```
     * <?= Wireframe::component('Card', ['summary' => 'Card summary', 'title' => 'Card title']) ?>
     * <?= Wireframe::component('Card', ['Card title', 'Card summary']) ?>
     * ```
     *
     * While this method returns an object, in the examples above we're making use of the fact that the __toString()
     * method of the Component class returns the rendered output of said component, so we don't have to specifically
     * call the render() method (`Wireframe::component('Card', [...])->render()`).
     *
     * Note: keep in mind that due to file system differences and the use of an autoloader, the name of the component
     * should *always* be treated as case sensitive. If actual class name is "Card" and the name is provided for this
     * method as "card", this will fail in some environments, resulting in an exception.
     *
     * @param string $component_name Component name.
     * @param array $args Arguments for the Component.
     * @return \Wireframe\Component Instance of the Component.
     *
     * @since 0.2.0 Added support for named arguments (Wireframe 0.12.0)
     * @since 0.1.0 (Wireframe 0.8.0)
     *
     * @throws WireException if Component class isn't found.
     */
    public static function component(string $component_name, array $args = []): \Wireframe\Component {

        // make sure that basic Wireframe features have been initialized
        if (!Wireframe::isInitialized(wire()->instanceID)) {
            wire()->modules->get('Wireframe')->initOnce();
        }

        $component_class = '\Wireframe\Component\\' . $component_name;

        if (!class_exists($component_class)) {
            throw new WireException(sprintf(
                'Component class %s was not found.',
                $component_class
            ), 404);
        }

        $reflector = new \ReflectionClass($component_class);

        if (empty($args) || array_key_exists(0, $args)) {
            // no args provided, or args looks like a numeric array; instantiate component using sequential args
            // (intended as a performant way to make an educated guess, not a foolproof associative array test)
            return $reflector->newInstanceArgs($args);
        }

        // get the component constructor method and its arguments; bail out early if constructor takes no args
        $constructor = $reflector->getConstructor();
        $constructor_args = $constructor->getParameters();
        if (empty($constructor_args)) {
            return $reflector->newInstanceArgs($args);
        }

        // build a modified args array based on component constructor parameter definitions and use that to
        // instantiate the component (named parameter support)
        $modified_args = [];
        foreach ($constructor_args as $constructor_arg_key => $constructor_arg) {
            if (empty($args)) {
                // if there are no more arguments left, break out of the loop
                break;
            }
            if (array_key_exists($constructor_arg->name, $args)) {
                // args has an argument matching the name of the constructor argument
                $modified_args[] = $args[$constructor_arg->name];
                unset($args[$constructor_arg->name]);
                continue;
            } else if (array_key_exists($constructor_arg_key, $args)) {
                // args has a numeric key in the position of the constructor argument
                $modified_args[] = $args[$constructor_arg_key];
                unset($args[$constructor_arg_key]);
                continue;
            }
            $modified_args[] = $constructor_arg->isDefaultValueAvailable() ? $constructor_arg->getDefaultValue() : null;
        }
        if (!empty($args)) {
            // args still has arguments left; merge these with the modified args array
            $modified_args = array_merge($modified_args, $args);
        }
        return $reflector->newInstanceArgs($modified_args);
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
     * @since 0.1.0 (Wireframe 0.8.0)
     *
     * @throws WireException if source param is of an unexpected type.
     * @throws WireException if args param is of an unexpected type.
     */
    public static function page($source, $args = []) {

        /** @var ProcessWire */
        $wire = $args['wire'] ?? ($source instanceof Page ? $source->getWire() ?? wire() : wire());

        // make sure that basic Wireframe features have been initialized
        if (!Wireframe::isInitialized($wire->instanceID)) {
            ($args['wireframe'] ?? $wire->modules->get('Wireframe'))->initOnce();
        }

        // get a page
        $page = null;
        if ($source instanceof Page) {
            $page = $source;
        } else if (\is_int($source) || \is_string($source)) {
            $page = $wire->pages->get($source);
        } else {
            throw new WireException(sprintf(
                'Invalid argument type supplied for param source (%s)',
                \gettype($source) . (\is_object($source) ? ' ' . \get_class($source) : '')
            ));
        }

        // bail out early if page wasn't found
        if (!$page->id) return $page;

        // parse arguments and merge with defaults
        if (\is_string($args)) {
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
        if (\is_array($args)) {
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
                \gettype($args) . (\is_object($args) ? ' ' . \get_class($args) : '')
            ));
        }

        // make sure that the page gets rendered with Wireframe
        $page->_wireframe_filename = $args['filename'] ?? 'wireframe';
        if (empty($args['filename']) && !empty($args['parent'])) {
            $page->_wireframe_filename = $args['parent']->template->altFilename;
        }
        if (empty($page->template->altFilename) || $page->template->altFilename != $page->_wireframe_filename) {

            // check if Page has hookable render method
            // note: this may need further adjustments to support other Page type objects
            $page_has_hookable_render_method = !$page instanceof \ProcessWire\RepeaterMatrixPage;

            if ($page_has_hookable_render_method) {

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
     * @since 0.1.0 (Wireframe 0.10.0)
     *
     * @throws WireException if partial name is invalid.
     * @throws WireException if namespaced partial name is invalid.
     * @throws WireException if partials path is undefined.
     * @throws WireException if partials path is invalid.
     */
    public static function partial(string $partial_name, array $args = null) {

        // validate partial name
        if (strpos($partial_name, '..') !== false) {
            throw new WireException(sprintf(
                'Partial name is invalid (%s)',
                $partial_name
            ));
        }

        // make sure that basic Wireframe features have been initialized
        if (!Wireframe::isInitialized(wire()->instanceID)) {
            wire()->modules->get('Wireframe')->initOnce();
        }

        // get root path for partials
        $config = wire('config');
        $partials_path = $config->paths->partials;
        if (strpos($partial_name, '::')) {
            list($namespace, $partial_name) = explode('::', $partial_name);
            if (empty($partial_name)) {
                throw new WireException(sprintf(
                    'Namespaced partial name is invalid (%s::%s)',
                    $namespace,
                    $partial_name
                ));
            }
            if (strlen($namespace) && \is_array($config->wireframe)) {
                $partials_path = $config->wireframe['view_namespaces'][$namespace] ?? '';
            }
        }
        if (empty($partials_path)) {
            throw new WireException('Partials path is undefined.');
        }
        if (strpos($partials_path, $config->paths->site) !== 0 || strpos($partials_path, '..') !== false) {
            throw new WireException(sprintf(
                'Partials path is invalid (%s). Specify an absolute path within site directory.',
                $partials_path
            ));
        }

        // get name and ext for partial
        $ext = '';
        if (strpos($partial_name, '.') !== false) {
            $ext_pos = strrpos($partial_name, '.');
            $ext = substr($partial_name, $ext_pos);
            $partial_name = substr($partial_name, 0, $ext_pos);
        } else {
            $ext = $config->_wireframeTemplateExtension;
            if (!$ext) {
                $ext = $config->templateExtension;
                if (\is_array($config->wireframe) && !empty($config->wireframe['ext'])) {
                    $ext = $config->wireframe['ext'];
                }
                if (mb_substr($ext, 0, 1) !== '.') {
                    $ext = '.' . $ext;
                }
            }
        }

        // instantiate and return/render partial
        $partial = new Partial([
            \ltrim($ext, '.') => $partials_path . $partial_name . $ext,
        ]);
        return \is_array($args) ? $partial->render($args) : $partial;
    }

}
