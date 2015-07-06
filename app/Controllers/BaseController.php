<?php namespace App\Controllers;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BaseController
 * @package App\Controllers
 */
class BaseController
{
    /**
     * @var Response
     */
    public $request;

    /**
     * @param Response $response
     */
    function __construct()
    {
        $this->request = Request::createFromGlobals();
    }

    /**
     * display content
     *
     * @param $page
     * @return Response
     */
    protected function view($page)
    {
        $content = view($page);

        return Response::create($content, 200, ['Content-Type: text/html']);
    }

    /**
     * display jason data
     *
     * @param $array
     * @return Response
     */
    protected function json($array)
    {
        $content = json_encode($array, JSON_PRETTY_PRINT);
        return Response::create($content, 200, ['Content-Type: application/json']);
    }
}
