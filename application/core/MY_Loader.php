<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// load the MX_Loader class
require APPPATH . 'third_party/MX/Loader.php';

/**
 * Enhanced CI3/MX Loader with PSR-4 namespaced class support.
 *
 * Extends MX_Loader to transparently handle fully-qualified class names
 * (e.g. `App\Services\Clients\ClientsService`) alongside the legacy
 * CI underscore-suffix convention.
 *
 * Namespaced classes are resolved via Composer PSR-4 autoloading; the loader
 * instantiates them and binds them to the CI super-object using the short class
 * name (lower-cased) as the property name, matching CI convention.
 *
 * PSR-4 naming takes precedence: any class name containing `\` is handled
 * by this loader before falling through to the legacy MX/CI path.
 */
#[AllowDynamicProperties]
class MY_Loader extends MX_Loader
{
    /**
     * Load a library — supports fully-qualified PSR-4 class names.
     *
     * @param  string|array $library
     * @param  array|null   $params
     * @param  string|null  $object_name
     * @return $this
     */
    public function library($library, $params = null, $object_name = null)
    {
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                is_int($key)
                    ? $this->library($value)
                    : $this->library($key, null, $value);
            }

            return $this;
        }

        if ($this->isNamespacedClassName($library)) {
            return $this->loadNamespacedClass($library, $params, $object_name, false);
        }

        return parent::library($library, $params, $object_name);
    }

    /**
     * Load a model — supports fully-qualified PSR-4 class names.
     *
     * @param  string|array $model
     * @param  string|null  $object_name
     * @param  bool|string  $connect
     * @return $this
     */
    public function model($model, $object_name = null, $connect = false)
    {
        if (is_array($model)) {
            foreach ($model as $key => $value) {
                is_int($key)
                    ? $this->model($value, null, $connect)
                    : $this->model($key, $value, $connect);
            }

            return $this;
        }

        if ( ! $this->isNamespacedClassName($model)) {
            return parent::model($model, $object_name, $connect);
        }

        // Handle database connection for namespaced models when requested
        if ($connect !== false && ! class_exists('CI_DB', false)) {
            $this->database($connect === true ? '' : $connect, false, true);
        }

        return $this->loadNamespacedClass($model, null, $object_name, true);
    }

    /**
     * Load a namespaced Core\ service directly (convenience wrapper).
     *
     * @param  string     $className   FQCN, e.g. Core\Services\Clients\ClientsService
     * @param  mixed|null $params
     * @param  string|null $object_name
     * @return $this
     */
    public function service(string $className, $params = null, ?string $object_name = null)
    {
        return $this->loadNamespacedClass($className, $params, $object_name, false);
    }

    // -------------------------------------------------------------------------
    // Protected helpers
    // -------------------------------------------------------------------------

    /**
     * Returns true when the class name contains a namespace separator.
     *
     * PSR-4 classes are always handled before legacy CI/MX paths.
     */
    protected function isNamespacedClassName($className): bool
    {
        return is_string($className) && str_contains($className, '\\');
    }

    /**
     * Instantiate a PSR-4 class and attach it to the CI super-object.
     *
     * @param  string      $className       FQCN
     * @param  mixed|null  $params          Optional constructor argument
     * @param  string|null $object_name     Custom property name on CI
     * @param  bool        $registerAsModel Whether to push name into _ci_models[]
     * @return $this
     */
    protected function loadNamespacedClass(
        string $className,
        $params = null,
        ?string $object_name = null,
        bool $registerAsModel = false,
    ) {
        if ( ! class_exists($className)) {
            show_error('Unable to load the requested class: ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8'));
        }

        $segments = explode('\\', $className);
        $short    = end($segments);
        $alias    = $object_name ?? lcfirst($short);

        if (isset(CI::$APP->{$alias})) {
            if ($object_name === null) {
                log_message(
                    'debug',
                    'Namespaced class ' . $className . ' not loaded: alias "' . $alias . '" already exists.'
                    . ' Use $object_name to specify a unique alias.',
                );
            }

            return $this;
        }

        CI::$APP->{$alias} = $params === null ? new $className() : new $className($params);

        $this->_ci_classes[mb_strtolower($short)] = $alias;

        if ($registerAsModel && ! in_array($alias, $this->_ci_models, true)) {
            $this->_ci_models[] = $alias;
        }

        return $this;
    }
}

