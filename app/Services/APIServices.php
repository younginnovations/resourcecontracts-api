<?php namespace App\Services;

/**
 * API Service for client site
 * Class APIServices
 * @package App\Services
 */
class APIServices extends Services
{
    /**
     * string Sorting order default is ascending
     */
    const ORDER = "asc";
    /**
     * int Start from default is 0
     */
    const FROM = 0;
    /**
     * int items per request default is 25
     */
    const SIZE = 25;

    /**
     * Return the summary of contracts
     *
     * @param $request
     *
     * @return array
     */
    public function getSummary($request)
    {
        $params = $this->getMetadataIndexType();
        $data   = [];
        $lang   = $this->getLang($request);

        $params['body'] = [
            'size' => 0,
            'aggs' =>
                [
                    'country_summary'  =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.country.code',
                                    'size'  => 252,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                    'year_summary'     =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.signature_year',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "desc",
                                    ],
                                ],
                        ],
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.resource.raw',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                ],
        ];

        $filters = [];
        if (isset($request['category']) && !empty($request['category'])) {
            $categoryFilter = $this->getCategory($lang, $request['category']);
            array_push($filters, $categoryFilter);
        }
        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $country['term'] = [
                $lang.".country.code" => [
                    "value" => $request['country_code'],
                ],
            ];
            array_push($filters, $country);
        }
        $params['body']['query']['bool']['must'] = $filters;
        $response                                = $this->search($params);

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
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function getTextPages($contractId, array $request = [])
    {
        $params['index'] = $this->index;
        $params['type']  = "pdf_text";
        $filter          = [];
        $type            = $this->getIdType($contractId);

        if (!empty($contractId) && $type == "string") {
            $filter[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['page']) and !empty($request['page'])) {
            $filter[] = [
                "term" => ["page_no" => ["value" => $request['page']]],
            ];
        }

        $params['body'] = [
            'size'  => 10000,
            'query' => [
                'bool' => [
                    'must' => $filter,
                ],
            ],
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
                'page_no'             => $source['page_no'],
            ];
        }

        return $data;
    }

    /**
     * Get Annotation pages
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function getAnnotationPages($contractId, $request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $filter          = [];
        $lang            = $this->getLang($request);

        $type = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filter[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['page']) and !empty($request['page'])) {
            $filter[] = [
                "term" => ["page" => ["value" => $request['page']]],
            ];
        }

        $params['body'] = [
            'size'  => 10000,
            'sort'  => ['page' => ["order" => "asc"]],
            'query' => [
                'bool' => [
                    'must' => $filter,
                ],
            ],
        ];

        $results         = $this->search($params);
        $data            = [];
        $data['total']   = $results['hits']['total'];
        $i               = 0;
        $data['result']  = [];
        $remove          = [
            "Pages missing from  copy//Pages Manquantes de la copie",
            "Annexes missing from copy//Annexes Manquantes de la copie",
        ];
        $totalAnnotation = 0;

        foreach ($results['hits']['hits'] as $result) {
            $source = $result['_source'];

            if (!in_array($source['category'], $remove)) {
                $totalAnnotation    = $totalAnnotation + 1;
                $data['result'][$i] = [
                    'contract_id'         => $source['contract_id'],
                    'open_contracting_id' => $source['open_contracting_id'],
                    'id'                  => $result['_id'],
                    'annotation_id'       => isset($source['annotation_id']) ? $source['annotation_id'] : null,
                    'quote'               => isset($source['quote']) ? $source['quote'] : null,
                    'text'                => isset($source['annotation_text'][$lang]) ? $source['annotation_text'][$lang] : null,
                    'category'            => $source['category'],
                    'category_key'        => isset($source['category_key']) ? $source['category_key'] : null,
                    'article_reference'   => isset($source['article_reference'][$lang]) ? $source['article_reference'][$lang] : null,
                    'page_no'             => $source['page'],
                    'ranges'              => isset($source['ranges']) ? $source['ranges'] : null,
                    'cluster'             => isset($source['cluster']) ? $source['cluster'] : null,
                ];
                if (isset($source['shapes'])) {
                    unset($data['result'][$i]['ranges']);
                    $data['result'][$i]['shapes'] = $source['shapes'];
                }
                $i++;
            }
        }

        $data['total'] = $totalAnnotation;

        return $data;
    }

    /**
     * Get Annotations
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function getAnnotationGroup($contractId, $request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $filter          = [];

        $type = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filter[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if (!empty($contractId) && $type == "numeric") {
            $filter[] = [
                "term" => ["contract_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['page']) and !empty($request['page'])) {
            $filter[] = [
                "term" => ["page" => ["value" => $request['page']]],
            ];
        }

        $params['body'] = [
            'size'  => 10000,
            'sort'  => ['page' => ["order" => "asc"]],
            'query' => [
                'bool' => [
                    'must' => $filter,
                ],
            ],
        ];

        $results        = $this->search($params);
        $data           = [];
        $data['total']  = $results['hits']['total'];
        $data['result'] = [];
        $remove         = [
            "Pages missing from  copy//Pages Manquantes de la copie",
            "Annexes missing from copy//Annexes Manquantes de la copie",
        ];

        $annotations = $results['hits']['hits'];
        $ann         = [];
        foreach ($annotations as $annotation) {
            $annotation = $annotation['_source'];
            if (in_array($annotation['category'], $remove)) {
                continue;
            }
            $ann[$annotation['annotation_id']][] = $annotation;
        }

        $lang = $this->getLang($request);

        $final_annotations = [];
        ksort($ann);
        foreach ($ann as $id => $annotation) {
            $pages = [];
            foreach ($annotation as $page) {
                $page_array = [
                    'id'                => $page['id'],
                    'page_no'           => $page['page'],
                    'quote'             => isset($page['quote']) ? $page['quote'] : '',
                    'article_reference' => $page['article_reference'][$lang],
                ];

                if (isset($page['shapes'])) {
                    $page_array['shapes'] = $page['shapes'];
                }

                if (isset($page['ranges'])) {
                    $page_array['ranges'] = $page['ranges'];
                }
                $pages[] = $page_array;
            }

            $final_annotations[] = [
                'id'                  => $annotation[0]['id'],
                'annotation_id'       => $annotation[0]['annotation_id'],
                'contract_id'         => $annotation[0]['contract_id'],
                'open_contracting_id' => $annotation[0]['open_contracting_id'],
                'text'                => $annotation[0]['annotation_text'][$lang],
                'category_key'        => $annotation[0]['category_key'],
                'category'            => $annotation[0]['category'],
                'cluster'             => $annotation[0]['cluster'],
                'pages'               => $pages,
            ];
        }

        $data['result'] = $final_annotations;
        $data['total']  = count($final_annotations);

        return $data;
    }

    /**
     * Return the metadata
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function getMetadata($contractId, $request)
    {
        $params   = $this->getMetadataIndexType();
        $filters  = [];
        $category = '';
        $type     = $this->getIdType($contractId);
        $lang     = $this->getLang($request);
        if ($contractId && $type == "numeric") {
            $filters[] = [
                "term" => ["_id" => ["value" => $contractId]],
            ];
        }
        if ($contractId && $type == "string") {
            $filters[] = [
                "term" => [$lang.".open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {

            $category = $request['category'];
        }

        $params['body'] = [
            "_source" => [
                "exclude" => [
                    $lang.".updated_user_name",
                    $lang.".updated_user_email",
                    $lang.".updated_at",
                    $lang.".created_user_name",
                    $lang.".created_user_email",
                ],
            ],
            "query"   => [
                "bool" => [
                    "must" => $filters,
                ],
            ],
        ];


        $result = $this->search($params);
        $result = $result['hits']['hits'];
        $data   = [];

        if (!empty($result)) {
            $results = $result[0]['_source'];
            $data    = $this->formatMetadata($results, $category, $lang);
        }

        return $data;
    }

    /**
     * Return all contracts
     *
     * @param $request
     *
     * @return array
     */
    public function getAllContracts($request)
    {
        $params = $this->getMetadataIndexType();
        $filter = [];
        $lang   = $this->getLang($request);
        if (isset($request['country_code']) and !empty($request['country_code'])) {
            $filter[] = [
                "term" => [
                    $lang.".country.code" => $request['country_code'],
                ],
            ];
        }
        if (isset($request['year']) and !empty($request['year'])) {
            $filter[] = [
                "term" => [
                    $lang.".signature_year" => $request['year'],
                ],
            ];
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $filter[] = [
                "term" => [
                    $lang.".resource.raw" => $request['resource'],
                ],
            ];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filter[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        $params['body']['query']["bool"]['filter'] = $filter;

        $perPage = (isset($request['per_page']) && !empty($request['per_page'])) ? (integer) $request['per_page'] : self::SIZE;
        $perPage = ($perPage < 100) ? $perPage : 100;
        $from    = (isset($request['from']) && !empty($request['from'])) ? (integer) $request['from'] : self::FROM;
        $from    = ($from < 9900) ? $from : 9900;

        $params['body']['size'] = $perPage;
        $params['body']['from'] = $from;

        if (isset($request['download']) && $request['download']) {
            $params['body']['size'] = 10000;
        }

        if (isset($request['all']) && $request['all'] == 1) {
            $params['body']['size'] = $this->countData();
            $params['body']['from'] = 0;
        }

        if (isset($request['sort_by']) and !empty($request['sort_by'])) {

            if ($request['sort_by'] == "country") {
                $params['body']['sort'][$lang.'.country.name.raw']['order'] = (isset($request['order']) and in_array(
                        $request['order'],
                        ['desc', 'asc']
                    )) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort'][$lang.'.signature_year']['order'] = (isset($request['order']) and in_array(
                        $request['order'],
                        ['desc', 'asc']
                    )) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort'][$lang.'.contract_name.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort'][$lang.'.resource']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort'][$lang.'.type_of_contract.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
        }

        $results = $this->search($params);

        $data             = [];
        $data['total']    = $results['hits']['total'];
        $data['per_page'] = $perPage;
        $data['from']     = $from;
        $data['results']  = [];

        foreach ($results['hits']['hits'] as $result) {
            $source            = $result['_source'];
            $data['results'][] = [
                'id'                  => (integer) $source['contract_id'],
                'open_contracting_id' => $source[$lang]['open_contracting_id'],
                'name'                => $source[$lang]['contract_name'],
                'country_code'        => $source[$lang]['country']['code'],
                'year_signed'         => $this->getSignatureYear($source[$lang]['signature_year']),
                'date_signed'         => !empty($source[$lang]['signature_date']) ? $source[$lang]['signature_date'] : '',
                'contract_type'       => $source[$lang]['type_of_contract'],
                'language'            => $source[$lang]['language'],
                'resource'            => $source[$lang]['resource'],
                'category'            => $source[$lang]['category'],
                'is_ocr_reviewed'     => $this->getBoolean($source[$lang]['show_pdf_text']),
            ];
        }

        if (isset($request['download']) && $request['download']) {
            $download     = new DownloadServices();
            $downloadData = $download->getMetadataAndAnnotations($data, $request, $lang);

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
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function searchAnnotationAndText($contractId, $request)
    {
        $allText           = $this->getTextPages($contractId);
        $textResult        = $this->textSearch($contractId, $request);
        $annotationsResult = $this->annotationSearch($contractId, $request);
        $results           = array_merge($textResult, $annotationsResult);
        $sum               = 0;

        function getTextBetweenTags($string, $tagname)
        {
            $pattern = "#<$tagname.*?>([^<]+)</$tagname>#";
            preg_match_all($pattern, $string, $matches);
            $matches = array_map(
                function ($v) {
                    return strtolower($v);
                },
                $matches[1]
            );

            return array_unique($matches);
        }

        foreach ($results as $key => &$result) {
            $result['search_text'] = getTextBetweenTags($result['text'], 'span');
            $result['count']       = 0;

            if ($result['type'] == 'text') {
                foreach ($allText['result'] as $text) {
                    if ($text['page_no'] == $result['page_no']) {
                        foreach ($result['search_text'] as $search) {
                            $subCount = substr_count(strtolower($text['text']), strtolower($search));
                            $result['count'] += $subCount;
                            $sum += $subCount;
                        }
                    }
                }
            } else {
                foreach ($result['search_text'] as $search) {
                    $subCount = substr_count(strtolower($result['text']), strtolower($search));
                    $result['count'] += $subCount;
                    $sum += $subCount;
                }
            }
        }

        $data['total']              = count($results);
        $data['total_search_count'] = $sum;
        $data['results']            = $results;

        return $data;
    }

    /**
     * Annotation Search
     *
     * @param $contractId
     * @param $request
     *
     * @return array
     */
    public function annotationSearch($contractId, $request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        if ((!isset($request['q']) and empty($request['q']))) {
            return [];
        }
        $filters = [];
        $type    = $this->getIdType($contractId);

        if ($contractId && $type == "string") {
            $filters[] = [
                "term" => ["open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if ($contractId && $type == "numeric") {
            $filters[] = [
                "term" => [
                    "contract_id" => $contractId,
                ],
            ];
        }
        $queryString   = isset($request['q']) ? $request['q'] : '';
        $foundOperator = $this->findOperator($queryString);

        $lang = $this->getLang($request);

        $fullTquery = [
            'query_string' => [
                "fields"              => ["annotation_text.".$lang],
                'query'               => $this->addFuzzyOperator($queryString),
                "default_operator"    => "OR",
                "fuzzy_prefix_length" => 4,
                "fuzziness"           => "AUTO",
            ],
        ];
        if ($foundOperator) {
            $fullTquery = [
                'simple_query_string' => [
                    "fields"           => ["annotation_text.".$lang],
                    'query'            => urldecode($queryString),
                    "default_operator" => "OR",
                ],
            ];
        }

        $params['body'] = [
            "query"     => $fullTquery,
            "filter"    => [
                "and" => [
                    "filters" => $filters,
                ],
            ]
            ,
            "highlight" => [
                "pre_tags"  => ["<span class='search-highlight-word'>"],
                "post_tags" => ["</span>"],
                "fields"    => [
                    "annotation_text.".$lang => [
                        "fragment_size"       => 100000,
                        "number_of_fragments" => 1,
                    ],
                    "category"               => [
                        "fragment_size"       => 100000,
                        "number_of_fragments" => 1,
                    ],
                ],
            ],
            "fields"    => [
                "id",
                "annotation_id",
                "page",
                "shapes ",
                "contract_id",
                "open_contracting_id",
            ],
        ];

        $results = $this->search($params);
        $data    = [];
        foreach ($results['hits']['hits'] as $hit) {
            $fields = $hit['fields'];
            $text   = isset($hit['highlight']["annotation_text.".$lang]) ? $hit['highlight']["annotation_text.".$lang][0] : "";
            if ($text == "") {
                $text = isset($hit['highlight']['category']) ? $hit['highlight']['category'][0] : "";
            }
            $category        = isset($hit['highlight']['category']) ? $hit['highlight']['category'][0] : "";
            $annotationsType = $this->getAnnotationType($fields['id'][0]);

            if (!empty($text)) {
                $data[] = [
                    'id'                  => $fields['id'][0],
                    'annotation_id'       => $fields['annotation_id'][0],
                    'page_no'             => $fields['page'][0],
                    'contract_id'         => $fields['contract_id'][0],
                    'open_contracting_id' => $fields['open_contracting_id'][0],
                    'text'                => strip_tags($text, "<span>"),
                    "annotation_type"     => $annotationsType,
                    "type"                => "annotation",
                ];
            }
        }

        return $data;
    }

    /**
     * Text search
     *
     * @param $contractId
     * @param $request
     *
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
                "term" => ["open_contracting_id" => ["value" => $contractId]],
            ];
        }
        if ($contractId && $type == "numeric") {
            $filters[] = [
                "term" => [
                    "contract_id" => $contractId,
                ],
            ];
        }
        $queryString   = isset($request['q']) ? $request['q'] : '';
        $foundOperator = $this->findOperator($queryString);

        $fullTquery = [
            'query_string' => [
                "fields"              => ["text"],
                'query'               => $this->addFuzzyOperator($queryString),
                "default_operator"    => "OR",
                "fuzzy_prefix_length" => 4,
                "fuzziness"           => "AUTO",
            ],
        ];
        if ($foundOperator) {
            $fullTquery = [
                'simple_query_string' => [
                    "fields"           => ["text"],
                    'query'            => urldecode($queryString),
                    "default_operator" => "OR",
                ],
            ];
        }

        $params['body'] = [
            "query"     => $fullTquery,
            "filter"    => [
                "and" => [
                    "filters" => $filters,
                ],
            ],
            "highlight" => [
                "pre_tags"  => ["<span class='search-highlight-word'>"],
                "post_tags" => ["</span>"],
                "fields"    => [
                    "text" => [
                        "fragment_size"       => 200,
                        "number_of_fragments" => 1,
                    ],
                ],
            ],
            "fields"    => [
                "page_no",
                "contract_id",
                "open_contracting_id",
            ],
        ];

        $response = $this->search($params);

        $data = [];
        foreach ($response['hits']['hits'] as $hit) {
            $fields = $hit['fields'];
            $text   = $hit['highlight']['text'][0];
            if (!empty($text)) {
                $data[] = [
                    'page_no'             => $fields['page_no'][0],
                    'contract_id'         => $fields['contract_id'][0],
                    'open_contracting_id' => $fields['open_contracting_id'][0],
                    'text'                => strip_tags($text, "<span>"),
                    "type"                => "text",
                ];
            }

        }

        return $data;
    }

    /**
     * Get all the contract according to countries
     *
     * @param $request
     *
     * @return array
     */
    public function getCountriesContracts($request)
    {
        $params    = $this->getMetadataIndexType();
        $lang      = $this->getLang($request);
        $resources = (isset($request['resource']) && ($request['resource'] != '')) ? array_map(
            'trim',
            explode(
                ',',
                $request['resource']
            )
        ) : [];
        $filters   = [];
        if (!empty($resources)) {
            $filters[] = [
                'terms' => [
                    $lang.".resource.raw" => $resources,
                ],
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    $lang.".country.code" => $request['country_code'],
                ],
            ];
        }

        $params['body'] = [
            'size'  => 0,
            "query" => [
                "bool" => [
                    "must" => $filters,
                ],
            ],
            'aggs'  =>
                [
                    'country_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.country.code',
                                    'size'  => 252,
                                ],
                        ],
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['country_summary']['buckets'];
        foreach ($results as $result) {
            $data['results'][] = [
                'code'     => $result['key'],
                'contract' => $result['doc_count'],
            ];
        }

        return $data;
    }

    /**
     * Get resource aggregation according to country
     *
     * @param $request
     *
     * @return array
     */
    public function getResourceContracts($request)
    {
        $lang    = $this->getLang($request);
        $params  = $this->getMetadataIndexType();
        $country = (isset($request['country']) && ($request['country'] != '')) ? array_map(
            'trim',
            explode(
                ',',
                $request['country']
            )
        ) : [];
        $filters = [];
        if (!empty($country)) {
            $filters[] = [
                'terms' => [
                    $lang.".country.code" => $country,
                ],
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                'term' => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                'term' => [
                    $lang.".country.code" => $request['country_code'],
                ],
            ];
        }
        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters,
                ],
            ],
            'aggs'  =>
                [
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.resource.raw',
                                    'size'  => 1000,
                                ],
                        ],
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['resource_summary']['buckets'];
        foreach ($results as $result) {
            $data['results'][] = [
                'resource' => $result['key'],
                'contract' => $result['doc_count'],
            ];
        }

        return $data;
    }

    /**
     * Get years aggregation according to country
     *
     * @param $request
     *
     * @return array
     */
    public function getYearsContracts($request)
    {
        $lang    = $this->getLang($request);
        $params  = $this->getMetadataIndexType();
        $country = (isset($request['country']) && ($request['country'] != '')) ? array_map(
            'trim',
            explode(
                ',',
                $request['country']
            )
        ) : [];
        $filters = [];
        if (!empty($country)) {
            $filters[] = [
                'terms' => [
                    $lang.".country.code" => $country,
                ],
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    $lang.".country.code" => $request['country_code'],
                ],
            ];
        }

        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters,
                ],
            ],
            'aggs'  =>
                [
                    'year_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.signature_year',
                                    'size'  => 1000,
                                ],
                        ],
                ],
        ];

        $data['results'] = [];
        $searchResult    = $this->search($params);
        $results         = $searchResult['aggregations']['year_summary']['buckets'];

        foreach ($results as $result) {
            $data['results'][] = [
                'year'     => $result['key'],
                'contract' => $result['doc_count'],
            ];
        }

        return $data;
    }

    /**
     * Get contract aggregation by country and resource
     *
     * @param $request
     *
     * @return array
     */
    public function getContractByCountryAndResource($request)
    {
        $lang      = $this->getLang($request);
        $params    = $this->getMetadataIndexType();
        $resources = isset($request['resource']) ? array_map('trim', explode(',', $request['resource'])) : [];
        $filters   = [];
        if (!empty($resources)) {
            $filters[] = [
                'terms' => [
                    $lang.".resource" => $resources,
                ],
            ];
        }
        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        if (isset($request['country_code']) && !empty($request['country_code'])) {
            $filters[] = [
                "term" => [
                    $lang.".country.code" => $request['country_code'],
                ],
            ];
        }

        $params['body'] = [
            'size'  => 0,
            "query" => [
                "bool" => [
                    "must" => $filters,
                ],
            ],
            'aggs'  =>
                [
                    'country_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.country.code',
                                    'size'  => 1000,
                                ],
                            "aggs"  => [
                                "resource_summary" => [
                                    "terms" => [
                                        "field" => $lang.".resource.raw",
                                        'size'  => 1000,
                                    ],
                                ],
                            ],
                        ],
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
                    'contract' => $result['doc_count'],
                ];
            }
            foreach ($resourceAggs as $bucket) {
                $data['results'][] = [
                    'code'     => $result['key'],
                    'resource' => $bucket['key'],
                    'contract' => $bucket['doc_count'],
                ];
            }
            $i++;
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
        $lang            = $this->getLang($request);
        $params['index'] = $this->index;
        $params['type']  = "master";
        $data            = [];
        $filter          = [];
        if (isset($request['country_code']) and !empty($request['country_code'])) {
            $filter[] = [
                "term" => [
                    $lang.".country_code" => $request['country_code'],
                ],
            ];
        }

        if (isset($request['category']) and !empty($request['category'])) {
            $filter[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }

        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filter,

                ],
            ],
            'aggs'  =>
                [
                    'company_name'       =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.company_name',
                                    'size'  => 3000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                    'corporate_grouping' =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.corporate_grouping',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                    'contract_type'      =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.contract_type',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                    'document_type'      =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.document_type.raw',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                    'language'           =>
                        [
                            'terms' =>
                                [
                                    'field' => $lang.'.language',
                                    'size'  => 1000,
                                    'order' => [
                                        "_term" => "asc",
                                    ],
                                ],
                        ],
                ],
        ];


        $response                   = $this->search($params);
        $data['company_name']       = [];
        $data['corporate_grouping'] = [];
        $data['contract_type']      = [];
        $data['document_type']      = [];
        $data['language']           = [];
        foreach ($response['aggregations']['company_name']['buckets'] as $companyname) {
            array_push($data['company_name'], $companyname['key']);
        }
        foreach ($response['aggregations']['corporate_grouping']['buckets'] as $grouping) {
            array_push($data['corporate_grouping'], $grouping['key']);
        }
        foreach ($response['aggregations']['contract_type']['buckets'] as $type) {
            array_push($data['contract_type'], $type['key']);
        }
        foreach ($response['aggregations']['document_type']['buckets'] as $type) {
            array_push($data['document_type'], $type['key']);
        }
        foreach ($response['aggregations']['language']['buckets'] as $type) {
            array_push($data['language'], $type['key']);
        }
        $data['company_name']       = array_unique($data['company_name']);
        $data['corporate_grouping'] = array_unique($data['corporate_grouping']);
        $data['contract_type']      = array_unique($data['contract_type']);
        $data['document_type']      = array_unique($data['document_type']);
        $data['language']           = array_unique($data['language']);

        return $data;
    }

    /**
     * Get all the annotations category
     *
     * @param $request
     *
     * @return array
     */
    public function getAnnotationsCategory($request)
    {
        $lang            = $this->getLang($request);
        $params['index'] = $this->index;
        $params['type']  = "master";
        $filters         = [];

        if (isset($request['category']) && !empty($request['category'])) {
            $filters[] = [
                "term" => [
                    $lang.".category" => $request['category'],
                ],
            ];
        }
        if (isset($request['country_code']) && !empty($request['country_code'])) {

            $filters[] = [
                "term" => [
                    $lang.".country_code" => $request['country_code'],
                ],
            ];
        }
        $params['body'] = [
            'size'  => 0,
            'query' => [
                'bool' => [
                    'must' => $filters,
                ],
            ],
            'aggs'  =>
                [
                    'category_summary' =>
                        [
                            "terms" => [
                                "field" => "annotations_category",
                                "size"  => 1000,
                            ],
                        ],
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
        $remove = [
            "Pages missing from  copy//Pages Manquantes de la copie",
            "Annexes missing from copy//Annexes Manquantes de la copie",
        ];
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
     *
     * @param $request
     *
     * @return array
     */
    public function downloadMetadtaAsCSV($request)
    {
        $lang            = $this->getLang($request);
        $params['index'] = $this->index;
        $params['type']  = "metadata";
        $filters         = [];

        if (isset($request['id']) && !empty($request['id'])) {
            $filters = explode(',', $request['id']);
        }
        $params['body'] = [
            'size'  => 10000,
            'query' => [
                "terms" => [
                    "_id" => $filters,
                ],
            ],
        ];
        $searchResult   = $this->search($params);
        $data           = [];
        if ($searchResult['hits']['total'] > 0) {
            $results = $searchResult['hits']['hits'];
            foreach ($results as $result) {
                unset($result['_source'][$lang]['amla_url'], $result['_source'][$lang]['file_size'], $result['_source'][$lang]['word_file']);
                $data[] = $result['_source'][$lang];
            }
        }

        return $data;
    }

    /**
     * Return the signature value
     *
     * @param $signatureYear
     *
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
     *
     * @param $contractId
     *
     * @return array
     */
    public function downloadAnnotationsAsCSV($contractId)
    {
        $annotations = $this->getAnnotationPages($contractId, '');
        $metadata    = $this->getMetadata($contractId, '');
        $download    = new DownloadServices();

        return $download->downloadAnnotations($annotations, $metadata);
    }

    /**
     * Get Annotation detail by id
     *
     * @param $id
     * @param $request
     *
     * @return array
     */
    public function getAnnotationById($id, $request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $params['body']  = [
            "query" => [
                "term" => [
                    "annotation_id" => [
                        "value" => $id,
                    ],
                ],
            ],
        ];
        $results         = $this->search($params);
        $data            = isset($results['hits']['hits'][0]["_source"]) ? $results['hits']['hits'][0]["_source"] : [];
        $page            = [];
        $lang            = $this->getLang($request);

        foreach ($results['hits']['hits'] as $result) {
            $page[] = [
                'id'                => $result['_source']['id'],
                'page'              => $result['_source']['page'],
                'type'              => (isset($result['_source']['shapes'])) ? 'pdf' : 'text',
                'article_reference' => isset($result['_source']['article_reference'][$lang]) ? $result['_source']['article_reference'][$lang] : '',
            ];
        }
        if (!empty($data)) {
            $data['page'] = $page;
        }

        $data['text'] = isset($data['annotation_text'][$lang]) ? $data['annotation_text'][$lang] : null;
        unset($data['article_reference']);

        return $data;
    }

    /**
     * Count data
     *
     * @return int
     */
    public function countData()
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "metadata";
        $params['body']  = [
            "query" => [
                "match_all" => [],
            ],
        ];
        $count           = $this->countResult($params);

        return $count['count'];
    }

    /**
     * Remove keys from the array
     *
     * @param $items
     *
     * @return array
     */
    protected function removeKeys($items)
    {
        $i = [];

        foreach ($items as $item) {
            $i[] = $item;
        }

        return $i;
    }

    /**
     * Return the published supporting document
     *
     * @param $documents
     * @param $category
     * @param $lang
     *
     * @return array
     */
    private function getSupportingDocument($documents, $category, $lang)
    {
        $data = [];
        foreach ($documents as $document) {
            $filters   = [];
            $filters[] = [
                'term' => [
                    '_id' => [
                        'value' => (int) $document['id'],
                    ],
                ],
            ];
            if (!empty($category)) {
                $filters[] = [
                    'term' => [
                        $lang.'.category' => [
                            'value' => $category,
                        ],
                    ],
                ];
            }
            $params         = $this->getMetadataIndexType();
            $params['body'] = [
                'fields' => [$lang.".contract_name", $lang.".open_contracting_id"],
                'query'  => [
                    'bool' => [
                        'must' => $filters,
                    ],
                ],
            ];
            $result         = $this->search($params);

            if (!empty($result['hits']['hits'])) {
                $data[] = [
                    'id'                  => (int) $result['hits']['hits'][0]['_id'],
                    'open_contracting_id' => $result['hits']['hits'][0]['fields'][$lang.'.open_contracting_id'][0],
                    'name'                => $result['hits']['hits'][0]['fields'][$lang.'.contract_name'][0],
                    'is_published'        => true,
                ];
            } else {
                $data[] = [
                    'id'                  => (int) $document['id'],
                    'open_contracting_id' => '',
                    'name'                => $document['contract_name'],
                    'is_published'        => false,
                ];
            }
        }

        return $data;
    }

    /**
     * Format metadata
     *
     * @param $results
     * @param $category
     *
     * @return array
     */
    private function formatMetadata($results, $category, $lang)
    {
        $data     = [];
        $metadata = $results[$lang];

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
                "identifier" => $government['identifier'],
            ];
        }
        $data['contract_type'] = isset($metadata['type_of_contract']) ? $metadata['type_of_contract'] : '';
        $data['date_signed']   = isset($metadata['signature_date']) ? $metadata['signature_date'] : '';
        $data['year_signed']   = isset($metadata['signature_year']) ? $this->getSignatureYear(
            $metadata['signature_year']
        ) : '';
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
                        ],
                    ],
                ],
                "is_operator" => isset($company['operator']) ? $this->getBoolean($company['operator']) : null,
                "share"       => $this->getShare($company['participation_share']),
            ];
        }

        $data['project']['name']       = isset($metadata['project_title']) ? $metadata['project_title'] : '';
        $data['project']['identifier'] = isset($metadata['project_identifier']) ? $metadata['project_identifier'] : '';

        foreach ($metadata['concession'] as $concession) {
            $data['concession'][] = [
                "name"       => !empty($concession['license_name']) ? $concession['license_name'] : "",
                "identifier" => $concession['license_identifier'],
            ];
        }

        $data['source_url']             = isset($metadata['source_url']) ? $metadata['source_url'] : '';
        $data['amla_url']               = isset($metadata['amla_url']) ? $metadata['amla_url'] : '';
        $data['publisher']              = [
            'type' => isset($metadata['disclosure_mode']) ? $metadata['disclosure_mode'] : '',
            'note' => isset($metadata['disclosure_mode_text']) ? $metadata['disclosure_mode_text'] : '',
        ];
        $data['retrieved_at']           = isset($metadata['date_retrieval']) ? $metadata['date_retrieval'] : '';
        $data['created_at']             = isset($results['created_at']) ? $results['created_at'].'Z' : '';
        $data['note']                   = isset($metadata['contract_note']) ? $metadata['contract_note'] : '';
        $data['is_associated_document'] = isset($metadata['is_supporting_document']) ? $this->getBoolean(
            $metadata['is_supporting_document']
        ) : null;
        $data['deal_number']            = isset($metadata['deal_number']) ? $metadata['deal_number'] : '';
        $data['matrix_page']            = isset($metadata['matrix_page']) ? $metadata['matrix_page'] : '';
        $data['is_ocr_reviewed']        = isset($metadata['show_pdf_text']) ? $this->getBoolean(
            $metadata['show_pdf_text']
        ) : null;
        $data['is_pages_missing']       = isset($metadata['pages_missing']) ? $this->getBoolean(
            $metadata['pages_missing']
        ) : null;
        $data['is_annexes_missing']     = isset($metadata['annexes_missing']) ? $this->getBoolean(
            $metadata['annexes_missing']
        ) : null;
        $data['is_contract_signed']     = isset($metadata['is_contract_signed']) ? $this->getBoolean(
            $metadata['is_contract_signed']
        ) : true;

        $data['file']   = [
            [
                "url"        => isset($metadata['file_url']) ? $metadata['file_url'] : '',
                "byte_size"  => isset($metadata['file_size']) ? (int) $metadata['file_size'] : '',
                "media_type" => "application/pdf",
            ],
            [
                "url"        => isset($metadata['word_file']) ? $metadata['word_file'] : '',
                "media_type" => "text/plain",
            ],
        ];
        $translatedFrom = isset($metadata['translated_from']) ? $metadata['translated_from'] : [];
        $parentDocument = $this->getSupportingDocument($translatedFrom, $category, $lang);
        $data['parent'] = $parentDocument;
        $document       = isset($results['supporting_contracts']) ? $results['supporting_contracts'] : [];
        $supportingDoc  = $this->getSupportingDocument($document, $category, $lang);
        if (!empty($parentDocument)) {
            $supportingDoc = $this->getSibblingDocument($parentDocument, $results['contract_id']);
        }
        $data['associated'] = $supportingDoc;

        return $data;
    }

    /**
     * Return Boolean values(true or false)
     *
     * @param $operator
     *
     * @return boolean
     */
    private function getBoolean($operator)
    {
        if ($operator == -1) {
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
     *
     * @return float
     */
    private function getShare($participationShare)
    {
        if (empty($participationShare)) {
            return null;
        }

        return (float) $participationShare;
    }

    /**
     * Annotations Type(pdf or text)
     *
     * @param $annotationId
     *
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
                        "value" => $annotationId,
                    ],
                ],
            ],
        ];
        $result          = $this->search($params);
        $result          = $result['hits']['hits'][0]["_source"];

        return isset($result['shapes']) ? "pdf" : "text";
    }

    /**
     * Return the associated along with sibling contracts
     *
     * @param $parentDocument
     * @param $contractId
     *
     * @return array
     */
    private function getSibblingDocument($parentDocument, $contractId)
    {
        $supportingDoc = [];

        if (!empty($parentDocument)) {
            $parentId = $parentDocument[0]['id'];

            $parentMetadata = $this->getMetadata($parentId, '');

            $supportingDoc = isset($parentMetadata["associated"]) ? $parentMetadata["associated"] : [];
        }

        foreach ($supportingDoc as $key => $doc) {
            if ($doc['id'] == $contractId) {
                unset($supportingDoc[$key]);
            }
        }

        return $this->removeKeys($supportingDoc);
    }

    /**
     * Get Annotation Detail
     *
     * @param $annotation_id
     *
     * @return array
     */
    private function getAnnotaionDetails($annotation_id)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $filter          = [];

        $filter[] = [
            "term" => ["annotation_id" => ["value" => $annotation_id]],
        ];

        $params['body'] = [
            'size'  => 10000,
            'sort'  => ['page' => ["order" => "asc"]],
            'query' => [
                'bool' => [
                    'must' => $filter,
                ],
            ],
        ];

        $results = $this->search($params);

        return $results;
    }
}
