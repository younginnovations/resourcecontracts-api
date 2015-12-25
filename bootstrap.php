<?php
use Symfony\Component\HttpFoundation\Request;

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
    $errorMsg["error"] = [
        "code"=>400,
        "message"=>"The API you are currently looking is not available."
    ];

    return $response->create(json_encode($errorMsg))->send();
}

