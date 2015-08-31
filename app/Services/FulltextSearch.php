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

    const INDEX = "nrgi";
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
        $params['index'] = self::INDEX;
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
            $filters[] = ["terms" => ["metadata.resource" => $resource]];
        }
        if (isset($request['category']) and !empty($request['category'])) {
            $filters[] = ["term" => ["metadata.category" => $request['category']]];
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
        if (isset($request['q'])) {
            $params['body']['query']['query_string'] = [
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
            "metadata.file_size",
            "metadata.country_code",
            "metadata.country_name",
            "metadata.resource",
            "metadata.language",
            "metadata.file_size"
        ];
        if (isset($request['sort_by']) and !empty($request['sort_by'])) {
            if ($request['sort_by'] == "country") {
                $params['body']['sort']['metadata.country_name']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
            if ($request['sort_by'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = (isset($request['order']) and !empty($request['order'])) ? $request['order'] : self::ORDER;
            }
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
                'number_of_fragments' => 1,
            ];

        }

        if (in_array('annotations', $type)) {
            $highlightField['annotations_string'] = [
                'fragment_size'       => 1,
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
        $params['body']['size']      = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        $params['body']['from']      = (isset($request['from']) and !empty($request['from'])) ? $request['from'] : self::FROM;
        $data                        = [];
        $data                        = $this->searchText($params, $type);
        $data['from']                = isset($request['from']) ? $request['from'] : self::FROM;

        return (array) $data;
    }


    /**
     * Return the result
     * @param $params
     * @return array
     */
    public function searchText($params, $type)
    {

        $results          = $this->search($params);
        $fields           = $results['hits']['hits'];
        $data             = [];
        $data['total']    = $results['hits']['total'];
        $data['country']  = [];
        $data['year']     = [];
        $data['resource'] = [];
        $data['results']  = [];
        $i                = 0;

        foreach ($fields as $field) {
            $contractId = $field['_id'];
            array_push($data['country'], $field['fields']['metadata.country_code'][0]);
            array_push($data['year'], $field['fields']['metadata.signature_year'][0]);
            if (isset($field['fields']['metadata.resource'])) {
                $data['resource'] = array_merge($data['resource'], $field['fields']['metadata.resource']);
            }

            $data['results'][$i]                = [
                "contract_id"    => $contractId,
                "contract_name"  => $field['fields']['metadata.contract_name'][0],
                "signature_year" => $field['fields']['metadata.signature_year'][0],
                'country'        => $field['fields']['metadata.country_code'][0],
                "file_size"      => $field['fields']['metadata.file_size'][0],
                "language"       => $field['fields']['metadata.language'][0],
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

        $data['country']  = (isset($data['country']) && !empty($data['country'])) ? array_unique($data['country']) : [];
        $data['year']     = (isset($data['year']) && !empty($data['year'])) ? array_filter(array_unique($data['year'])) : [];
        $data['resource'] = (isset($data['resource']) && !empty($data['resource'])) ? array_filter(array_unique($data['resource'])) : [];
        $data['per_page'] = (isset($request['per_page']) and !empty($request['per_page'])) ? $request['per_page'] : self::SIZE;
        asort($data['country']);
        asort($data['year']);
        asort($data['resource']);

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
