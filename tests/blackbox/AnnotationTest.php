<?php namespace Tests;

class AnnotationTest extends ApiTester
{
    protected $baseUrl = 'http://localhost:3030/';

    /** @tests */
    public function it_indexes_annotations()
    {
        $annotations = json_decode(file_get_contents(__DIR__ . '/../json/annotation.json'), true);

        //inside annotation.json understand one thing, id for all contract_id must be different else latest one will only be stored


         foreach ($annotations as $annotate) {

            print_r($this->post('contract/annotations', $annotate)->matchValue('_type', 'master')->getJson());
        }

        sleep(4);
        echo PHP_EOL;
        echo "Indexing of the annotations is now complete. Slept for 4 seconds.";
        echo PHP_EOL;


    }

    /** @tests */
    public function checks_the_annotations()
    {
        $keys = ['total' , 'result'];

       $result =  $this->get('http://localhost:2020/contract/2/annotations')
             ->seeJson()
             ->seeKeys($keys)
             ->matchValue('total' , 2)
             ->getJson();

        $this->assertEquals('MCC' , $result->result[1]->quote);
        $this->assertEquals('Welcome to Nepal !!' , $result->result[1]->text);
        echo PHP_EOL;
        echo "Checking of the annotations is now complete.";
        echo PHP_EOL;

    }

    /** @test */
    public function checks_annotation_by_page()
    {
        $result = $this->get('http://localhost:2020/contract/1/annotations?page=5')
                ->seeJson()
                ->getJson();

        $this->assertEquals('I can understand this' , $result->result[0]->text);

        echo PHP_EOL;
        echo "Displayed the annotations per page :)";
        echo PHP_EOL;

    }


    /** @test */
    public function invalid_id()
    {
        $output = $this->get('http://localhost:2020/contract/22/annotations')
            ->seeJson()
            ->matchValue('total' , 0)
            ->matchValue('result' , []);
        echo PHP_EOL;
        echo "Passed test for invalid ID also";
        echo PHP_EOL;


    }




/** @tests */

    public function delete_the_annotation()
    {
        //deleting the main contract direcly deletes it::

        $this->post('contract/delete' ,['id'  => 45 ] )->getJson();

    }




}