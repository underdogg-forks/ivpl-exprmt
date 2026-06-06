<?php

/**
 * CI3 loader mock that actually loads application models.
 *
 * CI3's loader resolves model paths like 'settings/mdl_settings' by looking
 * in APPPATH/modules/{module}/models/{Model}.php and then attaching an
 * instance to the CI super-object. This mock replicates that lookup so that
 * existing tests written against CiTestCase don't need to be rewritten.
 *
 * Libraries are still stubbed — they return a MockLibrary that swallows any
 * method call, which lets constructor chains like
 * `$this->form_validation->CI = &$this` work without error.
 */
class MockLoader
{
    private object $ci;

    public function __construct(object $ci)
    {
        $this->ci = $ci;
    }

    // -----------------------------------------------------------------
    // CI Loader API
    // -----------------------------------------------------------------

    public function library(string|array $library, mixed $params = null, ?string $object_name = null): void
    {
        $libs = is_array($library) ? $library : [$library];

        foreach ($libs as $lib) {
            $name = $object_name ?? strtolower((string) $lib);
            if (! isset($this->ci->{$name})) {
                $this->ci->{$name} = new MockLibrary();
            }
        }
    }

    /**
     * Load a CI3 model and attach it to the CI super-object.
     *
     * Accepts the same path forms CI3 does:
     *   'mdl_settings'             → APPPATH/models/Mdl_settings.php
     *   'settings/mdl_settings'    → APPPATH/modules/settings/models/Mdl_settings.php
     *   'Module/Model', 'alias'    → attach as $CI->alias instead of $CI->mdl_model
     */
    public function model(string|array $model, ?string $object_name = null, bool $db_conn = false): void
    {
        $models = is_array($model) ? $model : [$model];

        foreach ($models as $path) {
            $this->loadSingleModel((string) $path, $object_name);
        }
    }

    public function helper(string|array $helper): void
    {
        $helpers = is_array($helper) ? $helper : [$helper];

        foreach ($helpers as $name) {
            $file = APPPATH . 'helpers/' . $name . '_helper.php';
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    public function database(mixed $params = '', bool $return = false, ?bool $query_builder = null): ?MockDB
    {
        if ($return) {
            return new MockDB();
        }

        return null;
    }

    public function view(string $view, array $vars = [], bool $return = false): string
    {
        return '';
    }

    public function config(string $file, bool $use_sections = false, bool $fail_gracefully = false): void {}

    public function initialize(): void {}

    // -----------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------

    private function loadSingleModel(string $path, ?string $alias): void
    {
        // Normalise separators
        $path = str_replace('\\', '/', trim($path, '/'));

        // Split into module + class segments
        $segments = explode('/', $path);
        $classSegment = array_pop($segments);
        $module = implode('/', $segments);

        // Determine property name on CI
        $propertyName = $alias ?? strtolower($classSegment);

        // Resolve the file path
        $file = $this->resolveModelFile($module, $classSegment);
        if ($file === null) {
            throw new \RuntimeException(
                "MockLoader: Failed to locate model file for class segment '{$classSegment}'" .
                ($module !== '' ? " in module '{$module}'" : '') .
                ". Attempted model path: '{$path}'."
            );
        }

        require_once $file;

        // CI3 model class names are case-insensitive but PSR-0 style.
        // Try exact case first, then ucfirst.
        $className = $this->resolveClassName($classSegment);
        if ($className === null) {
            throw new \RuntimeException(
                "MockLoader: Failed to resolve class name for segment '{$classSegment}'" .
                " after loading file '{$file}'. The class may not be defined in the file."
            );
        }

        $this->ci->{$propertyName} = new $className();
    }

    /**
     * Locate the model PHP file, searching the standard CI3 module paths.
     */
    private function resolveModelFile(string $module, string $class): ?string
    {
        $candidates = [];

        if ($module !== '') {
            // application/modules/{module}/models/{Class}.php
            $candidates[] = APPPATH . 'modules/' . $module . '/models/' . $class . '.php';
            // Also try ucfirst variant
            $candidates[] = APPPATH . 'modules/' . $module . '/models/' . ucfirst($class) . '.php';
        }

        // Bare models in application/models/
        $candidates[] = APPPATH . 'models/' . $class . '.php';
        $candidates[] = APPPATH . 'models/' . ucfirst($class) . '.php';

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Map the file segment (e.g. 'mdl_settings') to the PHP class name
     * that was defined after requiring the file.
     */
    private function resolveClassName(string $segment): ?string
    {
        $attempts = [
            $segment,
            ucfirst($segment),
            strtoupper(substr($segment, 0, 1)) . substr($segment, 1),
        ];

        // Mdl_ prefix variant (CI3 convention)
        if (strncasecmp($segment, 'mdl_', 4) === 0) {
            $suffix = substr($segment, 4);
            $attempts[] = 'Mdl_' . $suffix;
            $attempts[] = 'Mdl_' . ucfirst($suffix);
        }

        foreach ($attempts as $name) {
            if (class_exists($name, false)) {
                return $name;
            }
        }

        return null;
    }
}
