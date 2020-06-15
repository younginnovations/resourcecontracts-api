<?php namespace App\Services;

use Elasticsearch\Common\Exceptions\BadRequest400Exception;


/**
 * Class FulltextSearch
 * @package App\Services
 */
class FulltextSearch extends Services
{
    /**
     * @param APIRepositoryInterface $api
     */


    const FROM = 0;
    const SIZE = 25;

    /**
     * @var APIRepositoryInterface
     */
    private $api;

    /**
     * Format the queries and return the search result
     *
     * @param $request
     *
     * @return array
     */
    public function searchInMaster($request)
    {
        $params          = [];
        $lang            = $this->getLang($request);
        $params['index'] = $this->index;
        $params['type']  = "master";
        $type            = isset($request['group']) ? array_map('trim', explode('|', $request['group'])) : [];
        $typeCheck       = $this->typeCheck($type);

        if (!$typeCheck) {
            return [];
        }
        if (isset($request['year']) and !empty($request['year'])) {
            $year      = explode('|', $request['year']);
            $filters[] = ["terms" => [$lang.".signature_year.keyword" => $year]];
        }
        $no_hydrocarbons = false;
        $isCountrySite   = (isset($request['is_country_site']) && $request['is_country_site'] == 1) ? true : false;

        if ((isset($request['download']) && $request['download'])
            && (isset($request['country']) && !empty($request['country']))
            && (isset($request['country_code']) && empty($request['country_code']))) {
            $request['country_code'] = $request['country'];
        }

        if (isset($request['country_code']) and !empty($request['country_code'])) {

            $country   = explode('|', strtoupper($request['country_code']));
            $filters[] = ["terms" => [$lang.".country_code.keyword" => $country]];
            /*$country   = explode('|', $request['country_code']);
            $filters[] = ["terms" => [$lang.".country_code" => $country]];*/

            if (count($country) == 1 && in_array('GN', $country) && $isCountrySite) {
                $no_hydrocarbons = true;
            }
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $resource  = explode('|', $request['resource']);
            $filters[] = ["terms" => [$lang.".resource_raw.keyword" => $resource]];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filters[] = ["term" => [$lang.".category" => $request['category']]];
        }
        if (isset($request['contract_type']) and !empty($request['contract_type'])) {
            $contractType = explode('|', $request['contract_type']);
            $filters[]    = ["terms" => [$lang.".contract_type.keyword" => $contractType]];
        }
        if (isset($request['document_type']) and !empty($request['document_type'])) {
            $contractType = explode('|', $request['document_type']);
            $filters[]    = ["terms" => [$lang.".document_type.keyword" => $contractType]];
        }
        if (isset($request['language']) and !empty($request['language'])) {
            $contractType = explode('|', $request['language']);
            $filters[]    = ["terms" => [$lang.".language.keyword" => $contractType]];
        }
        if (isset($request['company_name']) and !empty($request['company_name'])) {
            $companyName = explode('|', $request['company_name']);
            $filters[]   = ["terms" => [$lang.".company_name.keyword" => $companyName]];
        }
        if (isset($request['corporate_group']) and !empty($request['corporate_group'])) {
            $corporateGroup = explode('|', $request['corporate_group']);
            $filters[]      = ["terms" => [$lang.".corporate_grouping.keyword" => $corporateGroup]];
            $filters[]      = ["terms" => [$lang.".corporate_grouping.keyword" => $corporateGroup]];
        }
        if (isset($request['annotation_category']) and !empty($request['annotation_category'])) {
            $annotationsCategory = explode('|', $request['annotation_category']);
            $filters[]           = ["terms" => ["annotations_category.keyword" => $annotationsCategory]];
        }
        if (isset($request['annotated']) and !empty($request['annotated']) and $request['annotated'] == 1) {
            $filters[] = [
                "bool" => [
                    "must" => [
                        "exists" => [
                            "field" => "annotations_string.".$lang,
                        ],
                    ],
                ],
            ];
        }

        $fields = [];
        if (in_array("metadata", $type)) {
            array_push($fields, "metadata_string.".$lang);
        }
        if (in_array("text", $type)) {
            array_push($fields, "pdf_text_string");
        }
        if (in_array("annotations", $type)) {
            array_push($fields, "annotations_string.".$lang);
        }


        $queryString = isset($request['q']) ? $request['q'] : "";

        if (!empty($queryString)) {
            $operatorFound = $this->findOperator($queryString);

            if ($operatorFound) {
                $simpleQuery =
                    [
                        'simple_query_string' => [
                            "fields"           => $fields,
                            'query'            => urldecode($queryString),
                            "default_operator" => "AND",
                        ],
                    ];
                array_push($filters, $simpleQuery);
            } else {
                $queryStringFilter = [
                    'query_string' => [
                        "fields"              => $fields,
                        'query'               => $this->addFuzzyOperator($request['q']),
                        "default_operator"    => "AND",
                        "fuzzy_prefix_length" => 4,
                    ],
                ];
                array_push($filters, $queryStringFilter);
            }
        }
        if (!empty($filters)) {
            $params['body']['query'] = [
                "bool" => [
                    "must" => $filters,
                ],
            ];
        }

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
        if (isset($request['sort_by']) and !empty($request['sort_by'])) {
            if ($request['sort_by'] == "country") {
                $params['body']['sort'][$lang.'.country_name.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort'][$lang.'.signature_year.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort'][$lang.'.contract_name.raw']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort'][$lang.'.resource_raw.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort'][$lang.'.contract_type.keyword']['order'] = $this->getSortOrder($request);
            }
        }

        $highlightField = [];

        if (in_array('text', $type)) {
            $highlightField['pdf_text_string'] = [
                'fragment_size'       => 200,
                'number_of_fragments' => 50,
            ];

        }

        if (in_array('annotations', $type)) {
            $highlightField['annotations_string.'.$lang] = [
                'fragment_size'       => 50,
                'number_of_fragments' => 1,
            ];

        }

        if (in_array('metadata', $type)) {
            $highlightField['metadata_string.'.$lang] = [
                'fragment_size'       => 50,
                'number_of_fragments' => 1,
            ];
        }

        $params['body']['highlight'] = [
            'pre_tags'  => [
                '<strong>',
            ],
            'post_tags' => [
                '</strong>',
            ],
            'fields'    => $highlightField,
        ];

        $perPage = (isset($request['per_page']) && !empty($request['per_page'])) ? (integer) $request['per_page'] : self::SIZE;
        $perPage = ($perPage < 100) ? $perPage : 100;
        $from    = (isset($request['from']) && !empty($request['from'])) && (integer) $request['from'] > -1 ? (integer) $request['from'] : self::FROM;
        $from    = ($from < 9900) ? $from : 9900;

        $params['body']['size'] = $perPage;
        $params['body']['from'] = $from;


        if ((isset($request['download']) && $request['download']) || (isset($request['all']) && $request['all'])) {
            $params['body']['size'] = $this->countAll();
            $params['body']['from'] = 0;
        }

        if ($no_hydrocarbons) {
            $params['body']['query']['bool']['must_not']['term'] = $this->excludeResource(
                'resource_raw.keyword',
                'Hydrocarbons',
                $lang
            );
        }

        $data = $this->searchText($params, $type, $queryString, $lang);
        $data['from'] = isset($request['from']) and !empty($request['from']) and (integer) $request['from'] > -1 ? $request['from'] : self::FROM;

        $data['per_page'] = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        if (isset($request['download']) && $request['download']) {
            $download     = new DownloadServices();
            $downloadData = $download->getMetadataAndAnnotations($data, $request, $lang);

            return $download->downloadSearchResult($downloadData);
        }


        return (array) $data;
    }

