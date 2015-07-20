<?php
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__.'/../vendor/autoload.php';
require 'server.php';
require 'app/config.php';
require 'app/helper.php';

$router   = new League\Route\RouteCollection;
$response = new \Symfony\Component\HttpFoundation\Response;

require 'app/route.php';

try {
    $dispatcher = $router->getDispatcher();
    $request    = Request::createFromGlobals();
    $response   = $dispatcher->dispatch($request->getMethod(), $request->getPathInfo());
    $response->send();

} catch (\League\Route\Http\Exception $e) {
    return $response->create(view(404), 400)->send();
}