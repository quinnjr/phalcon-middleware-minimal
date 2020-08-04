<?php declare(strict_types=1);

namespace MyApp\Mvc;

use \Throwable;
use \Phalcon\Mvc\Controller;
use \Phalcon\Mvc\Micro as PhalconMicro;
use \Phalcon\Mvc\Micro\Exception;
use \Phalcon\Mvc\LazyLoader;
use \Phalcon\Mvc\Mico\MiddlewareInterface;
use function \is_a;
use function \is_callable;
use function \is_object;

class MicroExt extends PhalconMicro
{
    public function handle(string $uri)
    {
        $container = $this->container;

        if (!is_object($container)) {
            throw new Exception(
                Exception::containerServiceNotFound('micro services')
            );
        }

        try {
            $returnedValue = null;

            $eventsManager = $this->eventsManager;

            if (is_object($eventsManager)) {
                if ($eventsManager->fire('micro:beforeHandleRoute', $this) === false) {
                    return false;
                }
            }

            $router = $container->getShared('router');

            $router->handle($uri);

            $matchedRoute = $router->getMatchedRoute();

            if (is_object($matchedRoute)) {
                $handler = $this->handlers[$matchedRoute->getRouteId()];
                if (empty($handler)) {
                    throw new Exception(
                        'Matched route doesn\'t have an associated handler'
                    );
                }

                $this->activeHandler = $handler;

                if (is_object($eventsManager)) {
                    if ($eventsManager->fire('micro:beforeExecuteRoute', $this) === false) {
                        return false;
                    }

                    $handler = $this->activeHandler;
                }

                $beforeHandlers = $this->beforeHandlers;

                $this->stopped = false;

                foreach ($beforeHandlers as $before) {
                    if (is_object($before) && before instanceof MiddlewareInterface) {
                        $status = $before->call(this);
                    } else {
                        if (!is_callable($before)) {
                            throw new Exception (
                                "'before' handler is not callable"
                            );
                        }

                        $status = call_user_func($before);
                    }

                    if ($this->stopped) {
                        return status;
                    }
                }

                $params = $router->getParams();
                $modelBinder = $this->modelBinder;

                if (is_object($handler) && $handler instanceof Closure) {
                    $handler = Closure::bind($handler, $this);

                    if ($modelBinder !== null) {
                        $routeName = $matchedRoute->getName();

                        if (isset($routeName)) {
                            $bindCacheKey = '_PHMB_' . $routeName;
                        } else {
                            $bindCacheKey = '_PHMB_' . $matchedRoute->getPattern();
                        }

                        $params = $modelBinder->bindToHandler(
                            $handler,
                            $params,
                            $bindCacheKey
                        );
                    }
                }

                if (is_array($handler)) {
                    $realHandler = $handler[0];
                    /**
                     * Call `beforeExecuteRoute` method on a
                     * collection controller before binding
                     * is completed but after the application-wide
                     * middlewares are called.
                     */
                    if ($realHandler instanceof Controller && method_exists($realHandler,'beforeExecuteRoute')) {
                        $realHandler->beforeExecuteRoute();
                    }

                    if (isset($modleBinder)) {
                        $methodName = $handler[1];
                        $bindCacheKey = '_PHMB_' . get_class($realHandler) . '_' . $methodName;
    
                        $params = $modelBinder->bindToHandler(
                            $realHandler,
                            $params,
                            $bindCacheKey,
                            $methodName
                        );
                    }
                } 

                if (isset($realHandler) && $realHandler instanceof LazyLoader) {
                    $methodName = $handler[1];

                    $lazyReturned = $realHandler->callMethod(
                        $methodName,
                        $params,
                        $modelBinder
                    );
                } else {
                    $lazyReturned = call_user_func_array($handler, $params);
                }

                $returnedValue = $lazyReturned;

                if (is_object($eventsManager)) {
                    if ($eventsManager->fire('micro:afterBinding') === false) {
                        return false;
                    }
                }

                $afterBindingHandlers = $this->afterBindingHandlers;

                $this->stopped = false;

                foreach ($afterBindingHandlers as $afterBinding) {
                    if (is_object($afterBinding) && $afterBinding instanceof MiddlewareInterface) {
                        $status = $afterBinding->call($this);
                    } else {
                        if (!is_callable($afterBinding)) {
                            throw new Exception(
                                "'afterBinding' handler is not callable"
                            );
                        }

                        $status = call_user_func($afterBinding);
                    }

                    if ($this->stopped) {
                        return $status;
                    }
                }

                $this->returnedValue = $returnedValue;
                
                /**
                 * Call `afterExecuteRoute` middleware on a
                 * collection controller before the application-wide
                 * middlewares are called.
                 */
                if (isset($realHandler) && $realHandler instanceof Controller && method_exists($realHandler, 'afterExecuteRoute')) {
                    $realHandler->afterExecuteRoute();
                }

                if (is_object($eventsManager)) {
                    if ($eventsManager->fire('micro:afterExecuteRoute', $this) === false) {
                        return false;
                    }
                }

                $afterHandlers = $this->afterHandlers;
                $this->stopped = false;

                foreach ($afterHandlers as $after) {
                    if (is_object($after) && after instanceof MiddlewareInterface) {
                        $status = $after->call($this);
                    } else {
                        if (!is_callable($after)) {
                            throw new Exception(
                                "One of the 'after' handlers is not callable"
                            );
                        }

                        $status = call_user_func($after);
                    }

                    if ($this->stopped) {
                        return $status;
                    }
                }
            } else {
                $eventsManager = $this->eventsManager;

                if (is_object($eventsManager)) {
                    if ($eventsManager->fire('micro:beforeNotFound', false) === false) {
                        return false;
                    }
                }

                $notFoundHandler = $this->notFoundHandler;

                if (!is_callable($notFoundHandler)) {
                    throw new Exception(
                        'Not-Found handler is not callable or is not defined'
                    );
                }

                $returnedValue = call_user_func($notFoundHandler);
            }

            if (is_object($eventsManager)) {
                $eventsManager->fire('micro:afterHandleRoute', $this, $returnedValue);
            }

            $finishHandlers = $this->finishHandlers;
            $this->stopped = false;

            foreach ($finishHandlers as $finish) {
                if (is_object($finish) && finish instanceof MiddlewareInterface) {
                    $status = $finish->call($this);
                } else {
                    if (!is_callable($finish)) {
                        throw new Exception(
                            "One of the 'finish' handlers is not callable"
                        );
                    }

                    $status = call_user_func_array(
                        $finish,
                        [$this]
                    );
                }

                if ($this->stopped) {
                    return false;
                }
            }
        } catch (Throwable $e) {
            throw $e;
        }
    }
}
