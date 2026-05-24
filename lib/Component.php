<?php

namespace Wireframe;

/**
 * Abstract base implementation for Component objects
 *
 * By extending ProcessWire's WireData we get certain benefits, including the ability to easily set
 * up the array passed as context when rendering the component. This does, though, mean that if you
 * define class properties and set values to those (in order to make use of strong typing etc.), we
 * can't automatically pass such values to the component view for rendering.
 *
 * In aforementioned use case you should override the getData() method and return the data you want
 * the render process to have access to.
 *
 * @version 0.5.0
 * @author Teppo Koivula <teppo@wireframe-framework.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Component extends \ProcessWire\WireData {

    use EventListenerTrait;
    use RendererTrait;
    use MethodPropsTrait;

    /**
     * Default view file for the Component
     *
     * @var string
     */
    private $view = 'default';

    /**
     * Local instance of the Wireframe module
     *
     * @var \ProcessWire\Wireframe|null
     */
    private $_wireframe = null;

    /**
     * Cache rendered output of this component.
     *
     * Set to enable persistent caching of the markup returned by render(). Accepted values:
     * - false (default): no caching
     * - int: TTL in seconds (passed to WireCache::save)
     * - string: WireCache expire constant name ('expireSave', 'expireNever', 'expireDaily', etc.)
     *           or a selector string accepted by WireCache
     *
     * Components opting into render-output caching MUST NOT register scripts or styles via
     * $config->scripts->add() / $config->styles->add() inside render(): those calls would only
     * fire on cache miss. Move asset registration into a separate, always-running hook.
     *
     * @var int|string|bool
     */
    protected $render_cache = false;

    /**
     * Whether the rendered cache key should vary by current page id.
     *
     * Most components produce identical markup regardless of the current page; default is false.
     * Enable for components whose output depends on page-specific context (breadcrumbs, etc.).
     *
     * @var bool
     */
    protected $render_cache_vary_by_page = false;

    /**
     * Whether the rendered cache key should vary by user roles, groups and language.
     *
     * Default is true: output is assumed to be access-controlled and locale-aware. Set to false
     * for components whose output is identical for every visitor regardless of role or language.
     * Note: this varies by audience (roles + groups + language), not by individual user ID —
     * users with the same roles/groups/language share a cache entry.
     *
     * @var bool
     */
    protected $render_cache_vary_by_user = true;

    /**
     * PHP magic getter method
     *
     * Note: __get() is only called when trying to access a non-existent or non-local and non-public property.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name) {
        return $this->getMethodProp($name, 'component');
    }

    /**
     * Render markup for the Component
     *
     * If $render_cache is set, the rendered markup is cached using WireCache. The cache key is
     * built by getRenderCacheKey() from the component's class name, its data (constructor args
     * stored as WireData), and optionally the current page and user context.
     *
     * @return string Rendered Component markup.
     */
    public function ___render(): string {
        if (!$this->render_cache) {
            return $this->renderView();
        }
        return $this->wire('cache')->get(
            $this->getRenderCacheKey(),
            $this->resolveRenderCacheExpire(),
            function() {
                return $this->renderView();
            }
        );
    }

    /**
     * Build the cache key used for rendered output caching.
     *
     * Hook this method to customize the key shape per component or globally. Default key
     * incorporates the component class name, a hash of getRenderCacheArgs(), and (when enabled
     * via class properties) user roles/groups/language and current page id.
     *
     * @return string
     */
    protected function ___getRenderCacheKey(): string {
        $class_short = substr(strrchr(static::class, '\\'), 1) ?: static::class;
        $parts = [
            'Wireframe/RenderCache',
            $class_short,
            md5(serialize($this->getRenderCacheArgs())),
        ];
        if ($this->render_cache_vary_by_user) {
            $user = $this->wire('user');
            $parts[] = $user->roles->implode('+', 'id');
            $parts[] = $user->user_groups
                ? $user->user_groups->implode('+', 'id')
                : '';
            $parts[] = (string) $user->language;
        }
        if ($this->render_cache_vary_by_page) {
            $parts[] = (string) $this->wire('page')->id;
        }
        return implode('/', $parts);
    }

    /**
     * Get the args used to identify this component instance for cache-key purposes.
     *
     * Defaults to getData() with the auto-injected `partials` key removed (the Partials object
     * is an environment-provided service, not part of component identity). Override in components
     * that store identity in private/protected properties or that need to normalize complex args
     * (Pages, Pagefiles) into stable scalars before hashing.
     *
     * @return array
     */
    protected function ___getRenderCacheArgs(): array {
        $args = $this->getData();
        unset($args['partials']);
        return $args;
    }

    /**
     * Resolve the WireCache expire value for rendered output caching.
     *
     * @return int|string
     */
    private function resolveRenderCacheExpire() {
        if (\is_int($this->render_cache)) {
            return $this->render_cache;
        }
        if (\is_string($this->render_cache)) {
            $constant = '\\ProcessWire\\WireCache::' . $this->render_cache;
            if (\defined($constant)) {
                return \constant($constant);
            }
            // Pass through other string values (selectors, etc.) to WireCache as-is.
            return $this->render_cache;
        }
        return \ProcessWire\WireCache::expireSave;
    }

    /**
     * Render JSON for the Component
     *
     * By default this method returns nothing (null). If you want a Component to return values for
     * JSON API requests you need to implement this method in the Component class. Basic example:
     *
     * ```
     * public function renderJSON(): ?string {
     *     return json_encode($this->getData());
     * }
     * ```
     *
     * @return string|null JSON output.
     */
    public function ___renderJSON(): ?string {
        return null;
    }

    /**
     * Render markup for the Component using a separate Component View file
     *
     * @param string|null $view Component view file name (optional). If the name contains slash, it
     *                          will be assumed to contain a path and file name starting from the
     *                          components directory.
     * @return string Rendered Component markup.
     */
    public function ___renderView(string $view = null): string {
        $view = $view ?? $this->getView();
        if (!empty($view)) {

            // view prefix
            $view_prefix = $this->getViewPrefix();
            $view_prefix_is_strict = $view_prefix != '' && strpos($view_prefix, '!') === 0;
            if ($view_prefix_is_strict) {
                $view_prefix = substr($view_prefix, 1);
            }

            // view data
            $view_root = \dirname((new \ReflectionClass($this))->getFileName());
            $view_file = (strpos($view, '/') ? '' : '/' . $this->className())
                . '/'
                . ($view_prefix == '' ? $view : ltrim($view_prefix, '/') . $view);
            $view_file_without_prefix = $view_prefix == ''
                ? ''
                : (strpos($view, '/') ? '' : '/' . $this->className()) . '/' . $view;

            // attempt to render markup using a renderer
            $renderer = $this->getRenderer();
            if ($renderer) {
                /**
                 * @noinspection PhpUndefinedMethodInspection
                 * @disregard P1013 as it's a false positive; renderers are expected to have getExt() method
                 */
                $view_ext = '.' . ltrim($renderer->getExt(), '.');
                if (\is_file($view_root . $view_file . $view_ext)) {
                    /**
                     * @noinspection PhpUndefinedMethodInspection
                     * @disregard P1013 as it's a false positive; renderers are expected to have getExt() method
                     */
                    return $renderer->render('component', ltrim($view_file, '/') . $view_ext, $this->getData());
                } else if (!$view_prefix_is_strict && $view_file_without_prefix != '' && \is_file($view_root . $view_file_without_prefix . $view_ext)) {
                    /**
                     * @noinspection PhpUndefinedMethodInspection
                     * @disregard P1013 as it's a false positive; renderers are expected to have getExt() method
                     */
                    return $renderer->render('component', ltrim($view_file_without_prefix, '/') . $view_ext, $this->getData());
                }
            }

            // fall back to built-in PHP template renderer
            $fallback_filename = \is_file($view_root . $view_file . '.php')
                ? $view_root . $view_file . '.php'
                : (
                    !$view_prefix_is_strict && $view_file_without_prefix != '' && \is_file($view_root . $view_file_without_prefix . '.php')
                        ? $view_root . $view_file_without_prefix . '.php'
                        : null
                );
            if ($fallback_filename !== null) {
                $component_view = $this->wire(new ComponentView($this));
                $component_view->data($this->getData());
                $component_view->setFilename($fallback_filename);
                return $component_view->render();
            }
        }
        return '';
    }

    /**
     * Get data for the Component
     *
     * Override this method if you want to have full control over the data that is used while rendering
     * the component. The method should return an associative array.
     *
     * The default implementation also injects a `partials` key (lazily resolved from the active
     * layout View) so that component views can use the same `<?= $partials->name() ?>` idiom as
     * regular Wireframe views and layouts.
     *
     * @return array Associative array of data.
     */
    public function getData(): array {
        $data = parent::data(null, null);
        if (!\array_key_exists('partials', $data)) {
            $wireframe = $this->wire('modules')->get('Wireframe');
            $data['partials'] = $wireframe && $wireframe->view ? $wireframe->view->partials : null;
        }
        return $data;
    }

    /**
     * Set the view file name for the Component
     *
     * @param string $view View file name.
     * @return Component Self-reference.
     */
    final public function setView(string $view): Component {
        $this->emit('setView', ['view' => $view]);
        $this->view = $view;
        return $this;
    }

    /**
     * Get the view file name for the Component
     *
     * @return string View file name.
     */
    final public function getView(): string {
        return $this->view;
    }

    /**
     * Get view prefix for the Component
     *
     * @return string View prefix.
     */
    final protected function getViewPrefix(): string {
        if ($this->_wireframe === null) {
            $this->_wireframe = $this->wire('modules')->get('Wireframe');
        }
        return $this->_wireframe->getViewPrefix();
    }

    /**
     * Return rendered Component
     *
     * @return string
     */
    public function __toString() {
        return $this->render();
    }

}
