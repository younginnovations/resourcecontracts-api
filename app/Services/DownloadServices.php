<?php
namespace App\Services;

use Symfony\Component\HttpFoundation\Request;

/**
 * Class DownloadServices
 * @package App\Services
 */
class DownloadServices extends Services
{
    /**
     * Gets all the metadata with annotations if annotations category
     *
     * @param       $data
     * @param array $request
     * @param       $lang
     *
     * @return array
     */
    public function getMetadataAndAnnotations($data, $request = [], $lang)
    {
        $ids         = $this->getMetadataId($data);
        $contractIds = array_chunk($ids, 500);
        $data        = [];
        foreach ($contractIds as $contractId) {
            $params['index'] = $this->getMetadataIndex();
            $params['body']  = [
                'size'  => 500,
                'track_total_hits' => true,
                'query' => [
                    "terms" => [
                        "_id" => $contractId,
                    ],
                ],
            ];
            $searchResult    = $this->search($params);

            if (getHitsTotal($searchResult['hits']['total']) > 0) {
                $results = $searchResult['hits']['hits'];
                $i       = 0;
                foreach ($results as $result) {
                    unset($result['_source']['metadata']['amla_url'], $result['_source']['metadata']['file_size'], $result['_source']['metadata']['word_file']);
                    $tempData = $result['_source'][$lang];
                    $tempData['id']=$result['_id'];
                  if (isset($request['annotation_category']) && !empty($request['annotation_category'])) {
                        $annotations            = $this->getAnnotations(
                            $result["_id"],
                            $request['annotation_category']
                        );
                        $tempData['annotation'] = $annotations;
                    }
                    array_push($data, $tempData);
                    $i++;
                }
            }
        }
        return $this->makeDocumentsGrouping($ids,$data);
    }

    public function makeDocumentsGrouping($ids,$datas)
    {
       $arrangedDatas=[];
       foreach($datas as $data){
         $index=array_search($data['id'],$ids);
         $arrangedDatas[$index]=$data;
        
       }
         ksort($arrangedDatas);
     return $arrangedDatas;

    }
    
    /**
     * Return all the metadata ids
     *
     * @param $data
     *
     * @return array
     */
    public function getMetadataId($data)
    {
        $ids = [];
        foreach ($data['results'] as $result) {
            array_push($ids, $result['id']);
            if(!empty($result['supporting_contracts']))
            {
                foreach($result['supporting_contracts'] as $child)
                {
                    array_push($ids,$child['id']);
                }
            }
        }

        return $ids;
    }

    /**
     * Download the Search Result as CSV
     *
     * @param $downloadData
     * @param $category
     *
     * @return array
     */
    public function downloadSearchResult($downloadData,$request)
    {
        return $this->formatCSVData(json_decode(json_encode($downloadData), false),$request);
    }

    /**
     * Annotations Download
     *
     * @param $annotations
     *
     * @return array
     */
    public function downloadAnnotations($annotations)
    {
        $data = $this->getAnnotationsData($annotations);

        return $data;
    }

