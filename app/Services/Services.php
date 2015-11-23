<?php namespace App\Services;

use Elasticsearch\ClientBuilder;

/**
 * Class Services
 * @package App\Services
 */
class Services
{
    /**
     * @param index
     */
    public $index;
    /**
     * @var ClientBuilder
     */
    private $api;

    /**
     * @param ClientBuilder $api
     * @param               index
     */
    public function __construct()
    {
        $hosts       = [env('ELASTICSEARCH_SERVER')];
        $this->index = env("INDEX");
        $client      = ClientBuilder::create()->setHosts($hosts);
        $this->api   = $client->build();
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
     * Count the search result
     * @param $params
     * @return array
     */
    public function countResult($params)
    {
        return $this->api->count($params);
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
                "value" => $category
            ]
        ];

        return $params;
    }

    /**
     * Return the type of Id
     * @param $id
     * @return string
     */
    public function getIdType($id)
    {
        return is_numeric($id) ? 'numeric' : 'string';
    }

    public function suggest($params)
    {
        return $this->api->suggest($params);
    }

}
