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
        $params          = [];
        $params['index'] = self::INDEX;
        $params['type']  = "master";
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

        $total                    = $this->totalResult($params);
        $params['body']['fields'] = [
            "metadata.contract_name",
            "metadata.signature_year",
            "metadata.file_size",
            "metadata.country.code"
        ];
        if (isset($request['sortby']) and isset($request['order'])) {
            if ($request['sortby'] == "country") {
                $params['body']['sort']['metadata.country.name']['order'] = $request['order'];
            }
            if ($request['sortby'] == "year") {
                $params['body']['sort']['metadata.signature_year']['order'] = $request['order'];
            }
        }

        $params['body']['highlight'] = [
            'pre_tags'  => [
                '<strong>',
            ],
            'post_tags' => [
                '</strong>',
            ],
            'fields'    => [
                'pdf_text.text'     => [
                    'fragment_size'       => 200,
                    'number_of_fragments' => 1,
                ],
                'annotations.quote' => [
                    'fragment_size'       => 200,
                    'number_of_fragments' => 1,
                ],
                'annotations.text'  => [
                    'fragment_size'       => 200,
                    'number_of_fragments' => 1,
                ],
            ],
        ];

        if (isset($request['per_page'])) {
            $params['body']['size'] = $request['per_page'];
        } else {
            $params['body']['size'] = self::SIZE;
        }


        $params['body']['from'] = isset($request['from']) ? $request['from'] : self::FROM;
        $data                   = [];
        $type                   = isset($request['type']) ? array_map('trim', explode(',', $request['type'])) : [];
        $data                   = $this->searchText($params, $type);
        $data['total']          = $total;
        return $data;
    }


    /**
     * Return the result
     * @param $params
     * @return array
     */
    public function searchText($params, $type)
    {

        $results = $this->search($params);

        $fields          = $results['hits']['hits'];
        $data            = [];
        $data['country'] = [];
        $data['year']    = [];
        $data['result']  = [];
        $i               = 0;

        foreach ($fields as $field) {
            $contractId = $field['_id'];

            $data['result'][$i]['type'] = [];
            $highlight                   = isset($field['highlight']) ? $field['highlight'] : "";
            if (isset($highlight['pdf_text.text']) and in_array('text', $type)) {
                array_push($data['result'][$i]['type'], "Text");

            }
            if ((isset($highlight['annotations.quote']) or isset($highlight['annotations.text'])) and in_array('annotations',
                    $type)
            ) {
                array_push($data['result'][$i]['type'], "Annotation");

            }
            if (!isset($highlight['pdf_text.text']) and !isset($highlight['annotations.quote']) and !isset($highlight['annotations.text']) and in_array('metadata',
                    $type)
            ) {
                array_push($data['result'][$i]['type'], "Metadata");
            }

            if (!empty($data['result'][$i]['type'])) {
                $data['result'][$i]['contract_id']    = $contractId;
                $data['result'][$i]['contract_name']  = isset($field['fields']['metadata.contract_name'][0]) ? $field['fields']['metadata.contract_name'][0] : '';
                $data['result'][$i]['signature_year'] = isset($field['fields']['metadata.signature_year'][0]) ? $field['fields']['metadata.signature_year'][0] : '';
                $data['result'][$i]['file_size']      = isset($field['fields']['metadata.file_size'][0]) ? $field['fields']['metadata.file_size'][0] : '';
                $data['result'][$i]['country']        = strtolower($field['fields']['metadata.country.code'][0]);

                array_push($data['country'], strtolower($field['fields']['metadata.country.code'][0]));
                array_push($data['year'],
                    isset($field['fields']['metadata.signature_year'][0]) ? $field['fields']['metadata.signature_year'][0] : '');
                $highlight                   = isset($field['highlight']) ? $field['highlight'] : "";
                $data['result'][$i]['quote'] = (isset($highlight['annotations.quote']) and in_array('annotations',
                        $type)) ? $highlight['annotations.quote'][0] : "";
                $data['result'][$i]['text']  = (isset($highlight['annotations.text'][0]) and in_array('annotations',
                        $type)) ? $highlight['annotations.text'][0] . '...' : "";

                if (isset($highlight['pdf_text.text']) and in_array('text', $type)) {
                    $data['result'][$i]['text'] = $highlight['pdf_text.text'][0] . '...';
                }

            }
            if (empty($data['result'][$i]['type'])) {
                unset($data['result'][$i], $data);
            }

            $i++;

        }
        $data['country']  = isset($data['country'])?array_unique($data['country']):[];
        $data['year']     = isset($data['year'])?array_unique($data['year']):[];
        $data['per_page'] = isset($data['result'])?count($data['result']):0;
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
