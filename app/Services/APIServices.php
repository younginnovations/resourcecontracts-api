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
        $filters        = [];
        if (isset($request['category']) && !empty($request['category'])) {
            $categoryfilter = $this->getCategory($request['category']);
            array_push($filters, $categoryfilter);
        }
        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $country['term'] = [
                "metadata.country.code" => [
                    "value" => $request['country_code']
                ]
            ];
            array_push($filters, $country);
        }
        $params['body']['query']['bool']['must'] = $filters;

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
        $type            = $this->getIdType($contractId);

        if (!empty($contractId) && $type == "string") {
            $filter[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]]
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
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
                'contract_id'         => $source['contract_id'],
                'id'                  => $result['_id'],
                'open_contracting_id' => $source['open_contracting_id'],
                'text'                => $source['text'],
                'pdf_url'             => $source['pdf_url'],
                'page_no'             => $source['page_no']
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

        $type = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filter[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]]
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
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
            'sort'  => ['page' => ["order" => "asc"]],
            'query' => [
                'bool' => [
                    'must' => $filter
                ]
            ]
        ];

        $results         = $this->search($params);
        $data            = [];
        $data['total']   = $results['hits']['total'];
        $i               = 0;
        $data['result']  = [];
        $remove          = ["Pages missing from  copy//Pages Manquantes de la copie", "Annexes missing from copy//Annexes Manquantes de la copie"];
        $totalAnnotation = 0;
        foreach ($results['hits']['hits'] as $result) {
            $source = $result['_source'];

            if (!in_array($source['category'], $remove)) {
                $totalAnnotation    = $totalAnnotation + 1;
                $data['result'][$i] = [
                    'contract_id'         => $source['contract_id'],
                    'open_contracting_id' => $source['open_contracting_id'],
                    'id'                  => $result['_id'],
                    'quote'               => isset($source['quote']) ? $source['quote'] : null,
                    'text'                => $source['text'],
                    'category'            => $source['category'],
                    'page_no'             => $source['page'],
                    'ranges'              => isset($source['ranges']) ? $source['ranges'] : null,
                    'cluster'             => isset($source['cluster']) ? $source['cluster'] : null,
                    'category_key'        => isset($source['category_key']) ? $source['category_key'] : null,
                ];
                if (isset($source['shapes'])) {
                    unset($data['result'][$i]['ranges']);
                    $data['result'][$i]['shapes'] = $source['shapes'];
                }
                $i ++;
            }
        }

        $data['total'] = $totalAnnotation;

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
        $type     = $this->getIdType($contractId);

        if ($contractId && $type == "numeric") {
            $filters[] = [
                "term" => ["_id" => ["value" => $contractId]]
            ];
        }
        if ($contractId && $type == "string") {
            $filters[] = [
                "term" => ["metadata.open_contracting_id" => ["value" => $contractId]]
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


        $result = $this->search($params);
        $result = $result['hits']['hits'];
        $data   = [];

        if (!empty($result)) {
            $results = $result[0]['_source'];
            $data    = $this->formatMetadata($results, $category);
        }

        return $data;
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
                    "resource_raw" => $request['resource'],
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
        if (isset($request['download']) && $request['download']) {
            $params['body']['size'] = 100000;
        }
        if (isset($request['from'])) {
            $params['body']['from'] = !empty($request['from']) ? $request['from'] : self::FROM;
        }

        if (isset($request['sort_by']) and !empty($request['sort_by'])) {

            if ($request['sort_by'] == "country") {
                $params['body']['sort']['metadata.country.name.raw']['order'] = (isset($request['order']) and in_array($request['order'], ['desc', 'asc'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = (isset($request['order']) and in_array($request['order'], ['desc', 'asc'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort']['metadata.contract_name.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort']['resource_raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort']['metadata.type_of_contract.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
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
                'id'                  => (integer) $source['contract_id'],
                'open_contracting_id' => $source['metadata']['open_contracting_id'],
                'name'                => $source['metadata']['contract_name'],
                'country_code'        => $source['metadata']['country']['code'],
                'year_signed'         => $this->getSignatureYear($source['metadata']['signature_year']),
                'date_signed'         => !empty($source['metadata']['signature_date']) ? $source['metadata']['signature_date'] : '',
                'contract_type'       => $source['metadata']['type_of_contract'],
                'language'            => $source['metadata']['language'],
                'resource'            => $source['metadata']['resource'],
                'category'            => $source['metadata']['category'],
                'is_ocr_reviewed'     => $this->getBoolean($source['metadata']['show_pdf_text']),
            ];
        }

        if (isset($request['download']) && $request['download']) {
            $download     = new DownloadServices();
            $downloadData = $download->getMetadataAndAnnotations($data, $request);

            return $download->downloadSearchResult($downloadData);
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
    public function searchAnnotationAndText($contractId, $request)
    {
        $textResult        = $this->textSearch($contractId, $request);
        $annotationsResult = $this->annotationSearch($contractId, $request);
        $result            = array_merge($textResult, $annotationsResult);
        $data['total']     = count($result);
        $data['results']   = $result;

        return $data;
    }

    /**
     * Annotation Search
     *
     * @param $contractId
     * @param $request
     * @return array
     */
    public function annotationSearch($contractId, $request)
    {
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        if ((!isset($request['q']) and empty($request['q']))) {
            return [];
        }
        $filters = [];
        $type    = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filters[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]]
            ];
        }
        if ($contractId && $type == "numeric") {
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
        $q              = urldecode("\"" . $request['q'] . "\"");
        $params['body'] = [
            "query"     => [
                "simple_query_string" => [
                    "fields"           => ["text"],
                    'query'            => $q,
                    "default_operator" => "AND",
                    "flags"            => "PHRASE",
                ]
            ],
            "filter"    => [
                "and" => [
                    "filters" => $filters
                ]
            ]
            ,
            "highlight" => [
                "fields" => [
                    "text"     => [
                        "fragment_size"       => 200,
                        "number_of_fragments" => 1
                    ]
                ]
            ],
            "fields"    => [
                "id",
                "page",
                "shapes ",
                "contract_id",
                "open_contracting_id"
            ]
        ];
        $results        = $this->search($params);
        $data           = [];
        foreach ($results['hits']['hits'] as $hit) {
            $fields          = $hit['fields'];
            $text            = isset($hit['highlight']['text']) ? $hit['highlight']['text'][0] : "";
            $category        = isset($hit['highlight']['category']) ? $hit['highlight']['category'][0] : "";
            $annotationsType = $this->getAnnotationType($fields['id'][0]);

            if (!empty($text)) {
                $data[] = [
                    'annotation_id'       => $fields['id'][0],
                    'page_no'             => $fields['page'][0],
                    'contract_id'         => $fields['contract_id'][0],
                    'open_contracting_id' => $fields['open_contracting_id'][0],
                    'text'                => strip_tags($text),
                    "annotation_type"     => $annotationsType,
                    "type"                => "annotation"
                ];
            }

        }

        return $data;

    }

    /**
     * Text search
     * @param $contractId
     * @param $request
     * @return array
     */
    public function textSearch($contractId, $request)
    {
        $params['index'] = $this->index;
        $params['type']  = "pdf_text";
        if ((!isset($request['q']) and empty($request['q']))) {
            return [];
        }
        $filters = [];
        $type    = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filters[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]]
            ];
        }
        if ($contractId && $type == "numeric") {
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

        $params['body'] = [
            "query"     => [
                "filtered" => [
                    "query"  => [
                        "match_phrase" => [
                            "text" => $request['q']
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
                "contract_id",
                "open_contracting_id"
            ]
        ];
        $response       = $this->search($params);
        $data           = [];
        foreach ($response['hits']['hits'] as $hit) {
            $fields = $hit['fields'];
            $text   = $hit['highlight']['text'][0];
            if (!empty($text)) {
                $data[] = [
                    'page_no'             => $fields['page_no'][0],
                    'contract_id'         => $fields['contract_id'][0],
                    'open_contracting_id' => $fields['open_contracting_id'][0],
                    'text'                => strip_tags($text),
                    "type"                => "text"
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
                'fields' => ["metadata.contract_name", "metadata.open_contracting_id"],
                'query'  => [
                    'bool' => [
                        'must' => $filters
                    ]
                ]
            ];
            $result         = $this->search($params);

            if (!empty($result['hits']['hits'])) {
                $data[] = [
                    'id'                  => (int) $result['hits']['hits'][0]['_id'],
                    'open_contracting_id' => $result['hits']['hits'][0]['fields']['metadata.open_contracting_id'][0],
                    'name'                => $result['hits']['hits'][0]['fields']['metadata.contract_name'][0],
                    'is_published'        => true
                ];
            } else {
                $data[] = [
                    'id'                  => (int) $document['id'],
                    'open_contracting_id' => '',
                    'name'                => $document['contract_name'],
                    'is_published'        => false
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
                    "resource_raw" => $resources
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

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    "metadata.country.code" => $request['country_code']
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

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                'term' => [
                    "metadata.country.code" => $request['country_code']
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

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    "metadata.country.code" => $request['country_code']
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

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    "metadata.country.code" => $request['country_code']
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
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters
                ]
            ],
            'aggs'  =>
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

        $data   = array_unique($data);
        $remove = ["Pages missing from  copy//Pages Manquantes de la copie", "Annexes missing from copy//Annexes Manquantes de la copie"];
        array_walk(
            $data['results'],
            function ($value, $key) use ($data, $remove) {
                if (in_array($value, $remove)) {
                    unset($data['results'][$key]);
                }
            }
        );

        return $data;
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
        $params['body'] = [
            'size'  => 100000,
            'query' => [
                "terms" => [
                    "_id" => $filters
                ]
            ]
        ];
        $searchResult   = $this->search($params);
        $data           = [];
        if ($searchResult['hits']['total'] > 0) {
            $results = $searchResult['hits']['hits'];
            foreach ($results as $result) {
                unset($result['_source']['metadata']['amla_url'], $result['_source']['metadata']['file_size'], $result['_source']['metadata']['word_file']);
                $data[] = $result['_source']['metadata'];
            }
        }

        return $data;
    }

    /**
     * Format metadata
     *
     * @param $results
     * @param $category
     * @return array
     */
    private function formatMetadata($results, $category)
    {

        $data                        = [];
        $metadata                    = $results['metadata'];
        $data['id']                  = (int) $results['contract_id'];
        $data['open_contracting_id'] = isset($metadata['open_contracting_id']) ? $metadata['open_contracting_id'] : '';
        $data['name']                = isset($metadata['contract_name']) ? $metadata['contract_name'] : '';
        $data['identifier']          = isset($metadata['contract_identifier']) ? $metadata['contract_identifier'] : '';
        $data['number_of_pages']     = isset($results['total_pages']) ? (int) $results['total_pages'] : '';
        $data['language']            = isset($metadata['language']) ? $metadata['language'] : '';
        $data['country']             = isset($metadata['country']) ? $metadata['country'] : '';
        $data['resource']            = isset($metadata['resource']) ? $metadata['resource'] : '';

        foreach ($metadata['government_entity'] as $government) {
            $data['government_entity'][] = [
                "name"       => $government['entity'],
                "identifier" => $government['identifier']
            ];
        }
        $data['contract_type'] = isset($metadata['type_of_contract']) ? $metadata['type_of_contract'] : '';
        $data['date_signed']   = isset($metadata['signature_date']) ? $metadata['signature_date'] : '';
        $data['year_signed']   = isset($metadata['signature_year']) ? $this->getSignatureYear($metadata['signature_year']) : '';
        $data['type']          = isset($metadata['document_type']) ? $metadata['document_type'] : '';


        foreach ($metadata['company'] as $company) {
            $data['participation'][] = [
                "company"     => [
                    "name"               => isset($company['name']) ? $company['name'] : '',
                    "address"            => isset($company['company_address']) ? $company['company_address'] : '',
                    "founding_date"      => isset($company['company_founding_date']) ? $company['company_founding_date'] : '',
                    "corporate_grouping" => isset($company['parent_company']) ? $company['parent_company'] : '',
                    "opencorporates_url" => (isset($company['open_corporate_id']) && !empty($company['open_corporate_id'])) ? $company['open_corporate_id'] : '',
                    "identifier"         => [
                        "id"      => isset($company['company_number']) ? $company['company_number'] : '',
                        "creator" => [
                            "name"    => isset($company['registration_agency']) ? $company['registration_agency'] : '',
                            "spatial" => isset($company['jurisdiction_of_incorporation']) ? $company['jurisdiction_of_incorporation'] : '',
                        ]
                    ]
                ],
                "is_operator" => $this->getBoolean($company['operator']),
                "share"       => $this->getShare($company['participation_share'])
            ];
        }


        $data['project']['name']       = isset($metadata['project_title']) ? $metadata['project_title'] : '';
        $data['project']['identifier'] = isset($metadata['project_identifier']) ? $metadata['project_identifier'] : '';


        foreach ($metadata['concession'] as $concession) {
            $data['concession'][] = [
                "name"       => !empty($concession['license_name']) ? $concession['license_name'] : "",
                "identifier" => $concession['license_identifier']
            ];
        }

        $data['source_url']             = isset($metadata['source_url']) ? $metadata['source_url'] : '';
        $data['amla_url']               = isset($metadata['amla_url']) ? $metadata['amla_url'] : '';
        $data['publisher_type']         = isset($metadata['disclosure_mode']) ? $metadata['disclosure_mode'] : '';
        $data['retrieved_at']           = isset($metadata['date_retrieval']) ? $metadata['date_retrieval'] : '';
        $data['created_at']             = isset($results['created_at']) ? $results['created_at'] . 'Z' : '';
        $data['category']               = isset($metadata['category']) ? $metadata['category'] : '';
        $data['note']                   = isset($metadata['contract_note']) ? $metadata['contract_note'] : '';
        $data['is_associated_document'] = isset($metadata['is_supporting_document']) ? $this->getBoolean($metadata['is_supporting_document']) : null;
        $data['deal_number']            = isset($metadata['deal_number']) ? $metadata['deal_number'] : '';
        $data['matrix_page']            = isset($metadata['matrix_page']) ? $metadata['matrix_page'] : '';
        $data['is_ocr_reviewed']        = isset($metadata['show_pdf_text']) ? $this->getBoolean($metadata['show_pdf_text']) : null;

        $data['file']       = [
            [
                "url"        => isset($metadata['file_url']) ? $metadata['file_url'] : '',
                "byte_size"  => isset($metadata['file_size']) ? (int) $metadata['file_size'] : '',
                "media_type" => "application/pdf"
            ],
            [
                "url"        => isset($metadata['word_file']) ? $metadata['word_file'] : '',
                "media_type" => "text/plain"
            ]
        ];
        $translatedFrom     = isset($metadata['translated_from']) ? $metadata['translated_from'] : [];
        $parentDocument     = $this->getSupportingDocument($translatedFrom, $category);
        $data['parent']     = $parentDocument;
        $document           = isset($results['supporting_contracts']) ? $results['supporting_contracts'] : [];
        $supportingDoc      = $this->getSupportingDocument($document, $category);
        $data['associated'] = $supportingDoc;

        return $data;
    }

    /**
     * Return Boolean values(true or false)
     *
     * @param $operator
     * @return int|string
     */
    private function getBoolean($operator)
    {
        if ($operator == - 1) {
            return null;
        }
        if ($operator == 1) {
            return true;
        }
        if ($operator == 0) {
            return false;
        }

    }

    /**
     * Return participation share values
     *
     * @param $participationShare
     * @return float|string
     */
    private function getShare($participationShare)
    {
        if (empty($participationShare)) {
            return null;
        }

        return (float) $participationShare;
    }

    /**
     * Return the signature value
     * @param $signatureYear
     * @return int|string
     */
    public function getSignatureYear($signatureYear)
    {
        if (empty($signatureYear)) {
            return null;
        }

        return (int) $signatureYear;

    }

    /**
     * Annotation download
     * @param $contractId
     */
    public function downloadAnnotationsAsCSV($contractId)
    {
        $annotations = $this->getAnnotationPages($contractId, '');
        $metadata = $this->getMetadata($contractId,'');
        $download    = new DownloadServices();

        return $download->downloadAnnotations($annotations,$metadata);

    }

    /**
     * Annotations Type(pdf or text)
     * @param $annotationId
     * @return string
     */
    private function getAnnotationType($annotationId)
    {
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $params['body']  = [
            "query" => [
                "term" => [
                    "_id" => [
                        "value" => $annotationId
                    ]
                ]
            ]
        ];
        $result          = $this->search($params);
        $result          = $result['hits']['hits'][0]["_source"];

        return isset($result['shapes']) ? "pdf" : "text";
    }

}

