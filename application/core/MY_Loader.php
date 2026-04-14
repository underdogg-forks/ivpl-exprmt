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

        if ($this->isNamespacedClassName($library)) {
            return $this->loadNamespacedClass($library, $params, $object_name, false);
        }

        return parent::library($library, $params, $object_name);
    }

    public function model($model, $object_name = null, $connect = false)
    {
        if (is_array($model)) {
            foreach ($model as $key => $value) {
                if (is_int($key)) {
                    $this->model($value, null, $connect);
                } else {
                    $this->model($key, $value, $connect);
                }
            }

            return $this;
        }

        if ($this->isNamespacedClassName($model)) {
            return $this->loadNamespacedClass($model, null, $object_name, true);
        }

        return parent::model($model, $object_name, $connect);
    }

    public function service($className, $params = null, $object_name = null)
    {
        return $this->loadNamespacedClass($className, $params, $object_name, false);
    }

    protected function isNamespacedClassName($className)
    {
        return is_string($className) && strpos($className, '\\') !== false;
    }

    protected function loadNamespacedClass($className, $params = null, $object_name = null, $registerAsModel = false)
    {
        if ( ! class_exists($className)) {
            show_error('Unable to load the requested class: ' . $className);
        }

        $segments = explode('\\', $className);
        $short    = end($segments);
        $alias    = $object_name ?: lcfirst($short);

        if (isset(CI::$APP->{$alias})) {
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
