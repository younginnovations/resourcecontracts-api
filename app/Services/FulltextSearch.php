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
    const FROM = 0;
    const SIZE = 1000;

    /**
     * Full text search
     * @param $request
     * @return array
     */
    public function FullTextSearch($request)
    {
        $metadata         = $this->searchInMaster($request);
        $metadata['from'] = isset($request['from']) ? $request['from'] : self::FROM;
        return $metadata;
    }

    /**
     * Format the queries and return the search result
     * @param $request
     * @return array
     */
    public function searchInMaster($request)
    {
        $type = isset($request['type']) ? array_map('trim', explode(',', $request['type'])) : [];

        $params          = [];
        $params['index'] = self::INDEX;
        $params['type']  = "master";
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

        $total                    = $this->totalResult($params);
        $params['body']['fields'] = [
            "metadata.contract_name",
            "metadata.signature_year",
            "metadata.file_size",
            "metadata.country_code",
            "metadata.country_name",
            "metadata.language",
            "metadata.file_size"
        ];
        if (isset($request['sortby']) and isset($request['order'])) {
            if ($request['sortby'] == "country") {
                $params['body']['sort']['metadata.country_name']['order'] = $request['order'];
            }
            if ($request['sortby'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = $request['order'];
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

            $params['body']['highlight'] = [
                'pre_tags'  => [
                    '<strong>',
                ],
                'post_tags' => [
                    '</strong>',
                ],
                'fields'    => $highlightField,
            ];


        if (isset($request['per_page'])) {
            $params['body']['size'] = $request['per_page'];
        } else {
            $params['body']['size'] = self::SIZE;
        }


        $params['body']['from'] = isset($request['from']) ? $request['from'] : self::FROM;
        $data                   = [];

        $data          = $this->searchText($params, $type);
        $data['total'] = $total;
        return $data;
    }


    /**
     * Return the result
     * @param $params
     * @return array
     */
    public function searchText($params, $type)
    {

        $results         = $this->search($params);
        $fields          = $results['hits']['hits'];
        $data            = [];
        $data['country'] = [];
        $data['year']    = [];
        $data['result']  = [];
        $i               = 0;

        foreach ($fields as $field) {

            $contractId = $field['_id'];
            array_push($data['country'], $field['fields']['metadata.country_code'][0]);
            array_push($data['year'], $field['fields']['metadata.signature_year'][0]);
            $data['result'][$i]                = [
                "contract_id"    => $contractId,
                "contract_name"  => $field['fields']['metadata.contract_name'][0],
                "signature_year" => $field['fields']['metadata.signature_year'][0],
                'country'        => $field['fields']['metadata.country_code'][0],
                "file_size"      => $field['fields']['metadata.file_size'][0],
                "language"       => $field['fields']['metadata.language'][0],
            ];
            $data['result'][$i]['type']        = [];
            $highlight                         = $field['highlight'];
            $data['result'][$i]['text']        = isset($highlight['pdf_text_string'][0]) ? $highlight['pdf_text_string'][0] : '';
            $data['result'][$i]['annotations'] = isset($highlight['annotations_string'][0]) ? $highlight['annotations_string'][0] : '';
            if (isset($highlight['pdf_text_string']) and in_array('text', $type)) {
                array_push($data['result'][$i]['type'], "Text");
            }
            if (isset($highlight['metadata_string']) and in_array('metadata', $type)) {
                array_push($data['result'][$i]['type'], "Metadata");
            }
            if (isset($highlight['annotations_string']) and in_array('annotations', $type)) {
                array_push($data['result'][$i]['type'], "Annotation");
            }

            $i++;
        }

        $data['country']  = isset($data['country']) ? array_unique($data['country']) : [];
        $data['year']     = isset($data['year']) ? array_unique($data['year']) : [];
        $data['per_page'] = isset($data['result']) ? count($data['result']) : 0;
        return $data;
    }

    /**
     * Get the total count of search Result
     * @param $params
     * @return integer
     */
    public function totalResult($params)
    {
        $params['search_type'] = "count";
        $count                 = $this->search($params);

        return $count['hits']['total'];
    }
}
