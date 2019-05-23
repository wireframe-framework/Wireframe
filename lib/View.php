<?php

namespace wireframe;

/**
 * wireframe View component
 *
 * This class is essentially a wrapper for the ProcessWire TemplateFile class,
 * with some additional features and the wireframe namespace.
 *
 * @version 0.0.5
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 * @todo bodyClasses helper (template, delegated-template, others?
 */
class View extends \ProcessWire\TemplateFile {

    /**
     * Controller instance
     *
     * @var Controller
     */
    protected $controller;

    /**
     * Local data, made available to layouts and view scripts
     *
     * @var array
     */
    protected $data = [];

    /**
     * View placeholders
     *
     * @var ViewPlaceholders
     */
    protected $placeholders;

    /**
     * Partials
     *
     * @var stdClass
     */
    protected $partials;

    /**
     * View script name
     *
     * @param string
     */
    protected $script;

    /**
     * Layout name
     *
     * @param string
     */
    protected $layout;

    /**
     * Template name
     *
     * @param string
     */
    protected $template;

    /**
     * PHP's magic __get() method
     *
     * This method provides access to protected/private properties of current
     * instance, variables in the data array of current instance or variables
     * of the Controller instance.
     *
     * @param string $key Name of the Controller method
     * @return mixed Value of the key or null
     */
    public function __get($key) {
        $value = $this->$key ?? null;
        if (!$value) {
            $value = $this->get($key);
        }
        if (!$value && $this->controller) {
            $value = $this->controller->$key;
        }
        return $value;
    }

    /**
     * Setter method for Controller
     *
     * @param Controller|null Controller instance or null
     * @return View Self-reference
     */
    public function setController(?Controller $controller): View {
        $this->controller = $controller;
        return $this;
    }

    /**
     * Setter method for layout
     *
     * @param string|null
     * @return View Self-reference
     */
    public function setLayout(?string $layout): View {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Setter method for view script
     *
     * @param string|null
     * @return View Self-reference
     */
    public function setScript(?string $script): View {
        $this->script = $script;
        return $this;
    }

    /**
     * Setter method for template
     *
     * @param string|null
     * @return View Self-reference
     */
    public function setTemplate(?string $template): View {
        $this->template = $template;
        return $this;
    }

    /**
     * Setter method for placeholders
     *
     * @param ViewPlaceholders|null wireframe ViewPlaceholders instance or null
     * @return View Self-reference
     */
    public function setPlaceholders(?ViewPlaceholders $placeholders): View {
        $this->placeholders = $placeholders;
        return $this;
    }

    /**
     * Setter method for partials
     *
     * @param \stdClass|null Object containing partial paths or null
     * @return View Self-reference
     */
    public function setPartials(?\stdClass $partials): View {
        $this->partials = $partials;
        return $this;
    }

    /**
     * Setter method for the data array
     *
     * @param array $data Data array
     * @return View Self-reference
     */
    public function setData(array $data = []): View {
        $this->data = $data;
        return $this;
    }

    /**
     * Add new data
     *
     * @param array $data Data array
     * @return View Self-reference
     */
    public function addData(array $data = []): View {
        $this->data = array_merge(
            $this->data,
            $data
        );
        return $this;
    }
    
    /**
     * Get an array of all variables accessible (locally scoped) to layouts and view scripts
     *
     * We're overriding parent class method here so that we can add some additional variables to the
     * mix. Essentially all protected or private properties we want layouts and view scripts to see
     * need to be included here.
     *
     * @return array
     */
    public function getArray(): array {
        return array_merge(parent::getArray(), [
            'placeholders' => $this->placeholders,
            'partials' => $this->partials,
        ]);
    }

    /**
     * General purpose getter method
     *
     * @param string $key Name of the variable
     * @return mixed Value of the key or null
     */
    function get($key) {
        if (isset($this->data[$key])) {
            return $this->data[$key];
        }
        return parent::get($key);
    }

    /**
     * General purpose setter method
     *
     * @param string $key Name of the variable
     * @param mixed $value Value for the variable
     * @return View Self-reference
     */
    public function set($key, $value): View {
        $setter = 'set' . ucfirst($key);
        if (method_exists($this, $setter) && is_callable($this, $setter)) {
            return $this->{$setter}($value);
        }
        return parent::set($key, $value);
    }

    /**
     * Magic __set() method as an alias for set()
     *
     * @param string $key Name of the variable
     * @param mixed $value Value for the variable
     * @return View Self-reference
     */
    public function __set($key, $value): View {
        return $this->set($key, $value);
    }

}
