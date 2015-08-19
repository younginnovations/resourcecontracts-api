<?php namespace App\Services;

use Elasticsearch\ClientBuilder;

/**
 * Class Services
 * @package App\Services
 */
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

    /**
     * Return the search result
     * @param $params
     * @return array
     */
    public function search($params)
    {
        return $this->api->search($params);
    }

    /**
     * Return the count of search result
     * @param $params
     * @return array
     */
    public function getCount($params)
    {
        return $this->api->count($params);
    }

    /**
     * Filter according to category
     * @param $category
     * @return array
     */
    public function getCategory($category)
    {
        $params['term'] = [
            "metadata.category" => [
                "value"=>$category
            ]
        ];

        return $params;
    }

}
