<?php

abstract class TestCase extends PHPUnit_Framework_TestCase
{
    public function getClient()
    {
        $client = new \Elasticsearch\ClientBuilder();
        $client = $client->create()->build();
        return $client;
    }

    public function indexMetadata()
    {
        $client         = $this->getClient();
        $data           = [
            'metadata'           => json_decode('{"contract_name":"Test contract","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":["coal"],"category":[],"file_size":54836}'),
            'updated_user_name'  => "admin",
            'updated_user_email' => "admin@nrgi.com",
            'created_user_name'  => "admin",
            'created_user_email' => "admin@nrgi.app",
            'created_at'         => "2015-06-19T04:26:24",
            'updated_at'         => "2016-06-20T04:26:24"
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_metadata";
        $param['id']    = 1;
        $param['body']  = $data;
        $response       = $client->index($param);
        return $response;
    }

    public function indexPdfText()
    {
        $client = $this->getClient();
        $data   = [
            'metadata'    => [
                "contract_name"  => "Test Contract",
                "country"        => [
                    "code" => "AL",
                    "name" => "Albania"
                ],
                "resource"       => ["coal"],
                "signature_date" => "2015-07-09",
                "category"       => [],
                "file_size"      => 804997,
                "file_url"       => "https://rc-demo.s3-us-west-2.amazonaws.com/5240d137bc55b60e6ab39b48dc05f6070d84750d.pdf",
                "signature_year" => "2015",
            ],
            "page_no"     => 1,
            "contract_id" => 1,
            "text"        => "Presented by developerWorks, your source for great tutorials<br />\n<br />\nibm.com/developerWorks<br />\n<br />\nThe AuthorList stored procedure<br />\nThe following example is a stored procedure that uses one IN parameter to generate a list of<br />\nall messages by that author. Note that the ResultSet object is returned as part of an array<br />\nof ResultSet objects, which is necessary because the Java language always passes<br />\nobjects by value. In addition, note that we do not need to handle any error conditions in the<br />\ncode, which is passed onto the calling methods.<br />\npackage com.persistentjava;<br />\nimport java.sql.*;<br />\npublic class AuthorList {<br />\npublic static void authorList(String value, ResultSet[] rs )<br />\nthrows SQLException, Exception {<br />\nString sql =<br />\n\"SELECT id, author, title FROM digest WHERE author = ?\";<br />\nConnection con = DriverManager.getConnection(\"jdbc:default:connection\");<br />\nPreparedStatement pstmt = con.prepareStatement(sql);<br />\npstmt.setString(1, value) ;<br />\nSystem.err.println(value) ;<br />\nrs[0] = pstmt.executeQuery();<br />\nif (con != null)<br />\ncon.close();<br />\n}<br />\n}<br />\n<br />\nCalling the AuthorList stored procedure<br />\nAdvanced database operations with JDBC<br />\n<br />\nPage 15 of 26<br />\n<br />\n\f",
            "pdf_url"     => "http://localhost:8000/data/52/pages/15.pd"
        ];

        $param['index'] = "test_nrgi";
        $param['type']  = "test_pdf_text";
        $param['id']    = 1;
        $param['body']  = $data;
        $response       = $client->index($param);
    }

    public function indexAnnotations()
    {
        $client         = $this->getClient();
        $data           = [
            'metadata'    => [
                "contract_name"  => "Test Contract",
                "country"        => [
                    "code" => "AL",
                    "name" => "Albania"
                ],
                "signature_year" => "2015-10-09",
                "resource"       => ["coal"],
                "category"       => ["rc"],
                "file_size"      => 433994,
                "file_url"       => "https://rc-demo.s3-us-west-2.amazonaws.com/1fba33ec9bbc1260d1370ca6b0f7ad4656352a01.pdf",
                "signature_year" => "2015",


            ],
            "quote"       => "professional",
            "text"        => "this is annotaions text",
            "tags"        => ["country"],
            "contract_id" => 1,
            "page_no"     => 1,
            "ranges"      => [
                [
                    "start"       => "/div[1]/div[2]/div[1]/div[3]",
                    "startOffset" => 47,
                    "end"         => "/div[1]/div[2]/div[1]/div[3]",
                    "endOffset"   => 59
                ]
            ]
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_annotations";
        $param['id']    = 1;
        $param['body']  = $data;
        $response       = $client->index($param);
        return $response;
    }

    public function indexMaster()
    {
        $data=[
            "metadata"=>[
                "contract_name"=>"Test Contract",
                "signature_year"=>"2015",
                "signature_date"=>"2015-10-09",
                "country_code"=>"AL",
                "country_name"=>"Albania",
                "file_size"=>433994,

                "language"=>"FR",
            ],
            "metadata_string"=>"Upload to s3 test FR AL Albania Production sharing Agreements and Contract 2015-10-09 Contract 2015 433994 https://rc-demo.s3-us-west-2.amazonaws.com/1fba33ec9bbc1260d1370ca6b0f7ad4656352a01.pdf",
            "pdf_text_string"=>strip_tags('Presented by developerWorks, your source for great tutorials<br />\n<br />\nibm.com/developerWorks<br />\n<br />\nThe AuthorList stored procedure<br />\nThe following example is a stored procedure that uses one IN parameter to generate a list of<br />\nall messages by that author. Note that the ResultSet object is returned as part of an array<br />\nof ResultSet objects, which is necessary because the Java language always passes<br />\nobjects by value. In addition, note that we do not need to handle any error conditions in the<br />\ncode, which is passed onto the calling methods.<br />\npackage com.persistentjava;<br />\nimport java.sql.*;<br />\npublic class AuthorList {<br />\npublic static void authorList(String value, ResultSet[] rs )<br />\nthrows SQLException, Exception {<br />\nString sql =<br />\n\"SELECT id, author, title FROM digest WHERE author = ?\";<br />\nConnection con = DriverManager.getConnection(\"jdbc:default:connection\");<br />\nPreparedStatement pstmt = con.prepareStatement(sql);<br />\npstmt.setString(1, value) ;<br />\nSystem.err.println(value) ;<br />\nrs[0] = pstmt.executeQuery();<br />\nif (con != null)<br />\ncon.close();<br />\n}<br />\n}<br />\n<br />\nCalling the AuthorList stored procedure<br />\nAdvanced database operations with JDBC<br />\n<br />\nPage 15 of 26<br />\n<br />\n\f'),
            "annotations_string"=>"professional this is annotaions text country"
        ];
        $param['index'] = "test_nrgi";
        $param['type']  = "test_master";
        $param['id']    = 1;
        $param['body']  = $data;
        $response       = $this->getClient()->index($param);
        return $response;
    }
}