<?php namespace Tests;

class TestCase extends ApiTester
{
    protected $baseUrl = 'http://localhost:2020';
    protected $indexUrl = 'http://localhost:3030/';
    protected $jsonPath = [
            'add'    => [
                    'metadata'   => '/../json/metadata.json',
                    'pdf'        => '/../json/texts.json',
                    'annotation' => '/../json/annotation.json',
            ],
            'update' => [
                    'metadata'   => '/../json/metadata_updated.json',
                    'pdf'        => '',
                    'annotation' => '',
            ],
    ];

    /** @test * */
    public function it_indexes_metadata()
    {
        $contracts = json_decode(file_get_contents(__DIR__ . $this->jsonPath['add']['metadata']), true);

        foreach ($contracts as $contract) {
            $this->post($this->indexUrl . 'contract/metadata', $contract)->matchValue('created', 1);
        }

        sleep(5);

    }

    /** @test * */
    public function it_counts_contracts_result()
    {
        $contracts = json_decode(file_get_contents(__DIR__ . $this->jsonPath['add']['metadata']), true);
        $total     = count($contracts);

        $keys         = ['total', 'per_page', 'from', 'results'];
        $results_keys = ['contract_id', 'contract_name', 'country', 'country_code', 'signature_year', 'language', 'resources', 'file_size', 'category'];

        $this->get('contracts')
                ->seeJson()
                ->matchValue('total', $total)
                ->seeKeys($keys)
                ->shouldHaveResults('results', $total);
    }

    /** @test * */
    public function it_updates_metadata()
    {
        $contracts = json_decode(file_get_contents(__DIR__ . $this->jsonPath['update']['metadata']), true);

        foreach ($contracts as $contract) {
            $this->post($this->indexUrl . 'contract/metadata', $contract)->matchValue('_id', 1)->getJson();
        }


    }

    /** @test * */
    public function it_filters_result_by_country_and_year()
    {
        $output = $this->get('/contracts?country_code=np&year=2010')
                ->seeJson()
                ->matchValue('total', 2)
                ->getJson();

        $this->assertEquals('2010', $output->results[0]->signature_year);

    }

    /** @test * */
    public function it_searches_sorts_descending()
    {
        $output = $this->get('/contracts?country_code=np&sort_by=year&order=desc')
                ->seeJson()
                ->matchValue('total', '3')
                ->getJson();

        //check if the result is in descending order or not , we can do this by checking the arrays
        $this->assertEquals('2012', $output->results[0]->signature_year);
        $this->assertEquals('2010', $output->results[1]->signature_year);

    }

    /** @test * */
    public function it_searches_by_country_resource_per_page()
    {
        $result = $this->get('/contracts?country_code=np&resource=gold&per_page=2&from=1')
                ->seeJson()
                ->matchValue('total', 3)
                ->matchValue('per_page', 2)
                ->matchValue('from', 1)
                ->getJson();

        $this->assertEquals('Third Ram', $result->results[1]->contract_name);

    }

    /** @test */
    public function it_checks_by_category()
    {
        $output = $this->get('/contracts?category=olc&sort_by=year&order=desc')
                ->seeJson()
                ->matchValue('total', 3)
                ->getJson();
        $this->assertEquals(['rc', 'olc'], $output->results[0]->category);

    }

    /** @test */
    public function invalid_parameters()
    {
        $this->get('/contracts?year=2017&resource=gold')
                ->seeJson()
                ->matchValue('total', 0)
                ->matchValue('results', []);
    }

    /* Thus this ends all the tests for Contracts */
    /* Now moving for indexing the Annotations and then again we will check */
    /** @tests */
    public function it_indexes_annotations()
    {
        $annotations = json_decode(file_get_contents(__DIR__ . $this->jsonPath['add']['annotation']), true);

        //inside annotation.json understand one thing, id for all contract_id must be different else latest one will only be stored

        foreach ($annotations as $annotate) {

            $this->post($this->indexUrl . 'contract/annotations', $annotate)->matchValue('_type', 'master');

        }
        sleep(4);
    }

    /** @tests */
    public function checks_the_annotations()
    {
        $keys = ['total', 'result'];

        $result = $this->get('/contract/2/annotations')
                ->seeJson()
                ->seeKeys($keys)
                ->matchValue('total', 2)
                ->getJson();

        $this->assertEquals('MCC', $result->result[1]->quote);
        $this->assertEquals('Welcome to Nepal !!', $result->result[1]->text);

    }

