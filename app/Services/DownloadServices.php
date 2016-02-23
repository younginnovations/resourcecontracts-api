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
    public function getMetadataAndAnnotations($data, $request = [])
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
                    $tempData = $result['_source']['metadata'];
                    if (isset($request['annotation_category']) && !empty($request['annotation_category'])) {
                        $annotations          = $this->getAnnotations($result["_id"], $request['annotation_category']);
                        $tempData['annotation'] = $annotations;
                    }
                    array_push($data,$tempData);
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

        $filename = "export" . date('Y-m-d');
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $file    = fopen('php://output', 'w');
        $heading = [
            'OCID',
            'Category',
            'Contract Name',
            'Contract Identifier',
            'Language',
            'Country Name',
            'Resource',
            'Contract Type',
            'Signature Date',
            'Document Type',
            'Government Entity',
            'Government Identifier',
            'Company Name',
            'Company Address',
            'Jurisdiction of Incorporation',
            'Registration Agency',
            'Company Number',
            'Corporate Grouping',
            'Participation Share',
            'Open Corporates Link',
            'Incorporation Date',
            'Operator',
            'Project Title',
            'Project Identifier',
            'License Name',
            'License Identifier',
            'Source Url',
            'Disclosure Mode',
            'Retrieval Date',
            'Pdf Url',
            'Deal Number',
            'Contract Note',
            'Matrix Page'
        ];
        if (isset($downloadData[0]->annotation)) {
            array_push($heading, 'Annotation Category');
            array_push($heading, 'Annotation Text');
        }
        fputcsv($file, $heading);

        foreach ($data as $row) {
            fputcsv($file, $row);
        }
        fclose($file);
        die();
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
            $contract->open_contracting_id,
            $contract->category[0],
            $contract->contract_name,
            $contract->contract_identifier,
            $contract->language,
            $contract->country->name,
            implode(';', $contract->resource),
            implode(';', $contract->type_of_contract),
            $contract->signature_date,
            $contract->document_type,
            implode(';', $this->makeSemicolonSeparated($contract->government_entity, 'entity')),
            implode(';', $this->makeSemicolonSeparated($contract->government_entity, 'identifier')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'name')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'company_address')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'jurisdiction_of_incorporation')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'registration_agency')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'company_number')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'parent_company')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'participation_share')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'open_corporate_id')),
            implode(';', $this->makeSemicolonSeparated($contract->company, 'company_founding_date')),
            implode(';', $this->getOperator($contract->company, 'operator')),
            implode(';', $this->makeSemicolonSeparated($contract->concession, 'license_name')),
            implode(';', $this->makeSemicolonSeparated($contract->concession, 'license_identifier')),
            $contract->project_title,
            $contract->project_identifier,
            $contract->source_url,
            $contract->disclosure_mode,
            $contract->date_retrieval,
            $contract->file_url,
            $contract->deal_number,
            $contract->contract_note,
            $contract->matrix_page,
            isset($annotations->annotation_category) ? $annotations->annotation_category : '',
            isset($annotations->text) ? $annotations->text : ''
        ];
    }

    /**
     * Annotations Download
     * @param $annotations
     */
    public function downloadAnnotations($annotations, $metadata)
    {

        $data     = $this->getAnnotationsData($annotations);
        $filename = $metadata['name'] . date('Y-m-d');
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        $file    = fopen('php://output', 'w');
        $heading = [
            'Category',
            'Topic',
            'Annotation Text',
            'PDF Page Number',

        ];
        fputcsv($file, $heading);

        foreach ($data as $row) {
            fputcsv($file, $row);
        }

        fclose($file);
        exit;
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
                $annotation['category'],
                $annotation['cluster'],
                $annotation['text'],
                $annotation['page_no'],

            ];
        }

        return $data;
    }

}
