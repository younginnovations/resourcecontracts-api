<?php
namespace App\Services;

class DownloadServices extends Services
{
    /**
     * Gets all the metadata with annotations if annotations category
     * @param       $data
     * @param array $request
     * @return array
     */
    public function getMetadataAndAnnotations($data, $request = [],$lang)
    {

        $ids         = $this->getMetadataId($data);
        $contractIds = array_chunk($ids, 500);
        $data        = [];
        foreach ($contractIds as $contractId) {

            $params['index'] = $this->index;
            $params['type']  = "metadata";
            $params['body']  = [
                'size'  => 500,
                'query' => [
                    "terms" => [
                        "_id" => $contractId
                    ]
                ]
            ];
            $searchResult    = $this->search($params);

            if ($searchResult['hits']['total'] > 0) {

                $results = $searchResult['hits']['hits'];
                $i       = 0;
                foreach ($results as $result) {
                    unset($result['_source']['metadata']['amla_url'], $result['_source']['metadata']['file_size'], $result['_source']['metadata']['word_file']);
                    $tempData = $result['_source'][$lang];
                    if (isset($request['annotation_category']) && !empty($request['annotation_category'])) {
                        $annotations            = $this->getAnnotations($result["_id"], $request['annotation_category']);
                        $tempData['annotation'] = $annotations;
                    }
                    array_push($data, $tempData);
                    $i ++;
                }
            }
        }

        return $data;

    }

    /**
     * Return all the metadata ids
     *
     * @param $data
     * @return array
     */
    public function getMetadataId($data)
    {

        $ids = [];
        foreach ($data['results'] as $result) {
            array_push($ids, $result['id']);
        }

        return $ids;
    }