    /**
     * Returns main contract count
     *
     * @param $query_params
     *
     * @return mixed
     */
    public function getMainContractCount($query_params)
    {
        $params                                                                      = [];
        $params['index']                                                             = $this->index;
        $params['type']                                                              = "master";
        $params['body']['query']                                                     = $query_params;
        $params['body']['query']['bool']['must'][]['term']['is_supporting_document'] = '0';
        $count                                                                       = $this->countResult($params);

        return $count['count'];
    }

    /**
     * Returns main and associated contract count with filters
     *
     * @param $params
     *
     * @return mixed
     */
    public function getFilteredAllContractCount($params)
    {
        $temp_params                                                       = [];
        $temp_params['index']                                              = $this->index;
        $temp_params['type']                                               = "master";
        $temp_params['body']['query']['bool']['should'][0]['bool']['must'] = $params['body']['query']['bool']['must'];
        $contract_ids                                                      = $params['body']['query']['bool']['filter']['terms']['_id'];
        $temp_params['body']['query']['bool']['should'][1]['bool']         = ['filter' => ['terms' => ['_id' => $contract_ids]]];
        $count                                                             = $this->countResult($temp_params);

        return $count['count'];
    }

    /**
     * Returns paginated main contract id and contract id as arrays
     *
     * @param $params
     * @param $contract_id_array
     *
     * @return mixed
     */
    public function getMainContracts($params, $contract_id_array)
    {
        $page_size = $params['body']['size'];
        $results   = $this->search($params);
        $contracts = $results['hits']['hits'];

        if (!empty($contracts)) {

            foreach ($contracts as $contract) {
                $fields = $contract['_source'];
                /*all contracts array*/
                $contract_id_array['contract_ids'][] = (int) $contract['_id'];

                /*child contract array*/
                if ($fields['is_supporting_document'] == '1') {
                    $parent_contract_id = $fields['parent_contract']['id'];
                    $temp_ids           = [];

                    foreach ($contracts as $temp_contract) {
                        $temp_ids[] = $temp_contract['_id'];
                    }

                    if (!in_array($parent_contract_id, $temp_ids)) {
                        $temp_params                                                   = [];
                        $temp_params['type']                                           = $params['type'];
                        $temp_params['index']                                          = $params['index'];
                        $temp_params['body']['_source']                                = [
                            '_id',
                            'supporting_contracts',
                        ];
                        $temp_params['body']['query']['bool']['must'][]['term']['_id'] = $parent_contract_id;
                        $parent_contract                                               = $this->search($temp_params);
                        $parent_contract                                               = $parent_contract['hits']['hits'];

                        if (!empty($parent_contract)) {
                            $temp_supporting_contracts = $parent_contract[0]['_source']['supporting_contracts'];

                            if (!empty($temp_supporting_contracts)) {
                                foreach ($temp_supporting_contracts as $temp_supporting_contract) {
                                    $contract_id_array['contract_ids'][] = $temp_supporting_contract['id'];
                                }
                            }
                        }
                    }
                    $contract_id_array['contract_ids'][]      = $fields['parent_contract']['id'];
                    $contract_id_array['main_contract_ids'][] = $fields['parent_contract']['id'];
                } /*main contract array*/
                else {
                    $temp_supporting_contracts = $contract['_source']['supporting_contracts'];

                    if (!empty($temp_supporting_contracts)) {
                        foreach ($temp_supporting_contracts as $temp_supporting_contract) {
                            $contract_id_array['contract_ids'][] = $temp_supporting_contract['id'];
                        }
                    }
                    $contract_id_array['main_contract_ids'][] = (int) $contract['_id'];
                }

                if (count(array_unique($contract_id_array['main_contract_ids'])) == $page_size) {
                    break;
                }
            }

            $contract_id_array['main_contract_ids'] = array_values(
                array_unique($contract_id_array['main_contract_ids'])
            );
            $contract_id_array['contract_ids']      = array_values(array_unique($contract_id_array['contract_ids']));

            if (count($contract_id_array['main_contract_ids']) < $page_size) {
                $params['body']['query']['bool']['must_not']['bool']['filter']['terms']['_id'] = $contract_id_array['contract_ids'];

                return $this->getMainContracts($params, $contract_id_array);
            }
        }

        return $contract_id_array;
    }

