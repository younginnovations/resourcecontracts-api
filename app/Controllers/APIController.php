<?php namespace App\Controllers;

use App\Services\APIServices;
use App\Services\FulltextSearch;

/**
 * API Controller for Client site
 * Class APIController
 * @package App\Controllers
 */
class APIController extends BaseController
{
    /**
     * @var APIServices
     */
    private $api;
    /**
     * @var FulltextSearch
     */
    private $search;

    /**
     * @param APIServices $api
     * @param FulltextSearch $search
     */
    public function __construct(APIServices $api,FulltextSearch $search)
    {
        parent::__construct();
        $this->api = $api;
        $this->search = $search;
    }

    public function home()
    {
        return $this->view('home');
    }

    /**
     * Return all the summary of contract
     * @return json response
     */
    public function getSummary()
    {

        $reponse = $this->api->getSummary();
        return $this->json($reponse);
    }

    /**
     * search
     * @return response
     */
    public function search()
    {
        $response = $this->api->textSearch($this->request->query->all());
        return $this->json($response);

    }

    /**
     * Filter
     * @return array
     */
    public function filterContract()
    {
        return $this->api->getFilterData($this->request->query->all());
    }

    /**
     * Get the text page of contract
     * @param $request
     * @param $response
     * @param $argument
     * @return json response
     */
    public function getTextPages($request, $response, $argument)
    {
        $id      = $argument['id'];
        $page_no = $argument['page_no'];
        $data    = $this->api->getTextPages($id, $page_no);
        return $this->json($data);
    }

    /**
     * Get the annotations page contract
     * @param $request
     * @param $response
     * @param $argument
     * @return json response
     */
    public function getAnnotationPages($request, $response, $argument)
    {
        $id      = $argument['id'];
        $page_no = $argument['page_no'];
        $data    = $this->api->getAnnotationPages($id, $page_no);
        return $this->json($data);
    }

    /**
     * Get the metadata
     * @param $request
     * @param $response
     * @param $argument
     * @return json response
     */
    public function getMetadata($request, $response, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->getMetadata($id);
        return $this->json($response);
    }

    /**
     * Get all Annotations of contract
     * @param $request
     * @param $response
     * @param $argument
     * @return json response
     */
    public function getContractAnnotation($request, $response, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->getContractAnnotations($id);
        return $this->json($response);
    }

    /**
     * Get all the contract
     * @return json response
     */
    public function getAllContract()
    {
        $response = $this->api->getAllContracts();
        return $this->json($response);
    }

    /**
     * Retunrs the contract count
     * @return json response
     */
    public function getAllContractCount()
    {
        $response = $this->api->getAllContractCount();
        return $this->json($response);
    }

    /**
     * Search in Pdf Text
     * @return json response
     */
    public function pdfSearch()
    {
        $response = $this->api->pdfSearch($this->request->query->all());
        return $this->json($response);
    }

    /**
     * Full text search
     * @return json response
     */
    public function fullTextSearch()
    {

        $response = $this->search->FullTextSearch($this->request->query->all());
        return $this->json($response);
    }
}
