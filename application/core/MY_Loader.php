<?php

if ( ! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

// load the MX_Loader class
require APPPATH . 'third_party/MX/Loader.php';

#[AllowDynamicProperties]
class MY_Loader extends MX_Loader
{
    public function library($library, $params = null, $object_name = null)
    {
        if (is_array($library)) {
            foreach ($library as $key => $value) {
                if (is_int($key)) {
                    $this->library($value);
                } else {
                    $this->library($key, null, $value);
                }
            }

            return $this;
        }

        if (is_string($library) && strpos($library, '\\') !== false) {
            return $this->loadNamespacedLibrary($library, $params, $object_name);
        }

        return parent::library($library, $params, $object_name);
    }

    protected function loadNamespacedLibrary($fqcn, $params = null, $object_name = null)
    {
        if ( ! class_exists($fqcn)) {
            show_error('Unable to load the requested class: ' . $fqcn);
        }

        $segments = explode('\\', $fqcn);
        $class    = end($segments);
        $_alias   = $object_name === null ? lcfirst($class) : mb_strtolower($object_name);

        if (isset(CI::$APP->{$_alias})) {
            return $this;
        }

        CI::$APP->{$_alias} = $params === null ? new $fqcn() : new $fqcn($params);

        $this->_ci_classes[mb_strtolower($class)] = $_alias;

        return $this;
    }

    public function service($fqcn, $params = null, $object_name = null)
    {
        return $this->loadNamespacedLibrary($fqcn, $params, $object_name);
    }
}