    /**
     * Returns paginated main contract id and contract id as arrays
     *
     * @param $params
     *
     * @return mixed
     */
    public function getFilteredContracts($params)
    {
        $from                        = $params['body']['from'];
        $page_size                   = $params['body']['size'];
        $params['body']['size']      = $this->countAll();
        $params['body']['from']      = 0;
        $paginated_contract_ids      = [];
        $paginated_main_contract_ids = [];
        $main_contract_ids           = [];
        $results                     = $this->search($params);
        $contracts                   = $results['hits']['hits'];

        if (!empty($contracts)) {
            $main_supporting_contracts = [];

            foreach ($contracts as $contract) {
                $fields      = $contract['_source'];
                $contract_id = (int) $contract['_id'];

                if ($fields['is_supporting_document'] == '1') {
                    $parent_contract_id = (int) $fields['parent_contract']['id'];
                    $main_contract_ids[] = $parent_contract_id;
                    $main_supporting_contracts[$parent_contract_id][] = ['id'=>$contract_id];
                } else {
                    $main_contract_ids[]       = $contract_id;
                    $temp_supporting_contracts = $contract['_source']['supporting_contracts'];

                    if (!empty($temp_supporting_contracts)) {
                        $main_supporting_contracts[$contract_id] = $temp_supporting_contracts;
                    }
                }
            }
            $main_contract_ids               = array_values(array_unique($main_contract_ids));
            $to_be_spliced_main_contract_ids = $main_contract_ids;
            $paginated_main_contract_ids     = array_splice($to_be_spliced_main_contract_ids, $from, $page_size);

            foreach ($paginated_main_contract_ids as $paginated_main_contract_id) {
                $paginated_contract_ids[] = $paginated_main_contract_id;

                if (isset($main_supporting_contracts[$paginated_main_contract_id])) {
                    $paginated_temp_supporting_contracts = $main_supporting_contracts[$paginated_main_contract_id];

                    foreach ($paginated_temp_supporting_contracts as $paginated_temp_supporting_contract) {
                        $paginated_contract_ids[] = $paginated_temp_supporting_contract['id'];
                    }
                }

            }
        }

        return ['main_contract_ids' => $paginated_main_contract_ids, 'contract_ids' => $paginated_contract_ids];
    }

    /**
     * Manages main and all contract ids
     *
     * @param $contracts
     *
     * @return array[]
     */
    public function indexContracts($contracts)
    {
        $indexed_parent_contracts = [];
        $indexed_contracts        = [];

        foreach ($contracts as $contract) {
            $contract_id                     = (int) $contract['_id'];
            $indexed_contracts[$contract_id] = $contract;

            if (!in_array($contract_id, $indexed_parent_contracts)) {
                if ($contract['_source']['is_supporting_document'] == '0') {
                    $indexed_parent_contracts[] = $contract_id;
                } elseif ($contract['_source']['is_supporting_document'] == '1'
                    && !empty($contract['_source']['parent_contract'])
                    && !in_array((int) $contract['_source']['parent_contract']['id'], $indexed_parent_contracts)) {
                    $indexed_parent_contracts[] = $contract['_source']['parent_contract']['id'];
                }
            }
        }

        return ['indexed_parent_contracts' => $indexed_parent_contracts, 'indexed_contracts' => $indexed_contracts];
    }

    /**
     * Indexes the parent and all contracts
     *
     * @param $contracts
     * @param $contract_ids
     * @param $params
     *
     * @return array[]
     */
    public function getIndexContracts($contracts, $contract_ids, $params)
    {
        $contract_ids_not_found = [];
        $indexed_contract_array = $this->indexContracts($contracts);

        foreach ($contract_ids as $contract_id) {
            if (!array_key_exists($contract_id, $indexed_contract_array['indexed_contracts'])) {
                $contract_ids_not_found[] = $contract_id;
            }
        }

        if (!empty($contract_ids_not_found)) {
            $parent_contract_ids_not_found = [];

            foreach ($contract_ids_not_found as $contract_id_not_found) {
                if (in_array($contract_id_not_found, $indexed_contract_array['indexed_parent_contracts'])) {
                    $parent_contract_ids_not_found[] = $contract_id_not_found;
                }
            }
            $params['body']['query']                                   = [];
            $params['body']['query']['bool']['filter']['terms']['_id'] = $parent_contract_ids_not_found;
            $contracts                                                 = $this->search($params);
            $contracts                                                 = $contracts['hits']['hits'];
            $temp_indexed_contract_array                               = $this->indexContracts($contracts);
            $indexed_contract_array['indexed_contracts']               = $indexed_contract_array['indexed_contracts'] +
                $temp_indexed_contract_array['indexed_contracts'];
            $indexed_contract_array['indexed_parent_contracts']        = $indexed_contract_array['indexed_parent_contracts']
                + $temp_indexed_contract_array['indexed_parent_contracts'];
        }

        return $indexed_contract_array;
    }

