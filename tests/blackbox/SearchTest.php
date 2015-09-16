<?php namespace Tests;


class SearchTest extends ApiTester
{
    //since we have already  indexed the texts , metadata and annotations from another page we can more further
    protected  $baseUrl = "http://localhost:2020";

    /** @tests */
    public function search_by_text()
    {
        $results = $this->get('http://localhost:2020/contracts/search?q=html&group=text')
                ->seeJson()
                ->getJson();

       $this->assertEquals('This is second contract' , $results->results[0]->contract_name );

        echo PHP_EOL;
        echo "search by text is complete";
        echo PHP_EOL;

    }

    /** @tests */
    public function search_by_metadata()
    {
        $results = $this->get('http://localhost:2020/contracts/search?q=nepal&group=metadata')
            ->seeJson()
            ->matchValue('total' , 3)
            ->getJson();

        $this->assertEquals('This is second contract' , $results->results[1]->contract_name );
        echo PHP_EOL;
        echo "search by metadata complete";
        echo PHP_EOL;

    }

    /** @tests */
    public function search_by_annotations()
    {
        $results = $this->get('http://localhost:2020/contracts/search?q=MCC&group=annotations')
            ->seeJson()
            ->matchValue('total' ,3)
            ->getJson();

        $this->assertEquals(' <strong>MCC</strong>' , $results->results[0]->annotations );


        echo PHP_EOL;
        echo "search by annotations complete";
        echo PHP_EOL;
    }

    /** @tests */
    public function multiple_search()
    {
        // we need to pass the multiple parameters in order to search here:
        // we have provided 8 parameters for this
      $output =  $this->get('http://localhost:2020/contracts/search?q=a&group=text,metadata,annotations&from=0&resource=gold&sort_by=year&order=desc&per_page=2&country=np')
            ->seeJson()
            ->matchValue('total' ,3)
            ->getJson();

        //just trying to see that year is displayed in descending order
        $this->assertEquals('2012' , $output->results[0]->signature_year);
        $this->assertEquals('2010' , $output->results[1]->signature_year);


        echo PHP_EOL;
        echo "8 parameters passed ";
        echo PHP_EOL;

    }

}