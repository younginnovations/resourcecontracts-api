<?php namespace Tests;

class PdfTextTest extends ApiTester
{
    protected $baseUrl = 'http://localhost:3030/';


    /** @test */
    public function it_indexes_texts()
    {
        $texts = json_decode(file_get_contents(__DIR__ . '/../json/texts.json'), true);


        foreach ($texts as $text) {

            print_r($this->post('contract/pdf-text', $text)->matchValue('_type', 'master')->getJson());
        }

        echo PHP_EOL;
        echo "i added the index for the PDF-text and also slept for 3 seconds";
        echo PHP_EOL;
        sleep(3);

    }


    /** @test */
    public function it_checks_texts()
    {
        //let's check the keys::
        $keys = ['total' , 'result'];

        $results = $this->get('http://localhost:2020/contract/2/text')
            ->seeJson()
            ->seeKeys($keys)
            ->matchValue('total' , '2')
            ->getJson();

        $this->assertEquals('2', $results->result[0]->contract_id);
        $this->assertEquals('HTML , CSS ,  Javascript < JQuery , angular js , front end development ', $results->result[0]->text);


             echo "It passed the test";


    }



    /** @test */
    public function it_checks_text_perPage()
    {

        $output = $this->get('http://localhost:2020/contract/2/text?page=4')
            ->seeJson()
            ->matchValue('total' , 1)
            ->getJson();
        $this->assertEquals('Nepal only nepal' , $output->result[0]->text);
        $this->assertEquals('4' , $output->result[0]->page_no);



        echo PHP_EOL;
        echo "For second contract with page number two it's seen easily";
        echo PHP_EOL;


    }

    /** @test */
    public function invalid_page_no()
    {
        //we have contract but if searching by the page which doesnt exists is done then we see []

        $output = $this->get('http://localhost:2020/contract/2/text?page=20')
            ->seeJson()
            ->matchValue('total' , 0)
            ->matchValue('result' , []);

        echo PHP_EOL;
        echo "Test passed if invalid page is provided";
        echo PHP_EOL;

    }


    /** @test */
    public function it_searches_pdf_text()
    {
        $output = $this->get('http://localhost:2020/contract/2/searchtext?q=html')
            ->seeJson()
            ->matchValue('total' , 1)
            ->getJson();

        $this->assertEquals('HTML , CSS ,  Javascript < JQuery , angular js , front end development ', $output->results[0]->text);
//        print_r($output->results[0]->contract_id);
        echo PHP_EOL;
        echo "Searching of the text is completed";
    }


//    /** @test */

    public function it_deletes_texts()
    {
        //this needs to delete the main contract with id 12 in our case

        $this->post('contract/delete' ,['id'  =>  12] )->getJson();

    }


}