    /**
     * Return the annotations text and category
     * @param $id
     * @param $category
     * @return array
     */
    private function getAnnotations($id, $category)
    {
        $data            = [];
        $params['index'] = $this->index;
        $params['type']  = "annotations";
        $params['body']  = [
            'query' => [
                "bool" => [
                    "must" => [
                        [
                            "term" => [
                                "contract_id" => $id
                            ]
                        ],
                        [
                            "terms" => [
                                "category.raw" => explode(',', $category)
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $searchResult    = $this->search($params);
        foreach ($searchResult['hits']['hits'] as $result) {
            $temp['annotation_category'] = $result['_source']['category'];
            $temp['text']                = $result['_source']['text'];
            $data[]                      = $temp;
        }

        return $data;

    }


    /**
     * Download the searchresult as csv
     * @param $downloadData
     */
    public function downloadSearchResult($downloadData)
    {
        $downloadData = json_decode(json_encode($downloadData), false);

        $data = $this->formatCSVData($downloadData);

        return $data;
    }

    /**
     * Format all the contracts data
     * @param $contracts
     * @return array
     */
    private function formatCSVData($contracts)
    {
        $data = [];

        foreach ($contracts as $contract) {
            if (isset($contract->annotation)) {
                foreach ($contract->annotation as $annotations) {
                    $data[] = $this->getCSVData($contract, $annotations);


                }
            } else {
                $data[] = $this->getCSVData($contract);
            }

        }

        return $data;
    }

    /**
     * Make the array semicolon separated for multiple data
     * @param $arrays
     * @param $key
     * @return array
     */
    private function makeSemicolonSeparated($arrays, $key)
    {
        $data = [];
        if ($arrays == null) {
            return $data;
        }

        foreach ($arrays as $array) {

            if (is_array($array) && array_key_exists($array, $key)) {
                array_push($data, $array[$key]);
            }
            if (is_object($array) && property_exists($array, $key)) {
                array_push($data, $array->$key);
            }
        }


        return $data;
    }

    /**
     * Return the operator
     * @param $company
     * @return array
     */
    private function getOperator($company)
    {
        $data     = [];
        $operator = $this->operator();
        foreach ($company as $companyData) {
            if (isset($companyData->operator) && $companyData->operator) {
                array_push($data, $operator[$companyData->operator]);
            }

        }

        return $data;
    }

    public function operator()
    {
        return [
            - 1 => "Not Available",
            0   => "No",
            1   => "Yes",
        ];
    }

    /**
     * Return the format of csv
     * @param       $contract
     * @param array $annotations
     * @return array
     */
    private function getCSVData($contract, $annotations = [])
    {
        return [


            'OCID'                          => $contract->open_contracting_id,
            'Category'                      => $contract->category[0],
            'Contract Name'                 => $contract->contract_name,
            'Contract Identifier'           => $contract->contract_identifier,
            'Language'                      => $contract->language,
            'Country Name'                  => $contract->country->name,
            'Resource'                      => implode(';', $contract->resource),
            'Contract Type'                 => implode(';', $contract->type_of_contract),
            'Signature Date'                => $contract->signature_date,
            'Document Type'                 => $contract->document_type,
            'Government Entity'             => implode(';', $this->makeSemicolonSeparated($contract->government_entity, 'entity')),
            'Government Identifier'         => implode(';', $this->makeSemicolonSeparated($contract->government_entity, 'identifier')),
            'Company Name'                  => implode(';', $this->makeSemicolonSeparated($contract->company, 'name')),
            'Company Address'               => implode(';', $this->makeSemicolonSeparated($contract->company, 'company_address')),
            'Jurisdiction of Incorporation' => implode(';', $this->makeSemicolonSeparated($contract->company, 'jurisdiction_of_incorporation')),
            'Registration Agency'           => implode(';', $this->makeSemicolonSeparated($contract->company, 'registration_agency')),
            'Company Number'                => implode(';', $this->makeSemicolonSeparated($contract->company, 'company_number')),
            'Corporate Grouping'            => implode(';', $this->makeSemicolonSeparated($contract->company, 'parent_company')),
            'Participation Share'           => implode(';', $this->makeSemicolonSeparated($contract->company, 'participation_share')),
            'Open Corporates Link'          => implode(';', $this->makeSemicolonSeparated($contract->company, 'open_corporate_id')),
            'Incorporation Date'            => implode(';', $this->makeSemicolonSeparated($contract->company, 'company_founding_date')),
            'Operator'                      => implode(';', $this->getOperator($contract->company, 'operator')),
            'Project Title'                 => implode(';', $this->makeSemicolonSeparated($contract->concession, 'license_name')),
            'Project Identifier'            => implode(';', $this->makeSemicolonSeparated($contract->concession, 'license_identifier')),
            'License Name'                  => $contract->project_title,
            'License Identifier'            => $contract->project_identifier,
            'Source Url'                    => $contract->source_url,
            'Disclosure Mode'               => $contract->disclosure_mode,
            'Retrieval Date'                => $contract->date_retrieval,
            'Pdf Url'                       => $contract->file_url,
            'Deal Number'                   => $contract->deal_number,
            'Contract Note'                 => $contract->contract_note,
            'Matrix Page'                   => $contract->matrix_page,
            'Annotation Category'           => isset($annotations->annotation_category) ? $annotations->annotation_category : '',
            'Annotation Text'               => isset($annotations->text) ? $annotations->text : ''
        ];
    }

    /**
     * Annotations Download
     * @param $annotations
     * @return array
     */
    public function downloadAnnotations($annotations, $metadata)
    {
        $data = $this->getAnnotationsData($annotations);

        return $data;

    }

    /**
     * Return the formatted annotations data
     * @param $annotations
     * @return array
     */
    private function getAnnotationsData($annotations)
    {
        $data = [];

        foreach ($annotations['result'] as $annotation) {

            $data[] = [
                'Category'          => $annotation['category'],
                'Topic'             => $annotation['cluster'],
                'Annotation Text'   => $annotation['text'],
                'PDF Page Number'   => $annotation['page_no'],
                'Article Reference' => $annotation['article_reference']

            ];

        }

        return $data;
    }

    private function getPageNumber($pageNumbers)
    {
        $pages = [];
        foreach ($pageNumbers as $key => $pageNumber) {
            $pages[$key] = implode(',', $pageNumber);
        }
        print_r($pages);
    }

}
