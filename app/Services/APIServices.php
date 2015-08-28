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
                                ],
                        ],
                    'year_summary'     =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.signature_year',
                                ],
                        ],
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.resource',
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
        $param['index'] = "nrgi";
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
        $params['index'] = "nrgi";
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
        $params['index'] = "nrgi";
        $params['type']  = "annotations";
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
        $i              = 0;
        $data['result'] = [];
        foreach ($results['hits']['hits'] as $result) {
            $source             = $result['_source'];
            $data['result'][$i] = [
                'contract_id' => $source['contract_id'],
                'id'          => $result['_id'],
                'quote'       => $source['quote'],
                'text'        => $source['text'],
                'tags'        => $source['tags'],
                'category'    => $source['category'],
                'page_no'     => $source['page_no'],
                'ranges'      => $source['ranges']
            ];
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
                    "metadata.resource" => $request['resource'],
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
        $results          = $this->search($params);
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
                'signature_year' => $source['metadata']['signature_year'],
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
        $params['index'] = "nrgi";
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

            $params         = $this->getMetadataIndexType();
            $params['body'] = [
                'fields' => ["metadata.contract_name"],
                'query'  => [
                    'bool' => [
                        'must' => [
                            'term' => [
                                '_id' => [
                                    'value' => (int) $document['id']
                                ]
                            ],
                            [
                                'term' => [
                                    'metadata.category' => [
                                        'value' => $category
                                    ]
                                ]
                            ]
                        ]
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
                                    'field' => 'metadata.resource',
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
                                ],
                            "aggs"  => [
                                "resource_summary" => [
                                    "terms" => [
                                        "field" => "metadata.resource"
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


}