    /**
     * Maps source data to contract
     *
     * @param $source
     * @param $data
     *
     * @return mixed
     */
    public function mapSource($source, $data)
    {
        if (isset($source['country_code'])) {
            array_push($data['country'], $this->getValueOfField($source, 'country_code'));
        }
        if (isset($source['signature_year'])) {
            array_push($data['year'], (int) $this->getValueOfField($source, 'signature_year'));
        }
        if (isset($source['contract_type'])) {
            array_push($data['contract_type'], $this->getValueOfField($source, 'contract_type'));
        }
        if (isset($source['resource'])) {
            $data['resource'] = array_merge($data['resource'], $this->getValuesOfField($source, 'resource'));
        }
        if (isset($source['company_name'])) {
            $data['company_name'] = array_merge(
                $data['company_name'],
                $this->getValuesOfField($source, 'company_name')
            );
        }
        if (isset($source['corporate_grouping'])) {
            $data['corporate_group'] = array_merge(
                $data['corporate_group'],
                $this->getValuesOfField($source, 'corporate_grouping')
            );
        }

        return $data;
    }

    /**
     * Maps field data to contract
     *
     * @param $temp_contract
     * @param $lang
     * @param $queryString
     * @param $type
     *
     * @return array
     */
    public function mapContractFields($temp_contract, $lang, $queryString, $type)
    {
        $id          = (int) $temp_contract['_id'];
        $source      = $temp_contract['_source'];
        $source_lang = $temp_contract['_source'][$lang];
        $score       = $temp_contract['_score'];

        $contract = [
            "id"                     => $id,
            "score"                  => $score,
            "open_contracting_id"    => $this->getValueOfField($source_lang, 'open_contracting_id'),
            "name"                   => $this->getValueOfField($source_lang, 'contract_name'),
            "year_signed"            => $this->getValueOfField($source_lang, 'signature_year'),
            "contract_type"          => $this->getValuesOfField($source_lang, 'contract_type'),
            "resource"               => $this->getValuesOfField($source_lang, 'resource'),
            'country_code'           => $this->getValueOfField($source_lang, 'country_code'),
            "language"               => $this->getValueOfField($source_lang, 'language'),
            "category"               => $this->getValuesOfField($source_lang, 'category'),
            "is_ocr_reviewed"        => isset($source_lang['show_pdf_text']) ? $this->getBoolean(
                (int) $this->getValueOfField($source_lang, 'show_pdf_text')
            ) : null,
            "is_supporting_document" => $source['is_supporting_document'],
            "supporting_contracts"   => empty($source['supporting_contracts']) ? null : $source['supporting_contracts'],
            "translated_from"        => ($source['is_supporting_document'] == '1') ? $source['parent_contract'] : [],
        ];
        if ($source['is_supporting_document'] == '0') {
            $contract['children'] = [];
        }
        $contract['group']       = [];
        $highlight               = isset($temp_contract['highlight']) ? $temp_contract['highlight'] : '';
        $contract['text']        = isset($highlight['pdf_text_string'][0]) ? $highlight['pdf_text_string'][0] : '';
        $annotationText          = isset($highlight['annotations_string.'.$lang][0]) ? $highlight['annotations_string.'.$lang][0] : '';
        $apiService              = new APIServices();
        $annotationsResult       = ($queryString != "") ? $apiService->annotationSearch(
            $contract['id'],
            [
                "q"    => $queryString,
                'lang' => $lang,
            ]
        ) : [];
        $contract['annotations'] = ($annotationText != "") ? $this->getAnnotationsResult(
            $annotationsResult
        ) : [];

        $contract['metadata'] = isset($highlight['metadata_string'][0]) ? $highlight['metadata_string'][0] : '';
        if (isset($highlight['pdf_text_string']) and in_array('text', $type)) {
            array_push($contract['group'], "Text");
        }
        if (isset($highlight['metadata_string']) and in_array('metadata', $type)) {
            array_push($contract['group'], "Metadata");
        }
        if (
            isset($highlight['annotations_string']) and in_array(
                'annotations',
                $type
            ) and !empty($contract['annotations'])
        ) {
            array_push($contract['group'], "Annotation");
        }

        return $contract;
    }

