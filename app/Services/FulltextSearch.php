<?php namespace App\Services;


/**
 * Class FulltextSearch
 * @package App\Services
 */
class FulltextSearch extends Services
{
    /**
     * @var APIRepositoryInterface
     */
    private $api;

    /**
     * @param APIRepositoryInterface $api
     */


    const FROM  = 0;
    const SIZE  = 25;
    const ORDER = "asc";


    /**
     * Format the queries and return the search result
     * @param $request
     * @return array
     */
    public function searchInMaster($request)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "master";
        $type            = isset($request['group']) ? array_map('trim', explode(',', $request['group'])) : [];
        $typecheck       = $this->typeCheck($type);
        if (!$typecheck) {
            return [];
        }
        if (isset($request['year']) and !empty($request['year'])) {
            $year      = explode(',', $request['year']);
            $filters[] = ["terms" => ["metadata.signature_year" => $year]];
        }
        if (isset($request['country']) and !empty($request['country'])) {
            $country   = explode(',', $request['country']);
            $filters[] = ["terms" => ["metadata.country_code" => $country]];
        }
        if (isset($request['resource']) and !empty($request['resource'])) {
            $resource  = explode(',', $request['resource']);
            $filters[] = ["terms" => ["metadata.resource_raw" => $resource]];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filters[] = ["term" => ["metadata.category" => $request['category']]];
        }
        if (isset($request['contract_type']) and !empty($request['contract_type'])) {
            $contractType = explode(',', $request['contract_type']);
            $filters[]    = ["terms" => ["metadata.contract_type" => $contractType]];
        }
        if (isset($request['company_name']) and !empty($request['company_name'])) {
            $companyName = explode(',', $request['company_name']);
            $filters[]   = ["terms" => ["metadata.company_name" => $companyName]];
        }
        if (isset($request['corporate_group']) and !empty($request['corporate_group'])) {

            $corporateGroup = explode(',', $request['corporate_group']);
            $filters[]      = ["terms" => ["metadata.corporate_grouping" => $corporateGroup]];
        }
        if (isset($request['annotation_category']) and !empty($request['annotation_category'])) {

            $annotationsCategory = explode(',', $request['annotation_category']);
            $filters[]           = ["terms" => ["annotations_category" => $annotationsCategory]];
        }


        $fields = [];
        if (in_array("metadata", $type)) {
            array_push($fields, "metadata_string");
        }
        if (in_array("text", $type)) {
            array_push($fields, "pdf_text_string");
        }
        if (in_array("annotations", $type)) {
            array_push($fields, "annotations_string");
        }
        if (isset($request['q']) && !empty($request['q'])) {
            $params['body']['query']['simple_query_string'] = [
                "fields" => $fields,
                'query'  => $request['q']
            ];
        }

        if (!empty($filters)) {
            $params['body']['filter'] = [
                "and" => [
                    "filters" => $filters
                ]
            ];
        }

