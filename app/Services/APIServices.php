<?php namespace App\Services;

/**
 * API Service for client site
 * Class APIServices
 * @package App\Services
 */
class APIServices extends Services
{

    /**
     * Return the summary of contracts
     * @return array
     */
    public function getSummary()
    {
        $params         = $this->getMetadataIndexType();
        $data           = [];
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

        $response = $this->search($params);

        $data['country_summary']  = $response['aggregations']['country_summary']['buckets'];
        $data['year_summary']     = $response['aggregations']['year_summary']['buckets'];
        $data['resource_summary'] = $response['aggregations']['resource_summary']['buckets'];
        $data['contract_count']   = $this->getAllContractCount();

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
     * Text search
     * @param $request
     * @return array
     */
    public function textSearch($request)
    {
        $params          = [];
        $params['index'] = "nrgi";
        $type            = [];
        $text            = $request['q'] ?: "";
        if (isset($request['metadata'])) {
            array_push($type, "metadata");
        }
        if (isset($request['pdf-text'])) {
            array_push($type, "pdf_text");
        }
        $type = implode(',', $type);
        if (!empty($type)) {
            $params['type'] = $type;
        }
        $params['body'] = [
            "query" => [
                "query_string" => [
                    "query" => $text
                ]
            ]
        ];
        $response       = $this->search($params);

        return $response;
    }

    /**
     * Filter the data
     * @param $request
     * @param $params
     * @return array
     */
    public function filterData($request, $params)
    {
        if (isset($request['year']) and !empty($request['year'])) {
            $year      = explode(',', $request['year']);
            $filters[] = ["terms" => ["metadata.signature_year" => $year]];
        }
        if (isset($request['country']) and !empty($request['country'])) {
            $country   = explode(',', $request['country']);
            $filters[] = ["terms" => ["metadata.country.code" => $country]];
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $resource  = explode(',', $request['resource']);
            $filters[] = ["terms" => ["metadata.resource" => $resource]];
        }
        $params['body']['fields'] = [
            "contract_id",
            "metadata.contract_name",
            "metadata.signature_year",
            "metadata.file_size"
        ];

        if (isset($request['q'])) {
            $params['body']['query']['query_string']['query'] = $request['q'];
        }
        if (!empty($filters)) {
            $params['body']['filter'] = [
                "and" => [
                    "filters" => $filters
                ]
            ];
        }
        $results = $this->search($params);
        $fields  = $results['hits']['hits'];
        $data    = [];
        $i       = 0;

        foreach ($fields as $field) {
            $data[$i]['contract_name'] = isset($field['fields']['metadata.contract_name'][0]) ? $field['fields']['metadata.contract_name'][0] : '';

            $data[$i]['contract_id']    = $field['fields']['contract_id'][0];
            $data[$i]['signature_year'] = isset($field['fields']['metadata.signature_year'][0]) ? $field['fields']['metadata.signature_year'][0] : '';
            $data[$i]['file_size']      = isset($field['fields']['metadata.file_size'][0]) ? $field['fields']['metadata.file_size'][0] : '';
            $i++;
        }
        return $data;
    }

    /**
     *Filter the data from metadata,annotations and pdf_text
     * @param $request
     * @return array
     */
    public function getFilterData($request)
    {
        $data1 = $this->filterData($request, ["index" => "nrgi", "type" => "metadata"]);
        $data2 = $this->filterData($request, ["index" => "nrgi", "type" => "annotations"]);
        $data3 = $this->filterData($request, ["index" => "nrgi", "type" => "pdf_text"]);
        $data  = array_merge($data1, $data2);
        $data  = array_merge($data, $data3);
        $data  = $this->getUniqueData($data);
        return $data;
    }

    /**
     * Return the unique data
     * @param $filter
     * @return array
     */
    public function getUniqueData($filter)
    {

        $temp_array = array();
        $data       = [];
        foreach ($filter as $v) {
            if (!in_array($v['contract_id'], $temp_array)) {
                array_push($temp_array, $v['contract_id']);
                array_push($data, $v);
            }
        }
        return $data;
    }

    /**
     * Return the page of pdf text
     * @param $contractId
     * @param $pageNo
     * @return array
     */
    public function getTextPages($contractId, $pageNo)
    {
        $params['index']                         = "nrgi";
        $params['type']                          = "pdf_text";
        $params['body']['query']['bool']['must'] = [
            [
                "term" => ["contract_id" => ["value" => $contractId]],
            ],
            [
                "term" => ["page_no" => ["value" => $pageNo]]
            ]
        ];
        $results                                 = $this->search($params);
        $results                                 = $results['hits']['hits'][0]['_source'];
        return $results;

    }

    /**
     * Return the contract annotations of page
     * @param $contractId
     * @param $pageNo
     * @return array
     */
    public function getAnnotationPages($contractId, $pageNo)
    {
        $params                                  = [];
        $params['index']                         = "nrgi";
        $params['type']                          = "annotations";
        $params['body']['query']['bool']['must'] = [
            [
                "term" => ["contract_id" => ["value" => $contractId]],
            ],
            [
                "term" => ["page_no" => ["value" => $pageNo]]
            ]
        ];
        $results                                 = $this->search($params);
        $data                                    = [];
        foreach ($results['hits']['hits'] as $result) {
            $temp         = $result['_source'];
            $temp['id']   = (integer)$result['_id'];
            $data['rows'] = [$temp];
        }
        $data['total'] = count($data['rows']);
        return $data;
    }

    /**
     * Return the metadata
     * @param $contractId
     * @return mixed
     */
    public function getMetadata($contractId)
    {
        $params                   = $this->getMetadataIndexType();
        $params['body']['filter'] = [
            "and" => [
                "filters" => [
                    [
                        'term' => [
                            '_id' => $contractId
                        ]
                    ]
                ]
            ]
        ];
        $result                   = $this->search($params);
        $results                  = $result['hits']['hits'][0]['_source'];
        return $results;
    }

    /**
     * Return all the annotations of contract
     * @param $contractId
     * @return array
     */
    public function getContractAnnotations($contractId)
    {
        $params['index'] = "nrgi";
        $params['type']  = "annotations";
        $params['body']  = [
            'filter' =>
                [
                    'and' =>
                        [
                            'filters' =>
                                [
                                    0 =>
                                        [
                                            'term' =>
                                                [
                                                    'contract_id' => $contractId,
                                                ],
                                        ],
                                ],
                        ],
                ],
        ];

        $results = $this->search($params);
        $results = $results['hits']['hits'];
        $data    = [];
        foreach ($results as $result) {
            $data[] = [
                'quote'   => $result['_source']['quote'],
                'text'    => $result['_source']['text'],
                'tag'     => $result['_source']['tags'],
                'page_no' => $result['_source']['page_no'],
                'ranges'  => $result['_source']['ranges'][0],
            ];

        }
        return $data;
    }

    /**
     * Return all contracts
     * @return array
     */
    public function getAllContracts()
    {
        $params                               = $this->getMetadataIndexType();
        $params['body']['query']["match_all"] = [];
        $results                              = $this->search($params);
        $results                              = $results['hits']['hits'];
        $data                                 = [];
        foreach ($results as $result) {
            array_push($data, $result['_source']);
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
    public function pdfSearch($request)
    {
        $params['index'] = "nrgi";
        $params['type']  = "pdf_text";
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
                        "term" => [
                            "contract_id" => $request['contract_id']
                        ]
                    ]
                ]
            ],
            "highlight" => [
                "pre_tags"  => ["<b><em>"],
                "post_tags" => ["</em></b>"],
                "fields"    => [
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
        $hits            = $response['hits']['hits'];
        $data            = [];
        foreach ($hits as $hit) {
            $fields = $hit['fields'];
            $text   = $hit['highlight']['text'][0];
            if (!empty($text)) {
                $data[] = [
                    'page_no'     => $fields['page_no'][0],
                    'contract_id' => $fields['contract_id'][0],
                    'text'        => $text
                ];
            }

        }

        return $data;
    }

}