    /**
     * Returns mapped contract
     *
     * @param $params
     * @param $contract_ids
     * @param $lang
     * @param $type
     * @param $queryString
     * @param $only_default_filter
     * @param $only_default_filter_params
     *
     * @return mixed
     */
    public function rearrangeContracts(
        $params,
        $contract_ids,
        $lang,
        $type,
        $queryString,
        $only_default_filter,
        $only_default_filter_params
    ) {
        $params['body']['from']                                    = 0;
        $params['body']['query']['bool']['filter']['terms']['_id'] = $contract_ids;
        $params['body']['size']                                    = $this->getFilteredAllContractCount($params);

        $results                 = $this->search($params);
        $fields                  = $results['hits']['hits'];
        $data['total']           = $results['hits']['total'];
        $data['country']         = [];
        $data['year']            = [];
        $data['resource']        = [];
        $data['results']         = [];
        $data['contract_type']   = [];
        $data['company_name']    = [];
        $data['corporate_group'] = [];
        $main_contracts          = [];

        $indexed_contract_array = $this->getIndexContracts(
            $fields,
            $contract_ids,
            $params
        );

        $indexed_contracts        = $indexed_contract_array['indexed_contracts'];
        $indexed_parent_contracts = $indexed_contract_array['indexed_parent_contracts'];


        foreach ($indexed_parent_contracts as $parent_contract_id) {
            if (isset($indexed_contracts[$parent_contract_id])) {
                $temp_main_contract                  = $indexed_contracts[$parent_contract_id];
                $source                              = $temp_main_contract['_source'];
                $data                                = $this->mapSource($source[$lang], $data);
                $main_contracts[$parent_contract_id] = $this->mapContractFields(
                    $temp_main_contract,
                    $lang,
                    $queryString,
                    $type
                );

                if (isset($temp_main_contract['_source']['supporting_contracts'])) {
                    $temp_child_contracts = $temp_main_contract['_source']['supporting_contracts'];

                    foreach ($temp_child_contracts as $child_key => $child_contract) {
                        $main_contracts[$parent_contract_id]['supporting_contracts'][$child_key]['is_published'] = false;

                        if (isset($indexed_contracts[$child_contract['id']])) {
                            $temp_parent_id = $indexed_contracts[$child_contract['id']]['_source']['parent_contract']['id'];

                            if ($parent_contract_id == $temp_parent_id) {
                                $temp_child_contract                                                                     = $indexed_contracts[$child_contract['id']];
                                $child_source                                                                            = $temp_child_contract['_source'];
                                $data                                                                                    = $this->mapSource(
                                    $child_source[$lang],
                                    $data
                                );
                                $main_contracts[$parent_contract_id]['children'][]                                       = $this->mapContractFields(
                                    $temp_child_contract,
                                    $lang,
                                    $queryString,
                                    $type
                                );
                                $main_contracts[$parent_contract_id]['supporting_contracts'][$child_key]['is_published'] = true;
                            }
                        }
                    }
                }
            }
        }

        foreach ($main_contracts as $main_key => $main_contract) {
            $supporting_contracts           = $main_contract['supporting_contracts'];
            $temp_params                    = [
                'index' => $params['index'],
                'type'  => $params['type'],
            ];
            $temp_params['body']['_source'] = ['id'];

            if (!empty($supporting_contracts)) {
                foreach ($supporting_contracts as $supporting_key => $supporting_contract) {
                    if (!isset($supporting_contract['is_published']) || !$supporting_contract['is_published']) {
                        $temp_params['body']['query']['bool']['must']['term'] = ['_id' => $supporting_contract['id']];
                        $published_child_contract                             = $this->search($temp_params);

                        if (!empty($published_child_contract['hits']['hits'])) {
                            $main_contracts[$main_key]['supporting_contracts'][$supporting_key]['is_published'] = true;
                        }
                    }
                }
            }
        }

        $data['result_total']    = ($only_default_filter) ? $this->getMainContractCount(
            $only_default_filter_params['body']['query']
        ) : $this->getContractCount($params, true);
        $data['results']         = $main_contracts;
        $data['country']         = (isset($data['country']) && !empty($data['country'])) ? array_unique(
            $data['country']
        ) : [];
        $data['year']            = (isset($data['year']) && !empty($data['year'])) ? array_filter(
            array_unique($data['year'])
        ) : [];
        $data['resource']        = (isset($data['resource']) && !empty($data['resource'])) ? array_filter(
            array_unique($data['resource'])
        ) : [];
        $data['contract_type']   = (isset($data['contract_type']) && !empty($data['contract_type'])) ? array_filter(
            array_unique($data['contract_type'])
        ) : [];
        $data['company_name']    = (isset($data['company_name']) && !empty($data['company_name'])) ? array_filter(
            array_unique($data['company_name'])
        ) : [];
        $data['corporate_group'] = (isset($data['corporate_group']) && !empty($data['corporate_group'])) ? array_filter(
            array_unique($data['corporate_group'])
        ) : [];
        asort($data['country']);
        asort($data['year']);
        asort($data['resource']);
        asort($data['contract_type']);
        asort($data['company_name']);
        asort($data['corporate_group']);

        return $data;
    }

    /**
     * Return the result
     *
     * @param      $params
     * @param      $type
     * @param      $lang
     * @param      $queryString
     * @param bool $only_default_filter
     *
     * @return array
     */
    public function groupedSearchText($params, $type, $lang, $queryString, $only_default_filter = false)
    {
        try {
            $temp_params                    = $params;
            $temp_params['body']['_source'] = [
                $lang.'.open_contracting_id',
                'is_supporting_document',
                'supporting_contracts',
                'parent_contract',
            ];

            if ($only_default_filter) {
                $temp_params['body']['query']['bool']['must'][]['term']['is_supporting_document'] = '0';
                $contract_id_array                                                                = $this->getMainContracts(
                    $temp_params,
                    ['main_contract_ids' => [], 'contract_ids' => []]
                );
            } else {
                $contract_id_array = $this->getFilteredContracts($temp_params);
            }

            return $this->rearrangeContracts(
                $params,
                $contract_id_array['contract_ids'],
                $lang,
                $type,
                $queryString,
                $only_default_filter,
                $temp_params
            );
        } catch (BadRequest400Exception $e) {
            $results['hits']['hits']  = [];
            $results['hits']['total'] = 0;
            $metaData['hits']['hits'] = [];
        }
    }

