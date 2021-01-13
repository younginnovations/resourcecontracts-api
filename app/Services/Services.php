<?php namespace App\Services;

use Elasticsearch\ClientBuilder;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

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
    /*
     * Prefix to use for all ES indices.
     * @var string
     */
    protected $indices_prefix;
    /**
     * @var string
     */
    public $lang;
    /**
     * @var ClientBuilder
     */
    private $api;

    private $document_count;

    public function __construct()
    {
        $hosts       = explode(",", env('ELASTICSEARCH_SERVER'));
        $this->indices_prefix = env('INDICES_PREFIX');

        $log_level = Logger::WARNING;
//        if (env('APP_DEBUG') === 'true') {
//            $log_level = Logger::DEBUG;
//        }
        $logger = new Logger('log');
        $logger->pushHandler(new StreamHandler('/var/log/rc-api.log', $log_level));

        $client      = ClientBuilder::create()->setHosts($hosts)->setLogger($logger);
        $this->api   = $client->build();
        $this->lang  = "en";
    }

    public function getMasterIndex()
    {
        return $this->indices_prefix . '_master';
    }

    public function getMetadataIndex()
    {
        return $this->indices_prefix . '_metadata';
    }

    public function getAnnotationsIndex()
    {
        return $this->indices_prefix . '_annotations';
    }

    public function getPdfTextIndex()
    {
        return $this->indices_prefix . '_pdf_text';
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
        $params['index'] = $this->getMetadataIndex();
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
     * Get single contract from ID
     *
     * @param [type] $id
     * @param [type] $lang
     *
     * @return array
     */
    public function getSingleContract($id, $lang)
    {
        $params['index']           = $this->getMasterIndex();
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

    /**
     * Return search count
     * @return mixed
     */
    public function countAll()
    {
        if (!isset($this->document_count)) {
            $params               = [];
            $params['index']      = $this->getMasterIndex();
            $params['body']       = [
                "query" => [
                    "match_all" => new class {
                    },
                ],
            ];
            $count                = $this->countResult($params);
            $this->document_count = $count['count'];

            return $this->document_count;
        }

        return $this->document_count;
    }

    /**
     * Returns contract count
     *
     * @param      $params
     * @param bool $only_parent_count
     *
     * @return int
     */
    public function getContractCount($params, $only_parent_count = false)
    {
        $temp_params                                  = [];
        $temp_params['index']                         = $this->getMasterIndex();
        $temp_params['body']['_source']               = ['_id', 'is_supporting_document', 'parent_contract'];
        $temp_params['body']['query']['bool']['must'] = $params['body']['query']['bool']['must'];
        $temp_params['body']['from']                  = 0;
        $temp_params['body']['size']                  = $this->countAll();
        $results                                      = $this->search($temp_params);
        $results                                      = $results['hits']['hits'];
        $parent_contract_ids                          = [];
        $child_contract_ids                           = [];
        $temp_parent_contract_ids                     = [];

        foreach ($results as $result) {
            $source = $result['_source'];

            if ($source['is_supporting_document'] == '0') {
                $temp_parent_contract_ids[] = (int) $result['_id'];
            }
        }

        foreach ($results as $result) {
            $source      = $result['_source'];
            $contract_id = (int) $result['_id'];

            if ($source['is_supporting_document'] == '1') {
                $parent_contract_id   = (int) $source['parent_contract']['id'];
                $child_contract_ids[] = $contract_id;

                if (in_array($parent_contract_id, $temp_parent_contract_ids)) {
                    $parent_contract_ids[] = $parent_contract_id;
                }
            } else {
                $parent_contract_ids[] = $contract_id;
            }
        }
        $parent_contract_ids = array_unique($parent_contract_ids);
        $child_contract_ids  = array_unique($child_contract_ids);

        if ($only_parent_count) {
            return count($parent_contract_ids);
        }

        return count($parent_contract_ids) + count($child_contract_ids);
    }
}
