<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 10/1/2018
 * Time: 2:01 PM
 */

use GuzzleHttp\Client;

class AjaxLaborCost extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
        if(! $this->input->is_ajax_request())
        {
            redirect('404');
        }
    }

    public function ajaxDtAllLaborCost()
    {
        $dt = new JqdtHandler($this->input->post());
        $recordsTotal = Model_role::countAll();
        $recordsFiltered = $recordsTotal;
        if (!$dt->hasSearchValue())
        {
            $resultArray = Model_role::getAll($dt->getLength(), $dt->getStart(), $dt->getOrderName(0), $dt->getOrderDir(0));
        }
        else
        {
            $resultArray = Model_role::search($dt->getSearchValue(), $dt->getLength(), $dt->getStart(), $dt->getOrderName(0), $dt->getOrderDir(0), $dt->getSearchableColumnDefs());
            $recordsFiltered = Model_role::searchTotalCount($dt->getSearchValue(),$dt->getSearchableColumnDefs());
        }

        echo $dt->getJsonResponse($recordsTotal, $recordsFiltered, $resultArray);
        exit;
    }
    
    public function add(int $projectId)
    {
        //$this->_validateFeature('qb_create_invoice');
        /** Server Side Validations **/
        $this->form_validation->set_rules('structure-code', 'Codigo de estructura', 'trim|callback_validate_unique_structure_code');
        $this->form_validation->set_rules('structure-id', 'Structure ID ', 'trim|callback_validate_execution_and_activity['.$projectId.']');

        if ($this->form_validation->run() === FALSE) 
        {
            $validationErrors = validation_errors();
            $validationErrors = str_replace("<p>", "", $validationErrors);
            $validationErrors = str_replace("</p>", "<br>", $validationErrors);
            $response = array("success" => 0, "message" => $validationErrors);
            $success = $validationErrors != "" ? 0 : 1;
            $response["success"] = $success;
            $response["message"] = $validationErrors;
            $template = $this->loadView('panel/content/project/ManpowerHandler', array(), TRUE);
            $project = Model_project::getById($projectId);
            $projectArray = $project->toArray();
            $projectArray['managementBy'] = $this->_projectSystems[$projectArray['management_by_pro']];
            $response["data"]["template"] = $template;
            $response["data"]["templateName"] = "#ht-modal-form-add-labor-cost";
            $response["data"]["project"] = $projectArray;
        } 
        else
        {
            $formData = $this->input->post();
			$activityString = array('i' => 'instalacion','r' => 'retiro','m' => 'movimiento');
			$executionString = array('lv' => 'linea viva','lm' => 'linea muerta');
//            echo"<pre>";var_dump($formData);exit;
//            $currentLaborCost = Model_labor_cost::getMasterDetailByProjectId($projectId);
            /** @var Model_labor_detail $laborDetail */
            $laborDetail = Model_labor_detail::getByProjectId($projectId);
            $activity = $formData["activity"];
            $execution = $formData["execution"];
            $quantity = str_replace(",","", $formData["quantity"]);
            $unitPrice = str_replace(",","", $formData["price"]);
            //If the data to create a new structure is settled then this code block will be executed
			//This section is validate in callback to prevent duplicated structure code
            if(isset($formData["structure-code"]) && $formData["structure-code"] != "")
            {
                $structureCode = $formData["structure-code"];
				$detail = $formData["structure-detail"];
				$unitOfMeasurement = $formData["structure-unit-of-measurement"];
				$structure = new Model_building_structure($structureCode, $detail, $unitOfMeasurement);
				$structure->save();
            }
            //This section is validated in callback to prevent duplicated structure, activity and execution
            else
            {
                $structureId = $formData["structure-id"];
                if(isset($formData['change-quantity']))
                {
                    $laborCost = Model_labor_cost::getByProjectStructureExecutionActivity($projectId, $structureId, $execution, $activity);
                    $laborCostId = $laborCost[0]['id_lac'];
                    $quantityFrom = $laborCost[0]['quantity_lac'];
                    $quantityApplied = $quantity;
                    $currentUser = PrivateController::getSessionUser();
                    $userId = isset($currentUser) ? $currentUser->id:NULL;

                    $client = new Client(['base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL')]);
                    $apiResponse = $client->request('POST', 'api/v1/labor-cost-change-log',[
                        'form_params' => [
                            "labor_cost_id" => $laborCostId,
                            "user_id" => $userId,
                            "quantity_applied" => $quantityApplied,
                            "quantity_from" => $quantityFrom
                            
                        ]
                    ]);
                    // $body = json_decode($apiResponse->getBody(), true);
                    // dd($body);
                    // $settings = $body['data'];
                    
                    $laborCost = Model_labor_cost::getById($laborCostId);
                    $laborCost->setQuantity( ($quantityFrom) +($quantityApplied) );
                    $laborCost->save();

                    $structure = Model_building_structure::getById($structureId);

                    $response["success"] = 1;
                    $response["message"] = "Se modific&oacute; la cantidad a la esctructura ".$structure->getCode()." para ".$activityString[strtolower($activity)]." en ".$executionString[strtolower($execution)];
                    $response["data"]["laborCost"] = $laborCost->toArray();
                    $response["data"]["structure"] = $structure->toArray();
                    
                }
                else
                {
                    /** @var Model_building_structure $structure */
                    $structure = Model_building_structure::getById($structureId);
                    $laborCost = new Model_labor_cost($laborDetail->getId(), $structure->getId(), $activity, $execution, $quantity, $unitPrice, 1);
                    $laborCost->save();
    
                    $response["success"] = 1;
                    $response["message"] = "La estructura ".$structure->getCode()." se agreg&oacute; para ".$activityString[strtolower($activity)]." en ".$executionString[strtolower($execution)];
                    $response["data"]["laborCost"] = $laborCost->toArray();
                    $response["data"]["structure"] = $structure->toArray();
                }
            }
            WorkflowSyncNotifier::notify($projectId);
        }
        echo json_encode($response);
        exit;
    }

	public function validate_unique_structure_code()
	{
		$formData = $this->input->post();
		$structureCode = !isset($formData["structure-code"])?"":$formData["structure-code"];
		$response = TRUE;
		if($structureCode != "")
		{
			$structure = Model_building_structure::getByCode($structureCode);
			//If the structure code already exist then trow a error message.
			if($structure instanceof Model_building_structure)
			{
				$this->form_validation->set_message('validate_unique_structure_code', "La estructura ".strtoupper($structureCode)." ya esta registrado en el sistema.");
				$response = FALSE;
			}
		}

		return $response;
	}

	/**
	 * If the user choose an existing structure then let's check if the activity and execution isn't in current manpower
	 * @param $structureId
	 * @param $projectId
	 * @return bool
	 */
	public function validate_execution_and_activity($structureId, $projectId)
	{
		$formData = $this->input->post();
		$activityString = array('i' => 'instalacion','r' => 'retiro','m' => 'movimiento');
		$executionString = array('lv' => 'linea viva','lm' => 'linea muerta');
		$incomingActivity = $formData['activity'];
		$incomingExecution = $formData['execution'];
		$laborCostList = Model_labor_cost::getMasterDetailByProjectId($projectId);
		$incomingLaborCostUnique = $structureId.'-'.$incomingActivity.'-'.$incomingExecution;
		$laborCostFound = array_search($incomingLaborCostUnique, array_column($laborCostList,'labor_cost_unique'));
		$response = TRUE;
		//If it is distinct to FALSE then the labor cost already exist in manpower
		if($laborCostFound !== FALSE && !isset($formData['change-quantity']))
		{
			$laborCost = $laborCostList[$laborCostFound];
			$structureCode = $laborCost['structure_code'];
			$activity = $activityString[strtolower($laborCost['activity'])];
			$execution = $executionString[strtolower($laborCost['execution'])];
			$this->form_validation->set_message('validate_execution_and_activity', "La estructura ".$structureCode." ya esta registrado para ".$activity." en ".$execution.". Revise el item #".($laborCostFound+1));
			$response = FALSE;
		}

		return $response;
	}

    public function select2()
    {
        $term = $this->input->post("term");
        $limit = $this->input->post("limit");
        $page = $this->input->post("page");
        $budgetaryPosition = $this->input->post("budgetaryPosition");
        $managementBy = $this->input->post("management");
        $projectId = $this->input->post("projectId");
        $additionalParameters = array(
        	"budgetary-position" => $budgetaryPosition,
        	"management-by"	=> $managementBy,
			"project-id" => $projectId
		);
        $offset = ($page-1)*$limit;
        $records = Model_labor_cost::searchLaborCost($term, $limit, $offset, NULL, 'desc', array('structure_code_bus','description_bus', 'code_pro'), $additionalParameters);
        $recordsFiltered = Model_labor_cost::searchTotalCountLaborCost($term, array('structure_code_bus','description_bus', 'code_pro'), $additionalParameters);

        $resultArray = array();
        $list = array();

        foreach ($records as $row)
        {
                $list[] = array(
                    "id" => $row->id_bus,
                    "text" => $row->structure_code_bus,
                    "labor_cost_id" => $row->id_lac,
                    "structure_code" => $row->structure_code_bus,
                    "structure_detail" => $row->description_bus,
                    "structure_unit_price" => $row->unit_price_lac,
                    "structure_activity" => $row->activity_lac,
                    "structure_execution" => $row->execution_lac,
                    "structure_quantity" => $row->quantity_lac,
					"structure_unit_of_measurement" => $row->unit_of_measurement_bus,
					"management_by" => $row->management_by_pro,
                    "budgetary_position" => $row->budgetary_position_pro,

                    "project_code" => $row->code_pro

                );
        }
        $moreResults = ($page * $limit) < $recordsFiltered;
        $resultArray['list'] = $list;
        $resultArray['pagination'] = array("more" => $moreResults);
        echo json_encode($resultArray);exit;

    }

    public function getByIdFromV2($laborCostId)
    {
        $client = new Client(['base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL')]);
        $apiResponse = $client->request('GET', 'api/v1/labor-costs/'.$laborCostId);
        $arrayResponse = json_decode($apiResponse->getBody(),true);
        echo json_encode($arrayResponse);exit;
    }
}