    /**
     * Return the result
     *
     * @param $params
     *
     * @return array
     */
    public function searchText($params, $type, $queryString, $lang)
    {
        $data = [];
        try {
            $results = $this->search($params);
        } catch (BadRequest400Exception $e) {
            $results['hits']['hits']  = [];
            $results['hits']['total'] = 0;
        }

        $fields                  = $results['hits']['hits'];
        $data['total']           = $results['hits']['total'];
        $data['country']         = [];
        $data['year']            = [];
        $data['resource']        = [];
        $data['results']         = [];
        $data['contract_type']   = [];
        $data['company_name']    = [];
        $data['corporate_group'] = [];

        $i = 0;

        foreach ($fields as $field) {
            $contractId = $field['_id'];
            $source     = $field['_source'][$lang];
            if (isset($source['country_code'])) {
                array_push($data['country'], $this->getValueOfField($source, 'country_code'));
            }
            if (isset($source['signature_year'])) {
                array_push($data['year'], (int) $this->getValueOfField($source, 'signature_year'));
            }
            if (isset($source['contract_type'])) {
                array_push($data['contract_type'], $this->getValueOfField($source, 'contract_type'));
            }
            if (isset($source['resource'])) {
                $data['resource'] = array_merge($data['resource'], $this->getValuesOfField($source, 'resource'));
            }
            if (isset($source['company_name'])) {
                $data['company_name'] = array_merge(
                    $data['company_name'],
                    $this->getValuesOfField($source, 'company_name')
                );
            }
            if (isset($source['corporate_grouping'])) {
                $data['corporate_group'] = array_merge(
                    $data['corporate_group'],
                    $this->getValuesOfField($source, 'corporate_grouping')
                );
            }

            $data['results'][$i]          = [
                "id"                  => (int) $contractId,
                "open_contracting_id" => $this->getValueOfField($source, 'open_contracting_id'),
                "name"                => $this->getValueOfField($source, 'contract_name'),
                "year_signed"         => $this->getValueOfField($source, 'signature_year'),
                "contract_type"       => $this->getValuesOfField($source, 'contract_type'),
                "resource"            => $this->getValuesOfField($source, 'resource'),
                'country_code'        => $this->getValueOfField($source, 'country_code'),
                "language"            => $this->getValueOfField($source, 'language'),
                "category"            => $this->getValuesOfField($source, 'category'),
                "is_ocr_reviewed"     => isset($source['show_pdf_text']) ? $this->getBoolean(
                    (int) $this->getValueOfField($source, 'show_pdf_text')
                ) : null,
            ];
            $data['results'][$i]['group'] = [];
            $highlight                    = isset($field['highlight']) ? $field['highlight'] : '';
            $data['results'][$i]['text']  = isset($highlight['pdf_text_string'][0]) ? $highlight['pdf_text_string'][0] : '';
            $annotationText               = isset($highlight['annotations_string.'.$lang][0]) ? $highlight['annotations_string.'.$lang][0] : '';
            $apiService                   = new APIServices();
            $annotationsResult            = ($queryString != "") ? $apiService->annotationSearch(
                $data['results'][$i]['id'],
                [
                    "q"    => $queryString,
                    'lang' => $lang,
                ]
            ) : [];

            $data['results'][$i]['annotations'] = ($annotationText != "") ? $this->getAnnotationsResult(
                $annotationsResult
            ) : [];
            $data['results'][$i]['metadata']    = isset($highlight['metadata_string'][0]) ? $highlight['metadata_string'][0] : '';
            if (isset($highlight['pdf_text_string']) and in_array('text', $type)) {
                array_push($data['results'][$i]['group'], "Text");
            }
            if (isset($highlight['metadata_string']) and in_array('metadata', $type)) {
                array_push($data['results'][$i]['group'], "Metadata");
            }
            if (isset($highlight['annotations_string']) and in_array(
                    'annotations',
                    $type
                ) and !empty($data['results'][$i]['annotations'])
            ) {
                array_push($data['results'][$i]['group'], "Annotation");
            }

            $i++;
        }
        $data['country']         = (isset($data['country']) && !empty($data['country'])) ? array_unique(
            $data['country']
        ) : [];
        $data['year']            = (isset($data['year']) && !empty($data['year'])) ? array_filter(
            array_unique($data['year'])
        ) : [];
        $data['resource']        = (isset($data['resource']) && !empty($data['resource'])) ? array_filter(
            array_unique($data['resource'])
        ) : [];
        $data['contract_type']   = (isset($data['contract_type']) && !empty($data['contract_type'])) ? array_filter(
            array_unique($data['contract_type'])
        ) : [];
        $data['company_name']    = (isset($data['company_name']) && !empty($data['company_name'])) ? array_filter(
            array_unique($data['company_name'])
        ) : [];
        $data['corporate_group'] = (isset($data['corporate_group']) && !empty($data['corporate_group'])) ? array_filter(
            array_unique($data['corporate_group'])
        ) : [];
        asort($data['country']);
        asort($data['year']);
        asort($data['resource']);
        asort($data['contract_type']);
        asort($data['company_name']);
        asort($data['corporate_group']);

        return $data;
    }

    /**
     * Check the type of group
     *
     * @param $type
     *
     * @return bool
     */
    public function typeCheck($type)
    {
        $check = false;
        if (in_array('metadata', $type) or in_array('text', $type) or in_array('annotations', $type)) {
            $check = true;
        }

        return $check;
    }


    /**
     * Return the values of signature year
     *
     * @param $signatureYear
     *
     * @return int|string
     */
    public function getSignatureYear($signatureYear)
    {
        if (empty($signatureYear)) {
            return '';
        }

        return (int) $signatureYear;

    }

