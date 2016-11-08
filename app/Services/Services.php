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
    public $lang;
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
        $this->lang  = "en";
    }

    /**
     * Return the search result
     *
     * @param $params
     *
     * @return array
     */
    public function search($params)
    {
        return $this->api->search($params);
    }

    /**
     * Count the search result
     *
     * @param $params
     *
     * @return array
     */
    public function countResult($params)
    {
        return $this->api->count($params);
    }

    /**
     * Return the count of search result
     *
     * @param $params
     *
     * @return array
     */
    public function getCount($params)
    {
        return $this->api->count($params);
    }

    /**
     * Filter according to category
     *
     * @param $lang
     * @param $category
     *
     * @return array
     */
    public function getCategory($lang, $category)
    {
        $params['term'] = [
            $lang.".category" => [
                "value" => $category,
            ],
        ];

        return $params;
    }

    /**
     * Return the type of Id
     *
     * @param $id
     *
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


    /**
     * Add fuzzy operator
     *
     * @param $queryString
     *
     * @return string
     */
    public function addFuzzyOperator($queryString)
    {
        $queryString = urldecode($queryString);
        $quotePos    = strpos($queryString, '"');
        if ($quotePos === 0) {
            return $queryString;
        }

        if (count($queryString) == 1) {
            return $queryString."~4";
        }


        return $queryString;

    }

    /**
     * If operator exist
     *
     * @param $queryString
     *
     * @return bool
     */
    public function findOperator($queryString)
    {
        $operators = ["+", "-", "|", "*", "(", "~"];
        $found     = false;
        foreach ($operators as $op) {
            if (strpos($queryString, $op)) {
                $found = true;
            }

        }

        return $found;

    }

    public function getLang($request)
    {
        $lang = $this->lang;
        if (isset($request['lang']) && !empty($request['lang'])) {
            $lang = $request['lang'];
        }

        return $lang;
    }

}
