<?php namespace App\Services;

/**
 * API Service for client site
 * Class APIServices
 * @package App\Services
 */
class APIServices extends Services
{
    const ORDER = "asc";
    const FROM  = 0;
    const SIZE  = 25;

    /**
     * Return the summary of contracts
     * @return array
     */
    public function getSummary($request)
    {
        $params = $this->getMetadataIndexType();
        $data   = [];

        $params['body'] = [
            'size' => 0,
            'aggs' =>
                [
                    'country_summary'  =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.country.code',
                                    'size'  => 252,
                                    'order' => [
                                        "_term" => "asc"
                                    ]
                                ],
                        ],
                    'year_summary'     =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.signature_year',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "desc"
                                    ]
                                ],
                        ],
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'resource_raw',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc"
                                    ]
                                ],
                        ],
                ],
        ];
        if (isset($request['category']) && !empty($request['category'])) {
            $categoryfilter          = $this->getCategory($request['category']);
            $params['body']['query'] = $categoryfilter;
        }

        $response = $this->search($params);

        $data['country_summary']  = $response['aggregations']['country_summary']['buckets'];
        $data['year_summary']     = $response['aggregations']['year_summary']['buckets'];
        $data['resource_summary'] = $response['aggregations']['resource_summary']['buckets'];
        $data['contract_count']   = $response['hits']['total'];

        return $data;
    }

    /**
     * Return the index and its type
     * @return array
     */
    public function getMetadataIndexType()
    {
        $param          = [];
        $param['index'] = $this->index;
        $param['type']  = "metadata";

        return $param;
    }

    /**
     * Return the page of pdf text
     * @param $contractId
     * @param $pageNo
     * @return array
     */
    public function getTextPages($contractId, $request)
    {
        $params['index'] = $this->index;
        $params['type']  = "pdf_text";
        $filter          = [];
        if (!empty($contractId)) {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['page']) and !empty($request['page'])) {
            $filter[] = [
                "term" => ["page_no" => ["value" => $request['page']]]
            ];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filter[] = [
                "term" => ["metadata.category" => ["value" => $request['category']]]
            ];
        }
        $params['body'] = [
            'size'  => 10000,
            'query' => [
                'bool' => [
                    'must' => $filter
                ]
            ]
        ];
        $results        = $this->search($params);
        $data           = [];
        $data['total']  = $results['hits']['total'];
        $data['result'] = [];
        foreach ($results['hits']['hits'] as $result) {
            $source           = $result['_source'];
            $data['result'][] = [
                'contract_id' => $source['contract_id'],
                'id'          => $result['_id'],
                'text'        => $source['text'],
                'pdf_url'     => $source['pdf_url'],
                'page_no'     => $source['page_no']
            ];
        }

        return $data;
    }

    /**
     * Return the contract annotations of page
     * @param $contractId
     * @param $pageNo
     * @return array
     */
    public function getAnnotationPages($contractId, $request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $filter          = [];
        if (!empty($contractId)) {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['page']) and !empty($request['page'])) {
            $filter[] = [
                "term" => ["page" => ["value" => $request['page']]]
            ];
        }

        $params['body'] = [
            'size'  => 10000,
            'query' => [
                'bool' => [
                    'must' => $filter
                ]
            ]
        ];
        $results        = $this->search($params);
        $data           = [];
        $data['total']  = $results['hits']['total'];
        $i              = 0;
        $data['result'] = [];
        foreach ($results['hits']['hits'] as $result) {
            $source             = $result['_source'];
            $data['result'][$i] = [
                'contract_id' => $source['contract_id'],
                'id'          => $result['_id'],
                'quote'       => isset($source['quote']) ? $source['quote'] : null,
                'text'        => $source['text'],
                'tags'        => $source['tags'],
                'category'    => $source['category'],
                'page_no'     => $source['page'],
                'ranges'      => isset($source['ranges']) ? $source['ranges'] : null,
            ];
            if (isset($source['shapes'])) {
                unset($data['result'][$i]['ranges']);
                $data['result'][$i]['shapes'] = $source['shapes'];
            }

            $i ++;
        }

        return $data;
    }

    /**
     * Return the metadata
     * @param $contractId
     * @return mixed
     */
    public function getMetadata($contractId, $request)
    {
        $params   = $this->getMetadataIndexType();
        $filters  = [];
        $category = '';
        if ($contractId) {
            $filters[] = [
                "term" => ["_id" => ["value" => $contractId]]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => ["metadata.category" => ["value" => $request['category']]]
            ];
            $category  = $request['category'];
        }

        $params['body'] = [
            "_source" => [
                "exclude" => ["updated_user_name", "updated_user_email", "updated_at", "created_user_name", "created_user_email"]
            ],
            "query"   => [
                "bool" => [
                    "must" => $filters
                ]
            ]
        ];


        $result  = $this->search($params);
        $result  = $result['hits']['hits'];
        $results = [];

        if (!empty($result)) {
            $results                     = $result[0]['_source'];
            $document                    = isset($results['supporting_contracts']) ? $results['supporting_contracts'] : [];
            $supportingDoc               = $this->getSupportingDocument($document, $category);
            $metadata                    = $results['metadata'];
            $translatedFrom              = isset($metadata['translated_from']) ? $metadata['translated_from'] : [];
            $parentDocument              = $this->getSupportingDocument($translatedFrom, $category);
            $metadata['parent_document'] = $parentDocument;
            unset($results['metadata']);
            unset($results['supporting_contracts']);
            $results                         = array_merge($results, $metadata);
            $results['supporting_contracts'] = $supportingDoc;
            unset($results['translated_from']);

        }

        return $results;
    }

    /**
     * Return all contracts
     * @return array
     */
    public function getAllContracts($request)
    {

        $params = $this->getMetadataIndexType();
        $filter = [];
        if (isset($request['country_code']) and !empty($request['country_code'])) {
            $filter[] = [
                "term" => [
                    "metadata.country.code" => $request['country_code']
                ]
            ];
        }
        if (isset($request['year']) and !empty($request['year'])) {
            $filter[] = [
                "term" => [
                    "metadata.signature_year" => $request['year'],
                ]
            ];
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $filter[] = [
                "term" => [
                    "metadata.resource_raw" => $request['resource'],
                ]
            ];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filter[] = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
        }

        $params['body']['query']['filtered']['filter']['and']['filters'] = $filter;
        $params['body']['size']                                          = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        if (isset($request['from'])) {
            $params['body']['from'] = !empty($request['from']) ? $request['from'] : self::FROM;
        }

        if (isset($request['sort_by']) and !empty($request['sort_by'])) {
            if ($request['sort_by'] == "country") {
                $params['body']['sort']['metadata.country.name']['order'] = (isset($request['order']) and in_array($request['order'], ['desc', 'asc'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = (isset($request['order']) and in_array($request['order'], ['desc', 'asc'])) ? $request['order'] : self::ORDER;
            }
        }
        $results = $this->search($params);

        $data             = [];
        $data['total']    = $results['hits']['total'];
        $data['per_page'] = (isset($request['per_page']) and !empty($request['per_page'])) ? (integer) $request['per_page'] : self::SIZE;
        $data['from']     = (isset($request['from']) and !empty($request['from'])) ? $request['from'] : self::FROM;
        $data['results']  = [];
        foreach ($results['hits']['hits'] as $result) {
            $source            = $result['_source'];
            $data['results'][] = [
                'contract_id'    => (integer) $source['contract_id'],
                'contract_name'  => $source['metadata']['contract_name'],
                'country'        => $source['metadata']['country']['name'],
                'country_code'   => $source['metadata']['country']['code'],
                'signature_date' => $source['metadata']['signature_date'],
                'contract_type'  => $source['metadata']['type_of_contract'],
                'language'       => $source['metadata']['language'],
                'resources'      => $source['metadata']['resource'],
                'file_size'      => $source['metadata']['file_size'],
                'category'       => $source['metadata']['category']
            ];
        }

        return $data;
    }

    /**
     * Count of contract
     * @return array
     */
    public function getAllContractCount()
    {
        $params                               = $this->getMetadataIndexType();
        $params['body']["query"]["match_all"] = [];
        $response                             = $this->getCount($params);

        return $response['count'];
    }

    /**
     * PDF Search
     * @param $request
     * @return array
     */
    public function pdfSearch($contractId, $request)
    {
        $params['index'] = $this->index;
        $params['type']  = "pdf_text";
        if ((!isset($request['q']) and empty($request['q'])) or !is_numeric($contractId)) {
            return [];
        }
        $filters = [];
        if ($contractId) {
            $filters[] = [
                "term" => [
                    "contract_id" => $contractId
                ]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
        }

        $params['body']  = [
            "query"     => [
                "filtered" => [
                    "query"  => [
                        "query_string" => [
                            "default_field" => "text",
                            "query"         => $request['q']
                        ]
                    ],
                    "filter" => [
                        "and" => [
                            "filters" => $filters
                        ]
                    ]
                ]
            ],
            "highlight" => [
                "fields" => [
                    "text" => [
                        "fragment_size"       => 200,
                        "number_of_fragments" => 1
                    ]
                ]
            ],
            "fields"    => [
                "page_no",
                "contract_id"
            ]
        ];
        $response        = $this->search($params);
        $data            = [];
        $data['total']   = $response['hits']['total'];
        $data['results'] = [];
        foreach ($response['hits']['hits'] as $hit) {
            $fields = $hit['fields'];
            $text   = $hit['highlight']['text'][0];
            if (!empty($text)) {
                $data['results'][] = [
                    'page_no'     => $fields['page_no'][0],
                    'contract_id' => $fields['contract_id'][0],
                    'text'        => strip_tags($text)
                ];
            }

        }

        return $data;
    }

    /**
     * Return the published supporting document
     *
     * @param $document
     * @return array
     */
    private function getSupportingDocument($documents, $category)
    {
        $data = [];
        foreach ($documents as $document) {
            $filters   = [];
            $filters[] = [
                'term' => [
                    '_id' => [
                        'value' => (int) $document['id']
                    ]
                ]
            ];
            if (!empty($category)) {
                $filters[] = [
                    'term' => [
                        'metadata.category' => [
                            'value' => $category
                        ]
                    ]
                ];
            }
            $params         = $this->getMetadataIndexType();
            $params['body'] = [
                'fields' => ["metadata.contract_name"],
                'query'  => [
                    'bool' => [
                        'must' => $filters
                    ]
                ]
            ];
            $result         = $this->search($params);

            if (!empty($result['hits']['hits'])) {
                $data[] = [
                    'id'            => $result['hits']['hits'][0]['_id'],
                    'contract_name' => $result['hits']['hits'][0]['fields']['metadata.contract_name'][0],
                    'status'        => "published"
                ];
            } else {
                $data[] = [
                    'id'            => $document['id'],
                    'contract_name' => $document['contract_name'],
                    'status'        => 'unpublished'
                ];
            }
        }

        return $data;

    }

    /**
     * Get all the contract according to countries
     *
     * @return array
     */
    public function getCountriesContracts($request)
    {
        $params    = $this->getMetadataIndexType();
        $resources = (isset($request['resource']) && ($request['resource'] != '')) ? array_map('trim', explode(',', $request['resource'])) : [];
        $filters   = [];
        if (!empty($resources)) {
            $filters[] = [
                'terms' => [
                    "metadata.resource_raw" => $resources
                ]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
        }

        $params['body'] = [
            'size'  => 0,
            "query" => [
                "bool" => [
                    "must" => $filters
                ]
            ],
            'aggs'  =>
                [
                    'country_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.country.code',
                                    'size'  => 252
                                ],
                        ]
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['country_summary']['buckets'];
        foreach ($results as $result) {
            $data['results'][] = [
                'code'     => $result['key'],
                'contract' => $result['doc_count']
            ];
        }

        return $data;
    }

    /**
     * Get resource aggregation according to country
     *
     * @param $request
     * @return array
     */
    public function getResourceContracts($request)
    {
        $params  = $this->getMetadataIndexType();
        $country = (isset($request['country']) && ($request['country'] != '')) ? array_map('trim', explode(',', $request['country'])) : [];
        $filters = [];
        if (!empty($country)) {
            $filters[] = [
                'terms' => [
                    "metadata.country.code" => $country
                ]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                'term' => [
                    "metadata.category" => $request['category']
                ]
            ];
        }
        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters
                ]
            ],
            'aggs'  =>
                [
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'resource_raw',
                                    'size'  => 1000
                                ],
                        ]
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['resource_summary']['buckets'];
        foreach ($results as $result) {
            $data['results'][] = [
                'resource' => $result['key'],
                'contract' => $result['doc_count']
            ];
        }

        return $data;

    }

    /**
     * Get years aggregation according to country
     *
     * @param $request
     * @return array
     */
    public function getYearsContracts($request)
    {
        $params  = $this->getMetadataIndexType();
        $country = (isset($request['country']) && ($request['country'] != '')) ? array_map('trim', explode(',', $request['country'])) : [];
        $filters = [];
        if (!empty($country)) {
            $filters[] = [
                'terms' => [
                    "metadata.country.code" => $country
                ]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
        }
        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters
                ]
            ],
            'aggs'  =>
                [
                    'year_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.signature_year',
                                    'size'  => 1000
                                ],
                        ]
                ],
        ];


        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['year_summary']['buckets'];
        foreach ($results as $result) {
            $data['results'][] = [
                'year'     => $result['key'],
                'contract' => $result['doc_count']
            ];
        }

        return $data;
    }

    /**
     * Get contract aggregation by country and resource
     *
     * @param $request
     * @return mixed
     */
    public function getContractByCountryAndResource($request)
    {
        $params    = $this->getMetadataIndexType();
        $resources = isset($request['resource']) ? array_map('trim', explode(',', $request['resource'])) : [];
        $filters   = [];
        if (!empty($resources)) {
            $filters[] = [
                'terms' => [
                    "metadata.resource" => $resources
                ]
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
        }
        $params['body'] = [
            'size'  => 0,
            "query" => [
                "bool" => [
                    "must" => $filters
                ]
            ],
            'aggs'  =>
                [
                    'country_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.country.code',
                                    'size'  => 1000,
                                ],
                            "aggs"  => [
                                "resource_summary" => [
                                    "terms" => [
                                        "field" => "resource_raw",
                                        'size'  => 1000
                                    ]
                                ]
                            ]
                        ]
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['country_summary']['buckets'];
        $i               = 0;
        foreach ($results as $result) {
            $resourceAggs = $result['resource_summary']['buckets'];
            if (empty($resourceAggs)) {
                $data['results'][] = [
                    'code'     => $result['key'],
                    'resource' => '',
                    'contract' => $result['doc_count']
                ];
            }
            foreach ($resourceAggs as $bucket) {
                $data['results'][] = [
                    'code'     => $result['key'],
                    'resource' => $bucket['key'],
                    'contract' => $bucket['doc_count']
                ];
            }
            $i ++;
        }

        return $data;
    }

    /**
     * Get the search filter attributes, such as contract_type,company_name,corporate_group
     *
     * @return array
     */
    public function getFilterAttributes($request)
    {
        $params['index'] = $this->index;
        $params['type']  = "master";
        $data            = [];

        $params['body'] = [
            'size' => 0,
            'aggs' =>
                [
                    'company_name'       =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.company_name',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc"
                                    ]
                                ],
                        ],
                    'corporate_grouping' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.corporate_grouping',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc"
                                    ]
                                ],
                        ],
                    'contract_type'      =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.contract_type',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc"
                                    ]
                                ],
                        ],
                ],
        ];
        if (isset($request['category']) && !empty($request['category'])) {
            $categoryfilter          = $this->getCategory($request['category']);
            $params['body']['query'] = $categoryfilter;
        }

        $response                   = $this->search($params);
        $data['company_name']       = [];
        $data['corporate_grouping'] = [];
        $data['contract_type']      = [];
        foreach ($response['aggregations']['company_name']['buckets'] as $companyname) {
            array_push($data['company_name'], $companyname['key']);
        }
        foreach ($response['aggregations']['corporate_grouping']['buckets'] as $grouping) {
            array_push($data['corporate_grouping'], $grouping['key']);
        }
        foreach ($response['aggregations']['contract_type']['buckets'] as $type) {
            array_push($data['contract_type'], $type['key']);
        }
        $data['company_name']       = array_unique($data['company_name']);
        $data['corporate_grouping'] = array_unique($data['corporate_grouping']);
        $data['contract_type']      = array_unique($data['contract_type']);

        return $data;
    }

    /**
     * Get all the annotations category
     * @param $request
     */
    public function getAnnotationsCategory($request)
    {

        $params['index'] = $this->index;
        $params['type']  = "master";
        $filters         = [];

        if (isset($request['category']) && !empty($request['category'])) {
            $filters[]                               = [
                "term" => [
                    "metadata.category" => $request['category']
                ]
            ];
            $params['body']['query']['bool']['must'] = $filters;
        }
        $params['body'] = [
            'size' => 0,
            'aggs' =>
                [
                    'category_summary' =>
                        [
                            "terms" => [
                                "field" => "annotations_category",
                                "size"  => 1000
                            ]
                        ]
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['category_summary']['buckets'];
        $i               = 0;

        foreach ($results as $result) {
            array_push($data['results'], $result['key']);
        }

        return array_unique($data);
    }

    /**
     * Get all the metadata of given id
     * @param $request
     * @return array
     */
    public function downloadMetadtaAsCSV($request)
    {
        $params['index'] = $this->index;
        $params['type']  = "metadata";
        $filters         = [];

        if (isset($request['id']) && !empty($request['id'])) {
            $filters = explode(',', $request['id']);
        }
        $params['body']['query']['terms']['_id'] = $filters;
        $searchResult                            = $this->search($params);
        $data                                    = [];
        if ($searchResult['hits']['total'] > 0) {
            $results = $searchResult['hits']['hits'];
            foreach ($results as $result) {
                unset($result['_source']['metadata']['amla_url'], $result['_source']['metadata']['file_size'], $result['_source']['metadata']['file_url'], $result['_source']['metadata']['word_file']);
                $data[] = $result['_source']['metadata'];
            }
        }

        return $data;
    }

}
