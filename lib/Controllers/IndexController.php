<?php declare(strict_types=1);

namespace MyApp\Controllers;

use \Phalcon\Mvc\Controller;

class IndexController extends Controller
{
    public function beforeExecuteRoute()
    {
        $this->response->setContent('Foo');
        return true;
    }

    public function afterExecuteRoute()
    {
        $body = $this->response->getContent();
        if ($body === 'Bar') {
            $this->response->setContent('Baz');
        }
        return true;
    }

    public function getAction()
    {
        $body = $this->response->getContent();
        if ($body !== 'Foo') {
            $this->response->setContent('beforeExecuteRoute did not fire');
        } else {
            $this->response->setContent('Bar');
        }
        return $this->response;
    }
}