        $params['body']['fields'] = [
            "metadata.contract_name",
            "metadata.signature_year",
            "metadata.open_contracting_id",
            "metadata.signature_date",
            "metadata.file_size",
            "metadata.country_code",
            "metadata.country_name",
            "metadata.resource",
            "metadata.language",
            "metadata.file_size",
            "metadata.company_name",
            "metadata.contract_type",
            "metadata.corporate_grouping",
        ];
        if (isset($request['sort_by']) and !empty($request['sort_by'])) {
            if ($request['sort_by'] == "country") {
                $params['body']['sort']['metadata.country_name']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_name") {
                $params['body']['sort']['metadata.contract_name.raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "resource") {
                $params['body']['sort']['metadata.resource_raw']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "contract_type") {
                $params['body']['sort']['metadata.contract_type']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }

        } else {
            $params['body']['sort']['metadata.signature_year']['order'] = "desc";
        }

        $highlightField = [];
        if (in_array('metadata', $type)) {
            $highlightField['metadata_string'] = [
                'fragment_size'       => 200,
                'number_of_fragments' => 1,
            ];

        }

        if (in_array('text', $type)) {
            $highlightField['pdf_text_string'] = [
                'fragment_size'       => 200,
                'number_of_fragments' => 50,
            ];

        }

        if (in_array('annotations', $type)) {
            $highlightField['annotations_string'] = [
                'fragment_size'       => 50,
                'number_of_fragments' => 1,
            ];

        }
        if (in_array('metadata', $type)) {
            $highlightField['metadata_string'] = [
                'fragment_size'       => 1,
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

        $params['body']['size'] = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        if (isset($request['download']) && $request['download']) {
            $params['body']['size'] = 100000;
        }
        $params['body']['from'] = (isset($request['from']) and !empty($request['from'])) ? $request['from'] : self::FROM;
        $data                   = [];
        $data                   = $this->searchText($params, $type);
        $data['from']           = isset($request['from']) ? $request['from'] : self::FROM;
        $data['per_page']       = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        if (isset($request['download']) && $request['download']) {
            $download = new DownloadServices();
            $downloadData=$download->getMetadataAndAnnotations($data, $request);
            return $download->downloadSearchResult($downloadData);
        }

        return (array) $data;
    }


    /**
     * Return the result
     * @param $params
     * @return array
     */
    public function searchText($params, $type)
    {

        $results = $this->search($params);

        $fields                  = $results['hits']['hits'];
        $data                    = [];
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
            if (isset($field['fields']['metadata.country_code'])) {
                array_push($data['country'], $field['fields']['metadata.country_code'][0]);
            }
            if (isset($field['fields']['metadata.signature_year'])) {
                array_push($data['year'], (int) $field['fields']['metadata.signature_year'][0]);
            }
            if (isset($field['fields']['metadata.contract_type'])) {
                array_push($data['contract_type'], $field['fields']['metadata.contract_type'][0]);
            }
            if (isset($field['fields']['metadata.resource'])) {
                $data['resource'] = array_merge($data['resource'], $field['fields']['metadata.resource']);
            }
            if (isset($field['fields']['metadata.company_name'])) {
                $data['company_name'] = array_merge($data['company_name'], $field['fields']['metadata.company_name']);
            }
            if (isset($field['fields']['metadata.corporate_grouping'])) {
                $data['corporate_group'] = array_merge($data['corporate_group'], $field['fields']['metadata.corporate_grouping']);
            }

            $data['results'][$i]                = [
                "contract_id"         => (int) $contractId,
                "open_contracting_id" => isset($field['fields']['metadata.open_contracting_id']) ? $field['fields']['metadata.open_contracting_id'][0] : "",
                'guid'                => $contractId . '-' . $field['fields']['metadata.open_contracting_id'][0],
                "contract_name"       => isset($field['fields']['metadata.contract_name']) ? $field['fields']['metadata.contract_name'][0] : "",
                "signature_year"      => isset($field['fields']['metadata.signature_year']) ? $field['fields']['metadata.signature_year'][0] : "",
                "contract_type"       => isset($field['fields']['metadata.contract_type']) ? $field['fields']['metadata.contract_type'][0] : "",
                "resource"            => isset($field['fields']['metadata.resource']) ? $field['fields']['metadata.resource'] : [],
                'country_code'        => isset($field['fields']['metadata.country_code']) ? $field['fields']['metadata.country_code'][0] : "",
                "file_size"           => isset($field['fields']['metadata.file_size']) ? $field['fields']['metadata.file_size'][0] : "",
                "language"            => isset($field['fields']['metadata.language']) ? $field['fields']['metadata.language'][0] : "",
            ];
            $data['results'][$i]['group']       = [];
            $highlight                          = isset($field['highlight']) ? $field['highlight'] : '';
            $data['results'][$i]['text']        = isset($highlight['pdf_text_string'][0]) ? $highlight['pdf_text_string'][0] : '';
            $data['results'][$i]['annotations'] = isset($highlight['annotations_string'][0]) ? $highlight['annotations_string'][0] : '';
            $data['results'][$i]['metadata']    = isset($highlight['metadata_string'][0]) ? $highlight['metadata_string'][0] : '';
            if (isset($highlight['pdf_text_string']) and in_array('text', $type)) {
                array_push($data['results'][$i]['group'], "Text");
            }
            if (isset($highlight['metadata_string']) and in_array('metadata', $type)) {
                array_push($data['results'][$i]['group'], "Metadata");
            }
            if (isset($highlight['annotations_string']) and in_array('annotations', $type)) {
                array_push($data['results'][$i]['group'], "Annotation");
            }

            $i ++;
        }
        $data['country']         = (isset($data['country']) && !empty($data['country'])) ? array_unique($data['country']) : [];
        $data['year']            = (isset($data['year']) && !empty($data['year'])) ? array_filter(array_unique($data['year'])) : [];
        $data['resource']        = (isset($data['resource']) && !empty($data['resource'])) ? array_filter(array_unique($data['resource'])) : [];
        $data['contract_type']   = (isset($data['contract_type']) && !empty($data['contract_type'])) ? array_filter(array_unique($data['contract_type'])) : [];
        $data['company_name']    = (isset($data['company_name']) && !empty($data['company_name'])) ? array_filter(array_unique($data['company_name'])) : [];
        $data['corporate_group'] = (isset($data['corporate_group']) && !empty($data['corporate_group'])) ? array_filter(array_unique($data['corporate_group'])) : [];
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
     * @param $type
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


}
