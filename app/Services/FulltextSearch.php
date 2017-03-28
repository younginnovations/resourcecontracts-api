<?php namespace App\Services;

use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Exception;


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
    const ORDER = "asc";
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
            $filters[] = ["terms" => [$lang.".signature_year" => $year]];
        }
        if (isset($request['country_code']) and !empty($request['country_code'])) {
            $country   = explode('|', $request['country_code']);
            $filters[] = ["terms" => [$lang.".country_code" => $country]];
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $resource  = explode('|', $request['resource']);
            $filters[] = ["terms" => [$lang.".resource_raw" => $resource]];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filters[] = ["term" => [$lang.".category" => $request['category']]];
        }
        if (isset($request['contract_type']) and !empty($request['contract_type'])) {
            $contractType = explode('|', $request['contract_type']);
            $filters[]    = ["terms" => [$lang.".contract_type" => $contractType]];
        }
        if (isset($request['document_type']) and !empty($request['document_type'])) {
            $contractType = explode('|', $request['document_type']);
            $filters[]    = ["terms" => [$lang.".document_type.raw" => $contractType]];
        }
        if (isset($request['language']) and !empty($request['language'])) {
            $contractType = explode('|', $request['language']);
            $filters[]    = ["terms" => [$lang.".language" => $contractType]];
        }
        if (isset($request['company_name']) and !empty($request['company_name'])) {
            $companyName = explode('|', $request['company_name']);
            $filters[]   = ["terms" => [$lang.".company_name" => $companyName]];
        }
        if (isset($request['corporate_group']) and !empty($request['corporate_group'])) {
            $corporateGroup = explode('|', $request['corporate_group']);
            $filters[]      = ["terms" => [$lang.".corporate_grouping" => $corporateGroup]];
        }
        if (isset($request['annotation_category']) and !empty($request['annotation_category'])) {
            $annotationsCategory = explode('|', $request['annotation_category']);
            $filters[]           = ["terms" => ["annotations_category" => $annotationsCategory]];
        }
        if (isset($request['annotated']) and !empty($request['annotated']) and $request['annotated'] == 1) {
            $filters[] = [
                "bool" => [
                    "must_not" => [
                        "missing" => [
                            "field"     => "annotations_string.".$lang,
                            "existence" => true,
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
                $params['body']['query']['simple_query_string'] = [
                    "fields"           => $fields,
                    'query'            => urldecode($queryString),
                    "default_operator" => "AND",
                ];
            } else {
                $params['body']['query']['query_string'] = [
                    "fields"              => $fields,
                    'query'               => $this->addFuzzyOperator($request['q']),
                    "default_operator"    => "AND",
                    "fuzzy_prefix_length" => 4,
                ];
            }
        }

        if (!empty($filters)) {
            $params['body']['filter'] = [
                "and" => [
                    "filters" => $filters,
                ],
            ];
        }

        $params['body']['fields'] = [
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
                $params['body']['sort'][$lang.'.country_name']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort'][$lang.'.signature_year']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort'][$lang.'.contract_name.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort'][$lang.'.resource_raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort'][$lang.'.contract_type']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
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
        $from    = (isset($request['from']) && !empty($request['from'])) ? (integer) $request['from'] : self::FROM;
        $from    = ($from < 9900) ? $from : 9900;

        $params['body']['size'] = $perPage;
        $params['body']['from'] = $from;


        if ((isset($request['download']) && $request['download']) || (isset($request['all']) && $request['all'])) {
            $params['body']['size'] = $this->countAll();
            $params['body']['from'] = 0;
        }

        $data         = $this->searchText($params, $type, $queryString, $lang);
        $data['from'] = isset($request['from']) ? $request['from'] : self::FROM;

        $data['per_page'] = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        if (isset($request['download']) && $request['download']) {
            $download     = new DownloadServices();
            $downloadData = $download->getMetadataAndAnnotations($data, $request, $lang);

            return $download->downloadSearchResult($downloadData);
        }


        return (array) $data;
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
            if (isset($field['fields'][$lang.'.country_code'])) {
                array_push($data['country'], $field['fields'][$lang.'.country_code'][0]);
            }
            if (isset($field['fields'][$lang.'.signature_year'])) {
                array_push($data['year'], (int) $field['fields'][$lang.'.signature_year'][0]);
            }
            if (isset($field['fields'][$lang.'.contract_type'])) {
                array_push($data['contract_type'], $field['fields'][$lang.'.contract_type'][0]);
            }
            if (isset($field['fields'][$lang.'.resource'])) {
                $data['resource'] = array_merge($data['resource'], $field['fields'][$lang.'.resource']);
            }
            if (isset($field['fields'][$lang.'.company_name'])) {
                $data['company_name'] = array_merge($data['company_name'], $field['fields'][$lang.'.company_name']);
            }
            if (isset($field['fields'][$lang.'.corporate_grouping'])) {
                $data['corporate_group'] = array_merge(
                    $data['corporate_group'],
                    $field['fields'][$lang.'.corporate_grouping']
                );
            }

            $data['results'][$i]          = [
                "id"                  => (int) $contractId,
                "open_contracting_id" => isset($field['fields'][$lang.'.open_contracting_id']) ? $field['fields'][$lang.'.open_contracting_id'][0] : "",
                "name"                => isset($field['fields'][$lang.'.contract_name']) ? $field['fields'][$lang.'.contract_name'][0] : "",
                "year_signed"         => isset($field['fields'][$lang.'.signature_year']) ? $this->getSignatureYear(
                    $field['fields'][$lang.'.signature_year'][0]
                ) : "",
                "contract_type"       => isset($field['fields'][$lang.'.contract_type']) ? $field['fields'][$lang.'.contract_type'] : [],
                "resource"            => isset($field['fields'][$lang.'.resource']) ? $field['fields'][$lang.'.resource'] : [],
                'country_code'        => isset($field['fields'][$lang.'.country_code']) ? $field['fields'][$lang.'.country_code'][0] : "",
                "language"            => isset($field['fields'][$lang.'.language']) ? $field['fields'][$lang.'.language'][0] : "",
                "category"            => isset($field['fields'][$lang.'.category']) ? $field['fields'][$lang.'.category'] : [],
                "is_ocr_reviewed"     => isset($field['fields'][$lang.'.show_pdf_text']) ? $this->getBoolean(
                    (int) $field['fields'][$lang.'.show_pdf_text'][0]
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
     * Return search count
     * @return mixed
     */
    public function countAll()
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "master";
        $params['body']  = [
            "query" => [
                "match_all" => [],
            ],
        ];
        $count           = $this->countResult($params);

        return $count['count'];
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


}
