<?php

class APITest extends TestCase
{
    public function setUp()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->create($params);
        $this->indexMetadata();
        $this->indexPdfText();
        $this->indexAnnotations();
        $this->indexMaster();
    }

    public function tearDown()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->delete($params);
    }

    public function test_get_summary()
    {

        $client               = $this->getClient();
        $searchParam['index'] = "test_nrgi";
        $client->indices()->refresh($searchParam);
        $searchParam['type'] = "test_metadata";
        $searchParam['body'] = [
            'size' => 0,
            'aggs' =>
                [
                    'country_summary'  =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.country.code',
                                ],
                        ],
                    'year_summary'     =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.signature_year',
                                ],
                        ],
                    'resource_summary' =>
                        [
                            'terms' =>
                                [
                                    'field' => 'metadata.resource',
                                ],
                        ],
                ],
        ];

        $response                 = $client->search($searchParam);
        $data['country_summary']  = $response['aggregations']['country_summary']['buckets'];
        $data['year_summary']     = $response['aggregations']['year_summary']['buckets'];
        $data['resource_summary'] = $response['aggregations']['resource_summary']['buckets'];
        $jsonString               = '{"country_summary":[{"key":"al","doc_count":1}],"year_summary":[{"key":"2015","doc_count":1}],"resource_summary":[{"key":"coal","doc_count":1}]}';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);

    }

    public function test_getTextPages()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type']                          = "test_pdf_text";
        $params['body']['query']['bool']['must'] = [
            [
                "term" => ["contract_id" => ["value" => 1]],
            ],
            [
                "term" => ["page_no" => ["value" => 1]]
            ]
        ];
        $results                                 = $client->search($params);
        $jsonString                              = '{"metadata":{"contract_name":"Test Contract","country":{"code":"AL","name":"Albania"},"resource":["coal"],"signature_date":"2015-07-09","category":[],"file_size":804997,"file_url":"https:\/\/rc-demo.s3-us-west-2.amazonaws.com\/5240d137bc55b60e6ab39b48dc05f6070d84750d.pdf","signature_year":"2015"},"page_no":1,"contract_id":1,"text":"Presented by developerWorks, your source for great tutorials<br \/>\n<br \/>\nibm.com\/developerWorks<br \/>\n<br \/>\nThe AuthorList stored procedure<br \/>\nThe following example is a stored procedure that uses one IN parameter to generate a list of<br \/>\nall messages by that author. Note that the ResultSet object is returned as part of an array<br \/>\nof ResultSet objects, which is necessary because the Java language always passes<br \/>\nobjects by value. In addition, note that we do not need to handle any error conditions in the<br \/>\ncode, which is passed onto the calling methods.<br \/>\npackage com.persistentjava;<br \/>\nimport java.sql.*;<br \/>\npublic class AuthorList {<br \/>\npublic static void authorList(String value, ResultSet[] rs )<br \/>\nthrows SQLException, Exception {<br \/>\nString sql =<br \/>\n\"SELECT id, author, title FROM digest WHERE author = ?\";<br \/>\nConnection con = DriverManager.getConnection(\"jdbc:default:connection\");<br \/>\nPreparedStatement pstmt = con.prepareStatement(sql);<br \/>\npstmt.setString(1, value) ;<br \/>\nSystem.err.println(value) ;<br \/>\nrs[0] = pstmt.executeQuery();<br \/>\nif (con != null)<br \/>\ncon.close();<br \/>\n}<br \/>\n}<br \/>\n<br \/>\nCalling the AuthorList stored procedure<br \/>\nAdvanced database operations with JDBC<br \/>\n<br \/>\nPage 15 of 26<br \/>\n<br \/>\n\f","pdf_url":"http:\/\/localhost:8000\/data\/52\/pages\/15.pd"}';
        $this->assertJsonStringEqualsJsonString(json_encode($results['hits']['hits'][0]['_source']), $jsonString);
    }

    public function test_getAnnotationPages()
    {
        $client          = $this->getClient();
        $params          = [];
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);

        $params['type']                          = "test_annotations";
        $params['body']['query']['bool']['must'] = [
            [
                "term" => ["contract_id" => ["value" => 1]],
            ],
            [
                "term" => ["page_no" => ["value" => 1]]
            ]
        ];
        $results                                 = $client->search($params);
        $data                                    = [];
        foreach ($results['hits']['hits'] as $result) {
            $temp         = $result['_source'];
            $temp['id']   = (integer)$result['_id'];
            $data['rows'] = [$temp];
        }
        $jsonString = '{"rows":[{"metadata":{"contract_name":"Test Contract","country":{"code":"AL","name":"Albania"},"signature_year":"2015","resource":["coal"],"category":["rc"],"file_size":433994,"file_url":"https:\/\/rc-demo.s3-us-west-2.amazonaws.com\/1fba33ec9bbc1260d1370ca6b0f7ad4656352a01.pdf"},"quote":"professional","text":"this is annotaions text","tags":["country"],"contract_id":1,"page_no":1,"ranges":[{"start":"\/div[1]\/div[2]\/div[1]\/div[3]","startOffset":47,"end":"\/div[1]\/div[2]\/div[1]\/div[3]","endOffset":59}],"id":1}]}';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);
    }

    public function test_getMetadata()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type']           = "test_metadata";
        $params['body']['filter'] = [
            "and" => [
                "filters" => [
                    [
                        'term' => [
                            '_id' => 1
                        ]
                    ]
                ]
            ]
        ];
        $result                   = $client->search($params);
        $results                  = $result['hits']['hits'][0]['_source'];
        $jsonString               = '{"metadata":{"contract_name":"Test contract","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":["coal"],"category":[],"file_size":54836},"updated_user_name":"admin","updated_user_email":"admin@nrgi.com","created_user_name":"admin","created_user_email":"admin@nrgi.app","created_at":"2015-06-19T04:26:24","updated_at":"2016-06-20T04:26:24"}';
        $this->assertJsonStringEqualsJsonString(json_encode($results), $jsonString);
    }

    public function test_getContractAnnotations()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type'] = "test_annotations";
        $params['body'] = [
            'filter' =>
                [
                    'and' =>
                        [
                            'filters' =>
                                [
                                    0 =>
                                        [
                                            'term' =>
                                                [
                                                    'contract_id' => 1,
                                                ],
                                        ],
                                ],
                        ],
                ],
        ];

        $results = $client->search($params);
        $results = $results['hits']['hits'];
        $data    = [];
        foreach ($results as $result) {

            $data[] = [
                'quote'   => $result['_source']['quote'],
                'text'    => $result['_source']['text'],
                'tag'     => $result['_source']['tags'],
                'page_no' => $result['_source']['page_no'],
                'ranges'  => $result['_source']['ranges'][0],
            ];

        }
        $jsonString = '[{"quote":"professional","text":"this is annotaions text","tag":["country"],"page_no":1,"ranges":{"start":"\/div[1]\/div[2]\/div[1]\/div[3]","startOffset":47,"end":"\/div[1]\/div[2]\/div[1]\/div[3]","endOffset":59}}]';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);
    }

    public function test_getAllContracts()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type']                       = "test_metadata";
        $params['body']['query']["match_all"] = [];
        $results                              = $client->search($params);

        $results = $results['hits']['hits'];
        $data    = [];
        foreach ($results as $result) {
            array_push($data, $result['_source']);
        }
        $jsonString = '[{"metadata":{"contract_name":"Test contract","contract_identifier":"","language":"EN","country":{"code":"AL","name":"Albania"},"government_entity":"","government_identifier":"","type_of_contract":"","signature_date":"2015-06-23","document_type":"Contract","translation_parent":"","company":[{"name":"","jurisdiction_of_incorporation":"","registration_agency":"","company_foundin g_date":"","company_address":"","comp_id":"","parent_company":"","open_corporate_id":""}],"license_name":"","license_identifier":"","project_title":"","project_identifier":"","Source_url":"","date_retrieval":"","signature_year":"2015","resource":["coal"],"category":[],"file_size":54836},"updated_user_name":"admin","updated_user_email":"admin@nrgi.com","created_user_name":"admin","created_user_email":"admin@nrgi.app","created_at":"2015-06-19T04:26:24","updated_at":"2016-06-20T04:26:24"}]';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);
    }

    public function test_getAllContractCount()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type']                       = "test_metadata";
        $params['body']["query"]["match_all"] = [];
        $response                             = $client->count($params);
        $count                                = $response['count'];
        $this->assertEquals($count, 1);
    }

    public function test_pdfSearch()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type'] = "test_pdf_text";
        $params['body'] = [
            "query"     => [
                "filtered" => [
                    "query"  => [
                        "query_string" => [
                            "default_field" => "text",
                            "query"         => "Presented"
                        ]
                    ],
                    "filter" => [
                        "term" => [
                            "contract_id" => 1
                        ]
                    ]
                ]
            ],
            "highlight" => [
                "fields" => [
                    "text" => [
                        "fragment_size"       => 200,
                        "number_of_fragments" => 1
                    ]
                ]
            ],
            "fields"    => [
                "page_no",
                "contract_id"
            ]
        ];
        $response       = $client->search($params);
        $hits           = $response['hits']['hits'];
        $data           = [];
        foreach ($hits as $hit) {
            $fields = $hit['fields'];
            $text   = $hit['highlight']['text'][0];
            if (!empty($text)) {
                $data[] = [
                    'page_no'     => $fields['page_no'][0],
                    'contract_id' => $fields['contract_id'][0],
                    'text'        => strip_tags($text)
                ];
            }

        }
        $jsonString = '[{"page_no":1,"contract_id":1,"text":"Presented by developerWorks, your source for great tutorials\n\nibm.com\/developerWorks\n\nThe AuthorList stored procedure\nThe following example is a stored procedure that"}]';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);
    }

    public function test_FulltextSearch()
    {
        $client          = $this->getClient();
        $params['index'] = "test_nrgi";
        $client->indices()->refresh($params);
        $params['type'] = "test_master";

        $filters[] = ["terms" => ["metadata.signature_year" => ["2015"]]];
        $filters[] = ["terms" => ["metadata.country_code" => ["al"]]];
        $fields    = [];
        array_push($fields, "metadata_string");
        array_push($fields, "pdf_text_string");
        array_push($fields, "annotations_string");
        $params['body']['query']['query_string'] = [
            "fields" => $fields,
            'query'  => "presented"
        ];

        $params['body']['filter'] = [
            "and" => [
                "filters" => $filters
            ]
        ];

        $params['body']['fields']                                 = [
            "metadata.contract_name",
            "metadata.signature_year",
            "metadata.file_size",
            "metadata.country_code",
            "metadata.country_name",
            "metadata.language",
            "metadata.file_size"
        ];
        $params['body']['sort']['metadata.country_name']['order'] = "asc";
        $highlightField                                           = [];
        $highlightField['metadata_string']                        = [
            'fragment_size'       => 1,
            'number_of_fragments' => 1,
        ];

        $highlightField['pdf_text_string'] = [
            'fragment_size'       => 1,
            'number_of_fragments' => 1,
        ];

        $highlightField['annotations_string'] = [
            'fragment_size'       => 1,
            'number_of_fragments' => 1,
        ];


        $params['body']['highlight'] = [
            'pre_tags'  => [
                '<strong>',
            ],
            'post_tags' => [
                '</strong>',
            ],
            'fields'    => $highlightField,
        ];

        $params['body']['size'] = 20;
        $params['body']['from'] = 0;
        $results                = $client->search($params);

        $fields          = $results['hits']['hits'];
        $data            = [];
        $data['country'] = [];
        $data['year']    = [];
        $data['result']  = [];
        $i               = 0;

        foreach ($fields as $field) {

            $contractId = $field['_id'];
            array_push($data['country'], $field['fields']['metadata.country_code'][0]);
            array_push($data['year'], $field['fields']['metadata.signature_year'][0]);
            $data['result'][$i]                = [
                "contract_id"    => $contractId,
                "contract_name"  => $field['fields']['metadata.contract_name'][0],
                "signature_year" => $field['fields']['metadata.signature_year'][0],
                'country'        => $field['fields']['metadata.country_code'][0],
                "file_size"      => $field['fields']['metadata.file_size'][0],
                "language"       => $field['fields']['metadata.language'][0],
            ];
            $data['result'][$i]['type']        = [];
            $highlight                         = $field['highlight'];
            $data['result'][$i]['text']        = isset($highlight['pdf_text_string'][0]) ? $highlight['pdf_text_string'][0] : '';
            $data['result'][$i]['annotations'] = isset($highlight['annotations_string'][0]) ? $highlight['annotations_string'][0] : '';
            if (isset($highlight['pdf_text_string'])) {
                array_push($data['result'][$i]['type'], "Text");
            }
            if (isset($highlight['metadata_string'])) {
                array_push($data['result'][$i]['type'], "Metadata");
            }
            if (isset($highlight['annotations_string'])) {
                array_push($data['result'][$i]['type'], "Annotation");
            }

            $i++;
        }

        $data['country']  = isset($data['country']) ? array_unique($data['country']) : [];
        $data['year']     = isset($data['year']) ? array_unique($data['year']) : [];
        $data['per_page'] = isset($data['result']) ? count($data['result']) : 0;

        $jsonString = '{"country":["AL"],"year":["2015"],"result":[{"contract_id":"1","contract_name":"Test Contract","signature_year":"2015","country":"AL","file_size":433994,"language":"FR","type":["Text"],"text":"<strong>Presented<\/strong>","annotations":""}],"per_page":1}';
        $this->assertJsonStringEqualsJsonString(json_encode($data), $jsonString);
    }

}