    /**
     * Fetch latest 90d contracts
     *
     * @param      $request
     * @param bool $only_default_filter
     * @param bool $free_text_filter
     * @param bool $recent
     *
     * @return array
     */
    public function searchInMasterWithWeight(
        $request,
        $only_default_filter = false,
        $free_text_filter = false,
        $recent = false
    ) {
        $params          = [];
        $lang            = $this->getLang($request);
        $params['index'] = $this->index;
        $params['type']  = "master";
        $type            = isset($request['group']) ? array_map('trim', explode('|', $request['group'])) : [];
        $typeCheck       = $this->typeCheck($type);

        if (!$typeCheck) {
            return [];
        }
        if (isset($request['year']) and !empty($request['year'])) {
            $year      = explode('|', $request['year']);
            $filters[] = ["terms" => [$lang.".signature_year.keyword" => $year]];
        }
        $no_hydrocarbons = false;
        $isCountrySite   = (isset($request['is_country_site']) && $request['is_country_site'] == 1) ? true : false;

        if ((isset($request['download']) && $request['download']) && (isset($request['country']) && !empty($request['country'])) && (isset($request['country_code']) && empty($request['country_code']))) {
            $request['country_code'] = $request['country'];
        }

        if (isset($request['country_code']) and !empty($request['country_code'])) {
            $country   = explode('|', strtoupper($request['country_code']));
            $filters[] = ["terms" => [$lang.".country_code.keyword" => $country]];

            if (count($country) == 1 && in_array('GN', $country) && $isCountrySite) {
                $no_hydrocarbons = true;
            }
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $resource  = explode('|', $request['resource']);
            $filters[] = ["terms" => [$lang.".resource_raw.keyword" => $resource]];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filters[] = $rc = ["term" => [$lang.".category" => $request['category']]];
        }
        if (isset($request['contract_type']) and !empty($request['contract_type'])) {
            $contractType = explode('|', $request['contract_type']);
            $filters[]    = ["terms" => [$lang.".contract_type.keyword" => $contractType]];
        }
        if (isset($request['document_type']) and !empty($request['document_type'])) {
            $contractType = explode('|', $request['document_type']);
            $filters[]    = ["terms" => [$lang.".document_type.keyword" => $contractType]];
        }
        if (isset($request['language']) and !empty($request['language'])) {
            $contractType = explode('|', $request['language']);
            $filters[]    = ["terms" => [$lang.".language.keyword" => $contractType]];
        }
        if (isset($request['company_name']) and !empty($request['company_name'])) {
            $companyName = explode('|', $request['company_name']);
            $filters[]   = ["terms" => [$lang.".company_name.keyword" => $companyName]];
        }
        if (isset($request['corporate_group']) and !empty($request['corporate_group'])) {
            $corporateGroup = explode('|', $request['corporate_group']);
            $filters[]      = ["terms" => [$lang.".corporate_grouping.keyword" => $corporateGroup]];
            $filters[]      = ["terms" => [$lang.".corporate_grouping.keyword" => $corporateGroup]];
        }
        if (isset($request['annotation_category']) and !empty($request['annotation_category'])) {
            $annotationsCategory = explode('|', $request['annotation_category']);
            $filters[]           = ["terms" => ["annotations_category.keyword" => $annotationsCategory]];
        }
        if (isset($request['annotated']) and !empty($request['annotated']) and $request['annotated'] == 1) {
            $filters[] = [
                "bool" => [
                    "must" => [
                        "exists" => [
                            "field" => "annotations_string.".$lang,
                        ],
                    ],
                ],
            ];
        }

        $fields = [];
        
        if (in_array("metadata", $type)) {
            array_push($fields, "metadata_string.".$lang);
        }
        if (in_array("text", $type)) {
            array_push($fields, "pdf_text_string^0.2");
        }
        if (in_array("annotations", $type)) {
            array_push($fields, "annotations_string.".$lang."^0.6");
        }

        $queryString = isset($request['q']) ? $request['q'] : "";

        if (!empty($queryString)) {
            $operatorFound = $this->findOperator($queryString);

            if ($operatorFound) {
                $simpleQuery = [
                    'simple_query_string' => [
                        "fields"           => $fields,
                        'query'            => urldecode($queryString),
                        "default_operator" => "AND",
                    ],
                ];
                array_push($filters, $simpleQuery);
            } else {
                $queryStringFilter = [
                    'query_string' => [
                        "fields"              => $fields,
                        'query'               => $this->addFuzzyOperator($request['q']),
                        "default_operator"    => "AND",
                        "fuzzy_prefix_length" => 4,
                    ],
                ];
                array_push($filters, $queryStringFilter);
            }
        }

        if ($recent) {
            array_set($filters[], "range.published_at.gte", "now-90d/d");
        }

        if (!empty($filters)) {
            $params['body']['query'] = [
                "bool" => [
                    "must" => $filters,
                ],
            ];
        }

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
            "is_supporting_document",
            "supporting_contracts",
            "parent_contract",
            "published_at",
        ];
        // $params['body']['sort']['published_at']['order'] = 'desc';
        // $params['body']['sort']['_score']['order']       = 'desc';

        if (isset($request['sort_by']) and !empty($request['sort_by'])) {

            if ($request['sort_by'] == "country") {
                $params['body']['sort'][$lang.'.country_name.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort'][$lang.'.signature_year.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort'][$lang.'.contract_name.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort'][$lang.'.resource_raw.keyword']['order'] = $this->getSortOrder($request);
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort'][$lang.'.contract_type.keyword']['order'] = $this->getSortOrder($request);
            }
        }

        if (($only_default_filter && (!isset($request['sort_by']) || empty($request['sort_by']))) || !$free_text_filter) {
            $params['body']['sort'][$lang.'.signature_year.keyword']['order'] = 'desc';
        }

        $highlightField = [];

        if (in_array('text', $type)) {
            $highlightField['pdf_text_string'] = [
                'fragment_size'       => 200,
                'number_of_fragments' => 50,
            ];

        }

        if (in_array('annotations', $type)) {
            $highlightField['annotations_string.'.$lang] = [
                'fragment_size'       => 50,
                'number_of_fragments' => 1,
            ];

        }

        if (in_array('metadata', $type)) {
            $highlightField['metadata_string.'.$lang] = [
                'fragment_size'       => 50,
                'number_of_fragments' => 1,
            ];
        }

        $params['body']['highlight'] = [
            'pre_tags'  => [
                '<strong>',
            ],
            'post_tags' => [
                '</strong>',
            ],
            'fields'    => $highlightField,
        ];

