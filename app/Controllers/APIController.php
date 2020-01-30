<?php namespace App\Controllers;

use App\Services\APIServices;
use App\Services\DownloadServices;
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
     * @var DownloadServices
     */
    private $download;


    /**
     * @param APIServices      $api
     * @param FulltextSearch   $search
     * @param DownloadServices $download
     */
    public function __construct(APIServices $api, FulltextSearch $search, DownloadServices $download)
    {
        parent::__construct();
        $this->api      = $api;
        $this->search   = $search;
        $this->download = $download;
    }

    /**
     * Home page
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function home()
    {
        return $this->view('home');
    }

    /**
     * Return all the summary of contract
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getSummary()
    {
        $response = $this->api->getSummary($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get the text page of contract
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getTextPages($request, $response, $argument)
    {
        $id   = $argument['id'];
        $data = $this->api->getTextPages($id, $this->request->query->all());

        return $this->json($data);
    }

    /**
     * Get the annotations page contract
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAnnotationPages($request, $response, $argument)
    {
        $id   = $argument['id'];
        $data = $this->api->getAnnotationPages($id, $this->request->query->all());

        return $this->json($data);
    }

    /**
     * Get the annotations page contract
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAnnotationGroup($request, $response, $argument)
    {
        $id   = $argument['id'];
        $data = $this->api->getAnnotationGroup($id, $this->request->query->all());

        return $this->json($data);
    }

    /**
     * Get the metadata
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return json response
     */
    public function getMetadata($request, $response, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->getMetadata($id, $this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get all the contract
     * @return json response
     */
    public function getAllContract()
    {
        $response = $this->api->getAllContracts($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Returns the contract count
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAllContractCount()
    {
        $response = $this->api->getAllContractCount();

        return $this->json($response);
    }

    /**
     * Search in Pdf Text
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function search($request, $response, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->searchAnnotationAndText($id, $this->request->query->all());

        return $this->json($response);
    }

    /**
     * Full text search
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function fullTextSearch()
    {
        $response = $this->search->searchInMaster($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Grouped Full text search with weight order
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function groupedfullTextSearch()
    {
        $response = $this->search->searchInMasterWithWeight($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get all the contracts according to countries
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getCoutriesContracts()
    {
        $response = $this->api->getCountriesContracts($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get aggregation of resource according to country
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getResourceContracts()
    {
        $response = $this->api->getResourceContracts($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get aggregation of years according to country
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getYearsContracts()
    {
        $response = $this->api->getYearsContracts($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get Contract aggregation by Country and Resource
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getContractByCountryAndResource()
    {
        $response = $this->api->getContractByCountryAndResource($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get all the unique filter attributes for search
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getFilterAttributes()
    {
        $response = $this->api->getFilterAttributes($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Get all the annotations category
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAnnotationsCategory()
    {
        $response = $this->api->getAnnotationsCategory($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Download metadata as csv
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadMetadtaAsCSV()
    {
        $response = $this->api->downloadMetadtaAsCSV($this->request->query->all());

        return $this->json($response);
    }

    /**
     * Download Annotations As CSV
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAnnotationsAsCSV($request, $response, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->downloadAnnotationsAsCSV($id);

        return $this->json($response);
    }

    /**
     * Get Annotation detail by ID
     *
     * @param $request
     * @param $response
     * @param $argument
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function getAnnotationById($request, $response = null, $argument)
    {
        $id       = $argument['id'];
        $response = $this->api->getAnnotationById($id, $request->query->all());

        return $this->json($response);
    }
}
