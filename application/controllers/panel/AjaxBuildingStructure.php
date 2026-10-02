<?php

use GuzzleHttp\Client;

class AjaxBuildingStructure extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
        if(! $this->input->is_ajax_request())
        {
            redirect('404');
        }
    }

    public function select2()
    {
        $term = $this->input->post("term");
        $limit = $this->input->post("limit");
        $page = max(1, $this->input->post("page"));
        $offset = ($page-1)*$limit;
        $records = Model_building_structure::search($term, $limit, $offset, NULL, 'asc', ['structure_code_bus','description_bus']);
        $recordsFiltered = Model_building_structure::searchTotalCount($term, ['structure_code_bus','description_bus']);

        $resultArray = array();
        $list = array();

        foreach ($records as $row)
        {
                $list[] = array(
                    "id" => $row->id_bus,
                    "text" => $row->structure_code_bus.' - '.$row->description_bus,
                    "structure_code" => $row->structure_code_bus,
                    "description" => $row->description_bus,
                    "unit_of_measurement" => $row->unit_of_measurement_bus,
                );
        }
        $moreResults = ($page * $limit) < $recordsFiltered;
        // dd($page, $limit, $recordsFiltered);
        $resultArray['list'] = $list;
        $resultArray['pagination'] = array("more" => $moreResults);
        echo json_encode($resultArray);exit;
    }

    public function getByIdFromV2($buildingStructureId)
    {
        $client = new Client(['base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL')]);
        $apiResponse = $client->request('GET', 'api/v1/building-structures/'.$buildingStructureId);
        $arrayResponse = json_decode($apiResponse->getBody(),true);
        echo json_encode($arrayResponse);exit;
    }
}
