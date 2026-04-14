<?php


$classMap = [
    App\Adapters\CodeIgniter\MX\Config::class => __DIR__ . '/../application/third_party/MX/Namespaced/Config.php',
    App\Adapters\CodeIgniter\MX\Lang::class => __DIR__ . '/../application/third_party/MX/Namespaced/Lang.php',
    App\Adapters\CodeIgniter\MX\Loader::class => __DIR__ . '/../application/third_party/MX/Namespaced/Loader.php',
    App\Adapters\CodeIgniter\MX\Router::class => __DIR__ . '/../application/third_party/MX/Namespaced/Router.php',
    App\Adapters\CodeIgniter\MX\Controller::class => __DIR__ . '/../application/third_party/MX/Namespaced/Controller.php',
    App\Adapters\CodeIgniter\MX\Modules::class => __DIR__ . '/../application/third_party/MX/Namespaced/Modules.php',
];

spl_autoload_register(static function (string $className) use ($classMap): void {
    if (isset($classMap[$className])) {
        require_once $classMap[$className];
    }
});