    /**
     * Return the annotations text and category
     *
     * @param $id
     * @param $category
     *
     * @return array
     */
    private function getAnnotations($id, $category)
    {
        $data            = [];
        $params['index'] = $this->getAnnotationsIndex();
        $params['body']  = [
            'query' => [
                "bool" => [
                    "must" => [
                        [
                            "term" => [
                                "contract_id" => $id,
                            ],
                        ],
                        [
                            "terms" => [
                                "category.keyword" => explode('|', $category),
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $searchResult    = $this->search($params);
        $request         = Request::createFromGlobals();
        $lang            = $this->getLang($request->query->get('lang'));

        foreach ($searchResult['hits']['hits'] as $result) {
            $temp['annotation_category'] = $result['_source']['category'];
            $temp['text']                = $result['_source']['annotation_text'][$lang];
            $temp['article_reference']   =  $result['_source']['article_reference'][$lang];
            $data[]                      = $temp;
        }

        return $data;
    }

    /**
     * Format all the contracts data
     *
     * @param $contracts
     *
     * @return array
     */
    private function formatCSVData($contracts,$request)
    {
        $data = [];

        foreach($contracts as $contract) {

            if (isset($contract->annotation)) {

                foreach ($contract->annotation as $annotations) {
                    if($request['category']=='olc'){
                    $data[] = $this->getCSVData($contract,$request, $annotations);
                    }

        
                }
                    if($request['category']=='rc' && !empty($contract->annotation))
                    {
                         $data[] = $this->getCSVData($contract,$request);

                    }
                
            } else {
                $data[] = $this->getCSVData($contract,$request);
            }
        }

        return $data;
    }

    /**
     * Make the array semicolon separated for multiple data
     *
     * @param $arrays
     * @param $key
     *
     * @return array
     */
    private function makeSemicolonSeparated($arrays, $key)
    {
        $data = [];
        if ($arrays == null) {
            return $data;
        }

        foreach ($arrays as $array) {
            if (is_array($array) && array_key_exists($array, $key) && $array[$key]!="") {
                array_push($data, $array[$key]);
            }
            if (is_object($array) && property_exists($array, $key) &&$array->$key!="") {
                array_push($data, $array->$key);
            }
        }

        return $data;
    }

    /**
     * Return the operator
     *
     * @param $company
     *
     * @return array
     */
    private function getOperator($company)
    {
        $operator = [
            -1 => "Not Available",
            0  => "No",
            1  => "Yes",
        ];

        $data = [];
        foreach ($company as $companyData) {
            if (isset($companyData->operator) && $companyData->operator) {
                $operator = isset($operator[$companyData->operator])
                    ?
                    $operator[$companyData->operator]
                    :
                    $companyData->operator;

                array_push($data, $operator);
            }

        }

        return $data;
    }

    /**
     * Return the format of csv
     *
     * @param       $contract
     * @param array $annotations
     *
     * @return array
     */
    private function getCSVData($contract,$request, $annotations = [])
    {
        if($request['category']=='olc'){
      $data= [
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
            'Government Entity'             => implode(
                ';',
                $this->makeSemicolonSeparated($contract->government_entity, 'entity')
            ),
            'Government Identifier'         => implode(
                ';',
                $this->makeSemicolonSeparated($contract->government_entity, 'identifier')
            ),
            'Company Name'                  => implode(';', $this->makeSemicolonSeparated($contract->company, 'name')),
            'Company Address'               => implode(
                ';',
                $this->makeSemicolonSeparated($contract->company, 'company_address')
            ),
            'Jurisdiction of Incorporation' => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'jurisdiction_of_incorporation'
                )
            ),
            'Registration Agency'           => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'registration_agency'
                )
            ),
            'Company Number'                => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'company_number'
                )
            ),
            'Corporate Grouping'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'parent_company'
                )
            ),
            'Participation Share'           => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'participation_share'
                )
            ),
            'Open Corporates Link'          => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'open_corporate_id'
                )
            ),
            'Incorporation Date'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'company_founding_date'
                )
            ),
            'Operator'                      => implode(';', $this->getOperator($contract->company)),
            'Project Title'                 => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->concession,
                    'license_name'
                )
            ),
            'Project Identifier'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->concession,
                    'license_identifier'
                )
            ),
            'License Name'                  => $contract->project_title,
            'License Identifier'            => $contract->project_identifier,
            'Source Url'                    => $contract->source_url,
            'Disclosure Mode'               => $contract->disclosure_mode,
            'Retrieval Date'                => $contract->date_retrieval,
            'Pdf Url'                       => $contract->file_url,
            'Deal Number'                   => $contract->deal_number,
            'Contract Note'                 => $contract->contract_note,
            'Matrix Page'                   => $contract->matrix_page,
            'Key Clause'           => isset($annotations->annotation_category) ? $annotations->annotation_category : '',
            'Clause Summary'               => isset($annotations->text) ? $annotations->text : '',
        ];
    }
    if($request['category']=='rc')
    {
        $data =[
            'OCID'                          => $contract->open_contracting_id,
            'Association'                      => ($contract->is_supporting_document==0)?"Main":"Supporting",
            'Contract Name'                 => $contract->contract_name,
            'PDF URL'                       => $contract->file_url,

            'Language'                      => $contract->language,
            'Country Name'                  => $contract->country->name,
            'Resource'                      => implode(';', $contract->resource),
            'Contract Type'                 => implode(';', $contract->type_of_contract),
            'Signature Date'                => $contract->signature_date,
            'Document Type'                 => $contract->document_type,
            'Government Entity'             => implode(
                ';',
                $this->makeSemicolonSeparated($contract->government_entity, 'entity')
            ),
          
            'Company Name'                  => implode(';', $this->makeSemicolonSeparated($contract->company, 'name')),
            'Company Address'               => implode(
                ';',
                $this->makeSemicolonSeparated($contract->company, 'company_address')
            ),
            'Jurisdiction of Incorporation' => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'jurisdiction_of_incorporation'
                )
            ),
            'Registration Agency'           => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'registration_agency'
                )
            ),
            'Company Number'                => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'company_number'
                )
            ),
            'Corporate Grouping'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'parent_company'
                )
            ),
            'Participation Share'           => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'participation_share'
                )
            ),
            'Open Corporates Link'          => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'open_corporate_id'
                )
            ),
            'Incorporation Date'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->company,
                    'company_founding_date'
                )
            ),
            'Operator'                      => implode(';', $this->getOperator($contract->company)),
            'Project Title'                 => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->concession,
                    'license_name'
                )
            ),
            'Project Identifier'            => implode(
                ';',
                $this->makeSemicolonSeparated(
                    $contract->concession,
                    'license_identifier'
                )
            ),
            'License Name'                  => $contract->project_title,
            'License Identifier'            => $contract->project_identifier,
            'Source Url'                    => $contract->source_url,
            'Disclosure Mode'               => $contract->disclosure_mode,

            'Key Clause'           =>  ( isset($contract->annotation) && !empty($contract->annotation))? implode(

                ',',
                $this->makeSemicolonSeparated(
                    $contract->annotation,
                    'annotation_category'

                )) : '',
            'View Clause'               =>  ( isset($contract->annotation) && !empty($contract->annotation)) ? implode(

                ',',
                $this->makeSemicolonSeparated(
                    $contract->annotation,
                    'article_reference'

                )) : '',

        ];
        

        if (empty($request['annotation_category'])) {
            unset($data['Key Clause'],$data['View Clause']);
        }
        
    }
    return $data;
    }

    /**
     * Return the formatted annotations data
     *
     * @param $annotations
     *
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
                'Article Reference' => $annotation['article_reference'],
            ];
        }

        return $data;
    }
}
