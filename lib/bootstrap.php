<?php declare(strict_types=1);

namespace MyApp;

use \Phalcon\Loader;
use \Phalcon\Di\FactoryDefault;
// use \Phalcon\Mvc\Micro;
use \Phalcon\Mvc\Micro\Collection;

use MyApp\Mvc\MicroExt;

try {
    $loader = new \Phalcon\Loader();
    $loader->registerNamespaces([
        'MyApp' => __DIR__ . '/',
        'MyApp\\Controllers' => __DIR__ . '/Controllers/'
    ]);
    $loader->register();

    $di = new FactoryDefault();

    $app = new MicroExt($di);

    $indexCollection = new Collection();
    $indexCollection->setHandler(new Controllers\IndexController());
    $indexCollection->get('/', 'getAction');

    $app->mount($indexCollection);

    $app->handle($_SERVER['REQUEST_URI']);
} catch (\Throwable $e) {
    echo $e;
} finally {
    if(!$app->response->isSent()) {
        $app->response->send();
    }
}