    /** @test */
    public function checks_annotation_by_page()
    {
        $result = $this->get('/contract/1/annotations?page=5')
                ->seeJson()
                ->getJson();

        $this->assertEquals('I can understand this', $result->result[0]->text);

    }

    /** @test */
    public function checks_invalid_id_for_annotations()
    {
        $output = $this->get('http://localhost:2020/contract/22/annotations')
                ->seeJson()
                ->matchValue('total', 0)
                ->matchValue('result', []);

    }


    /* This ends the section for annotations also now let's move towards adding PDF Texts */
    /** @test */
    public function it_indexes_pdf_text()
    {
        $texts = json_decode(file_get_contents(__DIR__ . $this->jsonPath['add']['pdf']), true);

        foreach ($texts as $text) {
            $this->post($this->indexUrl . 'contract/pdf-text', $text)->matchValue('_type', 'master');
        }

        sleep(5);

    }

    /** @test */
    public function it_checks_pdf_texts()
    {
        $keys = ['total', 'result'];

        $results = $this->get('/contract/2/text')
                ->seeJson()
                ->seeKeys($keys)
                ->matchValue('total', '2')
                ->getJson();

        $this->assertEquals('2', $results->result[0]->contract_id);
        $this->assertEquals('HTML , CSS ,  Javascript < JQuery , angular js , front end development ', $results->result[0]->text);

    }

    /** @test */
    public function it_checks_pdf_text_perPage()
    {

        $output = $this->get('/contract/2/text?page=4')
                ->seeJson()
                ->matchValue('total', 1)
                ->getJson();
        $this->assertEquals('Nepal only nepal', $output->result[0]->text);
        $this->assertEquals('4', $output->result[0]->page_no);

    }

    /** @test */
    public function it_checks_invalid_pdfText_page_no()
    {

        $output = $this->get('http://localhost:2020/contract/2/text?page=20')
                ->seeJson()
                ->matchValue('total', 0)
                ->matchValue('result', []);

    }

    /** @test */
    public function it_searches_on_pdf_text()
    {
        $output = $this->get('http://localhost:2020/contract/2/searchtext?q=html')
                ->seeJson()
                ->matchValue('total', 1)
                ->getJson();

        $this->assertEquals('HTML , CSS ,  Javascript < JQuery , angular js , front end development ', $output->results[0]->text);

    }

    /* This ends PDF text section , we need to move towards searching */
    /** @tests */
    public function search_by_text()
    {
        $results = $this->get('/contracts/search?q=html&group=text')
                ->seeJson()
                ->getJson();

        $this->assertEquals('This is second contract', $results->results[0]->contract_name);

    }

    /** @tests */
    public function search_by_metadata()
    {
        $results = $this->get('/contracts/search?q=nepal&group=metadata')
                ->seeJson()
                ->matchValue('total', 3)
                ->getJson();

        $this->assertEquals('This is second contract', $results->results[1]->contract_name);

    }

    /** @tests */
    public function search_by_annotations()
    {
        $results = $this->get('http://localhost:2020/contracts/search?q=MCC&group=annotations')
                ->seeJson()
                ->matchValue('total', 3)
                ->getJson();

        $this->assertEquals(' <strong>MCC</strong>', $results->results[0]->annotations);

    }

    /** @tests */
    public function multiple_search()
    {
        // we need to pass the multiple parameters in order to search here:
        // we have provided 8 parameters for this
        $output = $this->get('http://localhost:2020/contracts/search?q=a&group=text,metadata,annotations&from=0&resource=gold&sort_by=year&order=desc&per_page=2&country=np')
                ->seeJson()
                ->matchValue('total', 3)
                ->getJson();

        //just trying to see that year is displayed in descending order
        $this->assertEquals('2012', $output->results[0]->signature_year);
        $this->assertEquals('2010', $output->results[1]->signature_year);

    }

    /** finally we need to delete everything */
    /** @test * */
    public function it_deletes_contracts()
    {
        $contracts = json_decode(file_get_contents(__DIR__ . $this->jsonPath['add']['metadata']), true);

        //first of all that id must exist
        foreach ($contracts as $contract) {
            $this->post($this->indexUrl . 'contract/delete', ['id' => $contract['id']])->getJson();
        }

        $this->get('http://localhost:2020/contracts')
                ->seeJson()
                ->matchValue('total', 0)
                ->matchValue('results', []);

        echo PHP_EOL;
        echo PHP_EOL;
        echo " ******************************";
        echo PHP_EOL;
        echo "this means our tests got completed";
        echo PHP_EOL;
        echo "***********************";
        echo PHP_EOL;

    }


}