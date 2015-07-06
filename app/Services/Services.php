<?php namespace App\Services;

use Elasticsearch\ClientBuilder;

class Services
{
    /**
     * @var ClientBuilder
     */
    private $api;

    /**
     * @param ClientBuilder $api
     */
    public function __construct()
    {
        $api       = new ClientBuilder();
        $this->api = $api->create()->build();
    }

    public function search($params)
    {
        return $this->api->search($params);
    }

    public function getCount($params)
    {
        return $this->api->count($params);
    }

}