        if ($no_hydrocarbons) {
            $params['body']['query']['bool']['must_not']['term'] = $this->excludeResource(
                'resource_raw.keyword',
                'Hydrocarbons',
                $lang
            );
        }

        $page_size = (isset($request['per_page']) && !empty($request['per_page'])) ? (integer) $request['per_page'] : self::SIZE;
        $page_size = ($page_size < 100) ? $page_size : 100;
        $from      = (isset($request['from']) && !empty($request['from'])) && (integer) $request['from'] > -1 ? (integer) $request['from'] : self::FROM;
        $from      = ($from < 9900) ? $from : 9900;

        $params['body']['size'] = $page_size;
        $params['body']['from'] = $from;
        $data                   = $this->groupedSearchText($params, $type, $lang, $queryString, $only_default_filter);
        $data['results']        = $this->manualSort($data['results'], $request);
        $data['total']          = $this->getContractCount($params, false);
        $data['from']           = $from;
        $data['per_page']       = $page_size;

        return $data;
    }

    /**
     * Return boolean
     *
     * @param $param
     *
     * @return bool
     */
    private function getBoolean($param)
    {
        if ($param == 0) {
            return false;
        }
        if ($param == 1) {
            return true;
        }
    }

    /**
     * Annotation result for search
     *
     * @param $annotationsResult
     *
     * @return array
     */
    private function getAnnotationsResult($annotationsResult)
    {
        $data = [];
        if (isset($annotationsResult[0]) && !empty($annotationsResult[0])) {
            $data = [
                "id"              => $annotationsResult[0]["id"],
                "annotation_text" => $annotationsResult[0]["text"],
                "annotation_id"   => $annotationsResult[0]["annotation_id"],
                "page_no"         => $annotationsResult[0]["page_no"],
                "type"            => $annotationsResult[0]["annotation_type"],
            ];
        }

        return $data;
    }

    /*
     * Get Suggestion Text
     *
     * @param $params
     * @param $q
     * @return array
     */

    private function getSuggestionText($params, $q)
    {

        $params['body']['size'] = 0;

        $q           = urldecode($q);
        $queryLength = str_word_count($q);

        $filter = [
            "text"                   => $q,
            "text_suggestion"        => [
                "term" => [
                    "field"         => "pdf_text_string",
                    "suggest_mode"  => "always",
                    "prefix_length" => 3,
                ],
            ],
            "annotations_suggestion" => [
                "term" => [
                    "field"         => "annotations_string",
                    "suggest_mode"  => "always",
                    "prefix_length" => 3,
                ],
            ],
        ];
        if ($queryLength > 1) {
            $filter = [
                "text"                   => $q,
                "text_suggestion"        => [
                    "phrase" => [
                        "field"                      => "pdf_text_string",
                        "real_word_error_likelihood" => 0.50,
                        "size"                       => 1,
                        "max_errors"                 => 0.5,
                        "gram_size"                  => 2,
                    ],
                ],
                "annotations_suggestion" => [
                    "phrase" => [
                        "field"                      => "annotations_string",
                        "real_word_error_likelihood" => 0.50,
                        "size"                       => 1,
                        "max_errors"                 => 0.5,
                        "gram_size"                  => 2,
                    ],
                ],
            ];
        }

        $params['body']['suggest'] = $filter;
        try {
            $suggestion = $this->search($params);
        } catch (BadRequest400Exception $e) {

        }

        $suggestions        = isset($suggestion['suggest']) ? $suggestion['suggest'] : [];
        $annotationsSuggest = $this->formatSuggestedData($suggestions, 'annotations_suggestion');
        $textSuggestion     = $this->formatSuggestedData($suggestions, 'text_suggestion');
        $intersections      = array_intersect_key($annotationsSuggest, $textSuggestion);
        $complement         = array_diff_key($annotationsSuggest, $textSuggestion);
        foreach ($intersections as $intersection) {
            $freq                                          = $intersection['freq'];
            $textSuggestion[$intersection['text']]['freq'] = $freq + $textSuggestion[$intersection['text']]['freq'];
        }
        $suggestion = array_merge($textSuggestion, $complement);
        $data       = array_values($suggestion);
        usort(
            $data,
            function ($a, $b) {
                return $b['freq'] - $a['freq'];
            }
        );

        return $data;
    }


    private function formatSuggestedData($suggestions, $field)
    {
        $data        = [];
        $pspell_link = pspell_new("en");
        if (isset($suggestions[$field])) {
            foreach ($suggestions[$field] as $sugField) {
                foreach ($sugField['options'] as $suggestion) {

                    if (pspell_check($pspell_link, $suggestion['text'])) {
                        $data[$suggestion['text']] = [
                            'text' => $suggestion['text'],
                            'freq' => (isset($suggestion['freq']) && !empty($suggestion['freq'])) ? $suggestion['freq'] : 1,
                        ];
                    }
                }

            }
        }

        return $data;
    }

    /*
     * Sort the result set manually by year
     *
     * @param $data
     * @param $request
     * @return array
     */

    private function manualSort($data, $request)
    {
        if ($request['sort_by'] == "year") {
            if ($this->getSortOrder($request) === 'desc') {
                usort(
                    $data,
                    function ($a, $b) {
                        return intval($b['year_signed']) - intval($a['year_signed']);
                    }
                );
            } else {
                usort(
                    $data,
                    function ($a, $b) {
                        return intval($a['year_signed']) - intval($b['year_signed']);
                    }
                );
            }
        }

        if ((!isset($request['q']) || empty($request['q'])) && (!isset($request['sort_by']) || empty($request['sort_by']))) {
            usort(
                $data,
                function ($a, $b) {
                    return intval($b['year_signed']) - intval($a['year_signed']);
                }
            );
        }

        return $data;
    }

}
