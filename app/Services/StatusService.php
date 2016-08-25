<?php namespace App\Services;

class StatusService extends Services
{

    const ORDER = "asc";
    const FROM  = 0;
    const SIZE  = 25;

    /**
     * Get the status of element
     * @param $request
     */
    public function getStatus($request)
    {
        $now         = date('Y-m-d H:i:s');
        $updatedDate = isset($request['updated_date']) ? $request['updated_date'] : "1900-01-01";
        $date        = date('Y-m-d H:i:s', strtotime($updatedDate));

        $data               = $this->getMetadataStatus($date, $now);
        $data               = $this->getTextOrAnnotStatus($date, $now, $data, 'pdf_text');
        $data               = $this->getTextOrAnnotStatus($date, $now, $data, 'annotations');
        $restult            = [];
        $restult['total']   = count($data);
        $restult['results'] = array_values($data);

        return $restult;
    }

    public function getMetadataStatus($date, $now)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = "metadata";
        $params['body']  = [
            "size"  => 10000,
            "query" => [
                "range" => [
                    "updated_at" => [
                        "from" => $date,
                        "to"   => $now
                    ]
                ]
            ],
            "sort"  => [
                [
                    "updated_at" => [
                        "order" => "desc"
                    ]
                ]
            ]
        ];

        $results = $this->search($params);
        $data    = [];
        foreach ($results['hits']['hits'] as $result) {
            $source                           = $result['_source'];
            $data[$result['_id']]['id']       = (int) $result['_id'];
            $data[$result['_id']]['metadata'] = [
                'created_at' => $source['created_at'],
                'updated_at' => $source['updated_at'],
                'category'   => $source['metadata']['category'][0],
                'created_by' => [
                    'name'  => $source['created_user_name'],
                    'email' => $source['created_user_email']
                ],
                'updated_by' => [
                    'name'  => $source['updated_user_name'],
                    'email' => $source['updated_user_email']
                ]
            ];
        }

        return $data;
    }

    private function getTextOrAnnotStatus($date, $now, $data, $type)
    {
        $params          = [];
        $params['index'] = $this->index;
        $params['type']  = $type;
        $params['body']  = [
            "size"  => 1000000000,
            "query" => [
                "range" => [
                    "updated_at" => [
                        "from" => $date,
                        "to"   => $now
                    ]
                ]
            ],
            "sort"  => [
                [
                    "updated_at" => [
                        "order" => "desc"
                    ]
                ]
            ]
        ];

        $results = $this->search($params);

        foreach ($results['hits']['hits'] as $result) {
            $source = $result['_source'];

            if (!isset($data[$source['contract_id']]['text'])) {
                if (!isset($data[$source['contract_id']]['id'])) {
                    $data[$source['contract_id']]['id'] = $source['contract_id'];
                }
                $data[$source['contract_id']][$type] = [
                    'created_at' => $source['created_at'],
                    'updated_at' => $source['updated_at'],
                ];
            }

        }

        return $data;
    }


}