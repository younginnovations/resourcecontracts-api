<?php namespace App\Services;

use Elasticsearch\ClientBuilder;
use Monolog\Logger;

/**
 * Class Services
 * @package App\Services
 */
class Services
{
    /**
     * string Sorting order default is ascending
     */
    const ORDER = "asc";
    /**
     * @param index
     */
    public $index;
    /**
     * @var string
     */
    public $lang;
    /**
     * @var ClientBuilder
     */
    private $api;

    public function __construct()
    {
        $hosts       = explode(",", env('ELASTICSEARCH_SERVER'));
        $this->index = env("INDEX");
        $logger      = ClientBuilder::defaultLogger('/var/log/rc-api.log', Logger::WARNING);
        $client      = ClientBuilder::create()->setHosts($hosts)->setLogger($logger);
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

    /**
     * $params['index']          = (list) A comma-separated list of index names to restrict the operation; use `_all`
     * or empty string to perform the operation on all indices
     *        ['ignore_indices'] = (enum) When performed on multiple indices, allows to ignore `missing` ones
     *        ['preference']     = (string) Specify the node or shard the operation should be performed on (default:
     *        random)
     *        ['routing']        = (string) Specific routing value
     *        ['source']         = (string) The URL-encoded request definition (instead of using request body)
     *        ['body']           = (array) The request definition
     *
     * @param $params array Associative array of parameters
     *
     * @return array
     */
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

    /**
     * Get Language code
     *
     * @param $request
     *
     * @return string
     */
    public function getLang($request)
    {
        $lang = $this->lang;

        if (isset($request['lang']) && !empty($request['lang'])) {
            $lang = $request['lang'];
        }

        return $lang;
    }

    /**
     * Set Language
     *
     * @param string $lang
     */
    public function setLang($lang)
    {
        $this->lang = $lang;
    }

    /**
     * Set Index
     *
     * @param string $index
     */
    public function setIndex($index)
    {
        $this->index = $index;
    }

    /**
     * Set Api
     *
     * @param ClientBuilder $api
     */
    public function setApi($api)
    {
        $this->api = $api;
    }

    /**
     * @param $source
     * @param $field_name
     *
     * @return mixed
     */
    protected function getValueOfField($source, $field_name)
    {
        $field_value = $source[$field_name];
        if (isset($field_value) && !empty($field_value)) {
            if (is_array($field_value)) {
                return $field_value[0];
            } else {
                return $field_value;
            }
        }

        return "";
    }

    /**
     * @param $source
     * @param $field_name
     *
     * @return mixed
     */
    protected function getValuesOfField($source, $field_name)
    {
        $field_value = $source[$field_name];
        if (isset($field_value)) {

            return $field_value;
        }

        return [];
    }

    /**
     * @param $request
     *
     * @return string
     */
    protected function getSortOrder($request)
    {
        return (isset($request['order']) and in_array(
                $request['order'],
                ['desc', 'asc']
            )) ? $request['order'] : self::ORDER;
    }

    /**
     * Excludes specific resource
     *
     * @param $resource_key
     * @param $resource_name
     * @param $lang
     *
     * @return array
     */
    public function excludeResource($resource_key, $resource_name, $lang)
    {
        return [
            "$lang.$resource_key" => $resource_name,
        ];
    }

    /**
     * Checks if specific contract is accessible
     *
     * @param       $contractId
     * @param       $lang
     * @param array $params
     *
     * @return bool
     */
    public function checkResourceAccess($contractId, $lang, $access_params = [])
    {
        $resource_access = true;
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "metadata";
        $filter          = [];
        $type            = $this->getIdType($contractId);

        if (!empty($contractId) && $type == "string") {
            $filter[] = [
                "term" => ["$lang.open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }

        $params['body'] = [
            'query' => [
                'bool' => [
                    'must' => $filter,
                ],
            ],
        ];

        $results = $this->search($params);
        $result  = $results['hits']['hits'];

        if (!empty($result)) {
            $result = $result[0]['_source'][$lang];

            if ((isset($result['country']['code']) && $result['country']['code'] == 'GN' && $access_params['isCountrySite'])
                && (isset($result['resource']) && in_array('Hydrocarbons', $result['resource']))
            ) {
                $resource_access = false;
            }
        }

        return $resource_access;

    }

    /**
     * Get all contracts from metadata type of elasticsearch
     *
     * @param [type] $lang
     * @param [type] $rc
     *
     * @return array
     */
    public function getAllMetaContracts($lang, $rc)
    {
        $params['index']         = $this->index;
        $params['type']          = 'metadata';
        $params['body']['query'] = [
            "bool" => [
                "must" => $rc,
            ],
        ];

        $totalMetaContracts = $this->countResult($params)['count'];

        $params['body']['size']    = $totalMetaContracts;
        $params['body']['from']    = 0;
        $params['body']['_source'] = [
            "contract_id",
            $lang.".open_contracting_id",
            $lang.".is_supporting_document",
            $lang.".translated_from",
            "supporting_contracts",
        ];

        return $this->search($params);
    }

    public function getMainContracts($params, $lang, $rc)
    {
        $main_params['index']                           = $this->index;
        $main_params['type']                            = 'metadata';
        $main_params['body']['query']['bool']['must'][] = $rc;
        $main_params['body']['query']['bool']['must'][] = ['term' => ["$lang.is_supporting_document" => "0"]];
        $main_params['body']['sort']                    = $params['body']['sort'];
        $main_params['body']['size']                    = $params['body']['size'];
        $main_params['body']['from']                    = $params['body']['from'];
        $main_params['body']['_source']                 = [
            "contract_id",
            "supporting_contracts"
        ];

        return $this->search($main_params);
    }

    /**
     * Get single contract from ID
     *
     * @param [type] $id
     * @param [type] $lang
     *
     * @return array
     */
    public function getSingleContract($id, $lang)
    {
        $params['index']           = $this->index;
        $params['type']            = "master";
        $params['body']['query']   = [
            "bool" => [
                "must" => [
                    "term" => [
                        "_id" => $id,
                    ],
                ],
            ],
        ];
        $params['body']['_source'] = [
            $lang.".contract_name",
            $lang.".signature_year",
            $lang.".open_contracting_id",
            $lang.".signature_date",
            $lang.".file_size",
            $lang.".country_code",
            $lang.".country_name",
            $lang.".resource",
            $lang.".language",
            $lang.".file_size",
            $lang.".company_name",
            $lang.".contract_type",
            $lang.".corporate_grouping",
            $lang.".show_pdf_text",
            $lang.".category",
        ];

        return $this->search($params);
    }
}
