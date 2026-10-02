<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 10/1/2018
 * Time: 2:01 PM
 */

use GuzzleHttp\Client;

class AjaxProject extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
        if(! $this->input->is_ajax_request())
        {
            redirect('404');
        }
//        $this->_validateFeature("project_index");
    }

    public function ajaxDtAllProjects()
    {
        $additionalParameters = $this->input->post('additionalParameters') ?? [];
        $dt = new JqdtHandler($this->input->post());
    
        // ── Construir query params para la API de Serebo2 ────────────────────
        $queryParams = [
            'per_page' => $dt->getLength(),
            'page'     => $dt->getStart() > 0 ? (int)($dt->getStart() / $dt->getLength()) + 1 : 1,
            'order_by' => $dt->getOrderName(0) ?: 'entry_date_pro',
            'order_type' => $dt->getOrderDir(0) ?: 'desc',
        ];
    
        // Búsqueda de texto
        if ($dt->hasSearchValue())
        {
            $queryParams['search'] = $dt->getSearchValue();
        }
    
        // Mapear additionalParameters de Serebo → query params de Serebo2
        $paramMap = PrivateController::serebo2ApiParamNames();
    
        foreach ($paramMap as $serebroKey => $serebo2Key)
        {
            if (isset($additionalParameters[$serebroKey]) && $additionalParameters[$serebroKey] !== '')
            {
                $queryParams[$serebo2Key] = $additionalParameters[$serebroKey];
            }
        }
    
        // Listas de códigos e IDs — las pasamos como parámetros especiales
        if (isset($additionalParameters['code-list']) && $additionalParameters['code-list'] !== '')
        {
            $queryParams['code_list'] = $additionalParameters['code-list'];
        }
        if (isset($additionalParameters['id-list']) && $additionalParameters['id-list'] !== '')
        {
            $queryParams['id_list'] = $additionalParameters['id-list'];
        }
    
        // ── Llamar a la API de Serebo2 ────────────────────────────────────────
        $apiUrl  = getenv('SISTEMA_CHIQUITANOV2_URL') . '/api/v1/workflows?' . http_build_query($queryParams);
        $response = $this->_callSerebo2Api($apiUrl);

        if (!$response['success'])
        {
            // Si la API falla, devolvemos un DataTables vacío con error
            echo $dt->getJsonResponse(0, 0, []);
            exit;
        }
    
        $apiData         = $response['data'];
        $recordsTotal    = $apiData['meta']['total']        ?? 0;
        $recordsFiltered = $apiData['meta']['total']        ?? 0;
        $rows            = $apiData['data']                 ?? [];
    
        // ── Devolver en formato DataTables ────────────────────────────────────
        echo $dt->getJsonResponse($recordsTotal, $recordsFiltered, $rows);
        exit;
    }
    
    /**
     * Realiza una llamada GET a la API de Serebo2 via cURL.
     *
     * @param  string $url  URL completa con query params
     * @return array        ['success' => bool, 'data' => array]
     */
    private function _callSerebo2Api(string $url) : array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
        ]);
    
        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError    = curl_error($ch);
        curl_close($ch);
        // echo var_dump($responseBody);exit;
        if ($curlError || $httpCode !== 200)
        {
            log_message('error', "Serebo2 API error [{$httpCode}]: {$curlError} — URL: {$url}");
            return ['success' => false, 'data' => []];
        }
    
        $decoded = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE)
        {
            log_message('error', "Serebo2 API JSON decode error — URL: {$url}");
            return ['success' => false, 'data' => []];
        }
    
        return ['success' => true, 'data' => $decoded];
    }


	/**
	 * @deprecated
	 */
    public function getStakesLeaderProjects()
    {
        $stakesLeaderProject = Model_project::getStakesLeaderProjects();
        $arrayStakes = array();
        $singleList = array();
        for ($i = 0; $i < count($stakesLeaderProject); $i++)
        {
            $stakeLeaderId = $stakesLeaderProject[$i]["id_stl"];
            $singleList[] = $stakesLeaderProject[$i];
            if(isset($stakesLeaderProject[$i+1]))
            {
                if($stakesLeaderProject[$i]["id_stl"] != $stakesLeaderProject[$i+1]["id_stl"])
                {
                    $arrayStakes[$stakeLeaderId]['teamLeaderId'] = $stakesLeaderProject[$i]["id_stl"];
                    $arrayStakes[$stakeLeaderId]['teamLeader'] = $stakesLeaderProject[$i]["leader_stl"];
                    $arrayStakes[$stakeLeaderId]['projectList'] = $singleList;
                    $singleList = array();
                }
            }
            else
            {
                $arrayStakes[$stakeLeaderId]['teamLeaderId'] = $stakesLeaderProject[$i]["id_stl"];
                $arrayStakes[$stakeLeaderId]['teamLeader'] = $stakesLeaderProject[$i]["leader_stl"];
                $arrayStakes[$stakeLeaderId]['projectList'] = $singleList;
            }
        }
        $list[] = $arrayStakes;
        echo json_encode($arrayStakes);exit;
    }

    public function updateStakesLeaderProjects()
    {
        $formData = $this->input->post();
        $leaderId = $formData["leaderId"];
        $projectId = $formData["projectId"];
//        var_dump($leaderId, $projectId);exit;
        $projectStakes = Model_project_stakes::getByLeaderIdAndProjectId($leaderId, $projectId);
        if($projectStakes instanceof Model_project_stakes)
        {

        }
    }

    public function select2ProjectsThatReturnedMaterials()
    {
        $currentIds = array();//$this->input->post("currentIds");
        $term = $this->input->post("term");
        $limit = $this->input->post("limit");
        $page = $this->input->post("page");
        $offset = ($page-1)*$limit;
        $additionalParameters['status'] = "39,12";
        $projects = Model_project::search($term, $limit, $offset, 'code_pro', 'asc', array('code_pro'), $additionalParameters);
        $recordsFiltered = Model_project::searchTotalCount($term, array('code_pro'), $additionalParameters);

        $resultArray = array();
        $list = array();

        foreach ($projects as $project)
        {
            if(array_search($project->id_pro,$currentIds) === FALSE)
            {
                $list[] = array(
                    "id" => $project->id_pro,
                    "text" => $project->code_pro,
                    // "responsible" => $project->responsible,
                    "points" => $project->points_pro,
                    "distance" => $project->distance_pro
                );
            }
        }
        $moreResults = ($page * $limit) < $recordsFiltered;
        $resultArray['list'] = $list;
        $resultArray['pagination'] = array("more" => $moreResults);
        echo json_encode($resultArray);exit;
    }

    public function getTotalProjects()
    {
        $apiResponse = WorkflowApiClient::getPaginated(['per_page' => 1, 'page' => 1]);
    
        $recordsTotal = $apiResponse['meta']['total'] ?? 0;
    
        $response = ['total' => $recordsTotal];
        echo json_encode($response);
        exit;
    }

    public function getManpower(int $projectId)
    {
        $laborCostMasterDetail = Model_labor_cost::getMasterDetailByProjectId($projectId);
        $i = 0;
        foreach($laborCostMasterDetail as &$laborCost)
        {
            $i++;
            $laborCost['index'] = $i;
            $laborCost['quantity'] = number_format($laborCost['quantity'], 2);
            $laborCost['unit_price'] = number_format($laborCost['unit_price'], 2);
            $laborCost['total_price_by_structure'] = number_format($laborCost['total_price_by_structure'], 2);
            $laborCost['worked_up'] = number_format($laborCost['worked_up'], 2);
            $laborCost['diff'] = number_format($laborCost['diff'], 2);
        }
        if(count($laborCostMasterDetail) > 0)
        {
            $data['isSuperAdmin'] = $this->_is('super_admin');
            $result['success'] = 1;
            $result['message'] = '';
            $result['data']['template'] = $this->loadView('panel/content/project/ManpowerHandler', $data, TRUE);
            $result['data']['templateName'] = "#ht-manpower-table";
            $result['data']['laborCostMasterDetail'] = $laborCostMasterDetail;
        }
        else
        {
            $result['success'] = 0;
            $result['message'] = 'No se encontraron datos';
            $result['data']['laborCostMasterDetail'] = array();
        }
        echo json_encode($result);exit;
    }

    public function addManpowerProgress($projectId = NULL)
    {
        //        $this->_validateFeature('qb_create_invoice');
        $this->_validateObjectToEdit($projectId,"Model_project","panel/Home");
        /** Server Side Validations **/
        $this->form_validation->set_rules('entry-date', 'Fecha', 'trim|required');
        $this->form_validation->set_rules('detail', 'Detalle', 'trim');

        if($this->form_validation->run() === FALSE)
        {
            $validationErrors = validation_errors();
            $validationErrors = str_replace("<p>","",$validationErrors);
            $validationErrors = str_replace("</p>","<br>",$validationErrors);
            $response = array("success" => 0, "message" => $validationErrors);
            $success = $validationErrors != ""?0:1;
            $response["success"] = $success;
            $response["message"] = $validationErrors;
            $template = $this->loadView('panel/content/project/ManpowerHandler', array(), TRUE);
            $laborCostMasterDetail = Model_labor_cost::getMasterDetailByProjectId($projectId);
            $totalLaborCostMasterDetail = array_sum(array_map('floatval', array_column($laborCostMasterDetail, 'total_price_by_structure')));
            $i = 0;
            foreach($laborCostMasterDetail as &$laborCost)
            {
                $i++;
                $laborCost['index'] = $i;
                $laborCost['quantity'] = number_format($laborCost['quantity'], 2);
                $laborCost['unit_price'] = number_format($laborCost['unit_price'], 2);
                $laborCost['total_price_by_structure'] = number_format($laborCost['total_price_by_structure'], 2);
            }
			$dateRangesToBlock = Model_blocked_log_date_range::getAll(100, 0);
            $builders = Model_user::getByRoleKeyword('builder');
            $arrayBuilder = array();
            foreach($builders as $builder)
            {
                $builder = $builder->toArray();
                $arrayBuilder[] = array(
                    'id' => $builder['id_usr'],
                    'firstName' => $builder['firstname_usr'],
                    'lastName' => $builder['lastname_usr']
                );
            }
            $fiscals = Model_user::getByRoleKeyword('fiscal');
            $arrayFiscal = array();
            foreach($fiscals as $fiscal)
            {
                $fiscal = $fiscal->toArray();
                $arrayFiscal[] = array(
                    'id' => $fiscal['id_usr'],
                    'firstName' => $fiscal['firstname_usr'],
                    'lastName' => $fiscal['lastname_usr']
                );
            }
            
            $productionLimit = Model_production_limit::getByProjectId($projectId);
            if(!$productionLimit instanceof Model_production_limit)
            {
                $productionLimit = new Model_production_limit($projectId, 110, date('Y-m-d H:i:s'), null);
                $productionLimit->save();
            }
            $project = WorkflowApiClient::getOne($projectId) ?? [];

            $response["data"]["laborCostMasterDetail"] = $laborCostMasterDetail;
            $response['data']['showAddAllButton'] = $totalLaborCostMasterDetail <= 15000;
            $response["data"]["builders"] = $arrayBuilder;
            $response["data"]["fiscals"] = $arrayFiscal;
            $response["data"]["template"] = $template;
            $response["data"]["templateName"] = "#ht-modal-form-add-manpower-progress";
            $response["data"]["dateRangesToBlock"] = $dateRangesToBlock;
            $response['data']['project'] = $project;
            $response['data']['productionLimit'] = $productionLimit->toArray();
        }
        else
        {
            $formData = $this->input->post();
            $manualEntryDate = $formData["entry-date"];
            $manualEntryDate = DateTime::createFromFormat('d-m-Y', $manualEntryDate);
            $manualEntryDate = date_format($manualEntryDate, 'Y-m-d');
            $manualEntryDate = $manualEntryDate." ".date("H:i:s");
            $detail = $formData["detail"];
            $workedUp = $formData["worked-up"];
            $builders = $formData["builders"];
            $fiscalId = $formData['fiscal'];
            $pointId = !isset($formData["point-id"])?NULL:$formData["point-id"];
            $userId = $this->sessionUser->id;
            Model_labor_cost_log::addLog($fiscalId, $detail, $manualEntryDate, $workedUp, $builders);
            WorkflowSyncNotifier::notify($projectId);
            $response["success"] = 1;
            $response["message"] = "Avance registrado correctamente.";
        }
        echo json_encode($response);exit;
    }

    public function addPointToPointProgress($projectId = NULL, $pointId = NULL)
    {
        //        $this->_validateFeature('qb_create_invoice');
        $this->_validateObjectToEdit($projectId,"Model_project","panel/Home");
        $this->_validateObjectToEdit($pointId,"Model_building_point","panel/Home");
        /** Server Side Validations **/
        $this->form_validation->set_rules('entry-date', 'Fecha', 'trim|required');
        $this->form_validation->set_rules('detail', 'Detalle', 'trim');

        if($this->form_validation->run() === FALSE)
        {
            $validationErrors = validation_errors();
            $validationErrors = str_replace("<p>","",$validationErrors);
            $validationErrors = str_replace("</p>","<br>",$validationErrors);
            $response = array("success" => 0, "message" => $validationErrors);
            $success = $validationErrors != ""?0:1;
            $response["success"] = $success;
            $response["message"] = $validationErrors;
            $template = $this->loadView('panel/content/project/ManpowerHandler', array(), TRUE);
            $laborCostMasterDetail = Model_labor_cost::getMasterDetailByProjectId($projectId);
            $i = 0;
            foreach($laborCostMasterDetail as &$laborCost)
            {
                $i++;
                $laborCost['index'] = $i;
                $laborCost['quantity'] = number_format($laborCost['quantity'], 2);
                $laborCost['unit_price'] = number_format($laborCost['unit_price'], 2);
                $laborCost['total_price_by_structure'] = number_format($laborCost['total_price_by_structure'], 2);
            }
            $builders = Model_user::getByRoleKeyword('builder');
            $arrayBuilder = array();
            foreach($builders as $builder)
            {
                $builder = $builder->toArray();
                $arrayBuilder[] = array(
                    'id' => $builder['id_usr'],
                    'firstName' => $builder['firstname_usr'],
                    'lastName' => $builder['lastname_usr']
                );
            }
			$dateRangesToBlock = Model_blocked_log_date_range::getAll(100, 0);
            $buildingPoints = Model_building_point::getMasterDetail($projectId, $pointId);

            $productionLimit = Model_production_limit::getByProjectId($projectId);
            if(!$productionLimit instanceof Model_production_limit)
            {
                $productionLimit = new Model_production_limit($projectId, 110, date('Y-m-d H:i:s'), null);
                $productionLimit->save();
            }
            $project = WorkflowApiClient::getOne($projectId) ?? [];

            $response["data"]["project"] = $project;
            $response['data']['productionLimit'] = $productionLimit->toArray();
            $response["data"]["laborCostMasterDetail"] = $laborCostMasterDetail;
            $response["data"]["builders"] = $arrayBuilder;
            $response["data"]["template"] = $template;
            $response["data"]["point"] = array_values($buildingPoints)[0];
            $response["data"]["structuresToUse"] = array_values($buildingPoints[$pointId]["structures"]);
            $response["data"]["templateName"] = "#ht-modal-form-add-point-to-point-progress";
            $response["data"]["dateRangesToBlock"] = $dateRangesToBlock;
        }
        else
        {
            $formData = $this->input->post();
            $manualEntryDate = $formData["entry-date"];
            $manualEntryDate = DateTime::createFromFormat('d-m-Y', $manualEntryDate);
            $manualEntryDate = date_format($manualEntryDate, 'Y-m-d');
            $manualEntryDate = $manualEntryDate." ".date("H:i:s");
            $detail = $formData["detail"];
            $workedUp = $formData["worked-up"];
            $builders = $formData["builders"];
            $userId = $this->sessionUser->id;            
            Model_labor_cost_log::addLog($userId, $detail, $manualEntryDate, $workedUp, $builders, $pointId);
            $response["success"] = 1;
            $response["message"] = "Avance registrado correctamente.";
        }
        echo json_encode($response);exit;
    }

    public function addMassivePointToPointProgress($projectId = NULL)
    {
        //        $this->_validateFeature('qb_create_invoice');
        $this->_validateObjectToEdit($projectId,"Model_project","panel/Home");
        /** Server Side Validations **/
        $this->form_validation->set_rules('entry-date', 'Fecha', 'trim|required');
        $this->form_validation->set_rules('detail', 'Detalle', 'trim');

        if($this->form_validation->run() === FALSE)
        {
            $validationErrors = validation_errors();
            $validationErrors = str_replace("<p>","",$validationErrors);
            $validationErrors = str_replace("</p>","<br>",$validationErrors);
            $response = array("success" => 0, "message" => $validationErrors);
            $success = $validationErrors != ""?0:1;
            $response["success"] = $success;
            $response["message"] = $validationErrors;
            $template = $this->loadView('panel/content/project/ManpowerHandler', array(), TRUE);
            $laborCostMasterDetail = Model_labor_cost::getMasterDetailByProjectId($projectId);
            $i = 0;
            foreach($laborCostMasterDetail as &$laborCost)
            {
                $i++;
                $laborCost['index'] = $i;
                $laborCost['quantity'] = number_format($laborCost['quantity'], 2);
                $laborCost['unit_price'] = number_format($laborCost['unit_price'], 2);
                $laborCost['total_price_by_structure'] = number_format($laborCost['total_price_by_structure'], 2);
            }
//            $builders = Model_user::getBySupervisingUserId($this->sessionUser->id);
            $builders = Model_user::getByRoleKeyword('builder');
            $arrayBuilder = array();
            foreach($builders as $builder)
            {
                $builder = $builder->toArray();
                $arrayBuilder[] = array(
                    'id' => $builder['id_usr'],
                    'firstName' => $builder['firstname_usr'],
                    'lastName' => $builder['lastname_usr']
                );
            }
            $buildingPoints = Model_building_point::getMasterDetail($projectId);
            $dateRangesToBlock = Model_blocked_log_date_range::getAll(100, 0);
            $response["data"]["laborCostMasterDetail"] = $laborCostMasterDetail;
            $response["data"]["builders"] = $arrayBuilder;
            $response["data"]["template"] = $template;
            $response["data"]["points"] = array_values($buildingPoints);
            $response["data"]["templateName"] = "#ht-modal-form-add-several-point-to-point-progress";
            $response["data"]["dateRangesToBlock"] = $dateRangesToBlock;
        }
        else
        {
            $formData = $this->input->post();
            // echo"<pre>";var_dump($formData, $buildingPoints);exit;
            $manualEntryDate = $formData["entry-date"];
            $manualEntryDate = DateTime::createFromFormat('d-m-Y', $manualEntryDate);
            $manualEntryDate = date_format($manualEntryDate, 'Y-m-d');
            $manualEntryDate = $manualEntryDate." ".date("H:i:s");
            $detail = $formData["detail"];
            $builders = $formData["builders"];
            $pointsToFinish = $formData["points-to-finish"];
            $userId = $this->sessionUser->id;            
            $response = Model_labor_cost_log::addMassiveLog($userId, $detail, $manualEntryDate, $builders, $pointsToFinish, $projectId);
        }
        echo json_encode($response);exit;
    }

    public function getManpowerLog($projectId)
    {
        $arrayLog = Model_labor_cost_log::prepareArrayLog($projectId);
        $response['success'] = 1;
        $response['message'] = '';
        $response['data']['log'] = array_values($arrayLog);
        $response['data']['template'] = $this->loadView('panel/content/project/ManpowerHandler', array(), TRUE);
        $response['data']['templateName'] = "#ht-manpower-quick-log";
        echo json_encode($response);exit;
    }

    public function getBuildingPoints($projectId)
    {
        $buildingPoints = Model_building_point::getMasterDetail($projectId);
        $i = 0;
        $data['isSuperAdmin'] = $this->_is('super_admin');
        foreach($buildingPoints as &$point)
        {
            $i++;
            $point['index'] = $i;
        }
        if(count($buildingPoints) > 0)
        {
            
            $result['success'] = 1;
            $result['message'] = '';
            $result['data']['template'] = $this->loadView('panel/content/project/ManpowerHandler', $data, TRUE);
            $result['data']['templateName'] = "#ht-building-points";
            $result['data']['buildingPoints'] = array_values($buildingPoints);
        }
        else
        {
            $result['success'] = 0;
            $result['message'] = 'No se encontraron datos';
            $result['data']['template'] = $this->loadView('panel/content/project/ManpowerHandler', $data, TRUE);
            $result['data']['templateName'] = "#ht-building-points";
            $result['data']['buildingPoints'] = array();
        }
        echo json_encode($result);exit;
    }

    public function getByCodeList()
    {
        $this->_validateFeature('project_quick_search');
        $formData = $this->input->post();
        $codeList = $formData['codeList'];
        $codeList = explode(" ", $codeList);
        $projectList = Model_project::getByCodeList($codeList);
        $projectIds = array_keys($projectList);
        $projectData = array();
        foreach ($projectList as $row) 
        {
            $row = $row->toArray();
            $projectData[] = array(
                "id" => $row["id_pro"],
                "status" => $row["status_pro"],
                'code' => $row['code_pro']
            );
        }
        $result['success'] = 1;
        $result['message'] = '';
        $result['data']['projectList'] = $projectData;
        echo json_encode($result);exit;
    }

    public function select2()
    {
        $term = $this->input->post("term");
        $limit = $this->input->post("limit");
        $page = $this->input->post("page");
        $currentIds = $this->input->post("currentIds");
        $currentIds = array_filter($currentIds);
        $offset = ($page-1)*$limit;
        $records = Model_project::search($term, $limit, $offset, 'code_pro', 'asc', array('code_pro'));
        $recordsFiltered = Model_project::searchTotalCount($term, array('code_pro'));

        $resultArray = array();
        $list = array();

        foreach ($records as $row)
        {
            if(array_search($row->id_pro,$currentIds) === FALSE)
            {
                $list[] = array(
                    "id" => $row->id_pro,
                    "text" => $row->code_pro,
                    "address"=> $row->address_pro,
                    "responsible" => $row->responsible,
                    "points" => $row->points_pro,
                    "distance" => $row->distance_pro,
                    "status" => $row->status_name_pst
                );
            }
        }
        $moreResults = ($page * $limit) < ($recordsFiltered - count($currentIds));
        $resultArray['list'] = $list;
        $resultArray['pagination'] = array("more" => $moreResults);
        echo json_encode($resultArray);exit;

    }

    public function projectQuickSelect2()
    {
        $term = $this->input->post("term");
        $limit = $this->input->post("limit");
        $page = $this->input->post("page");
        $data = Model_project::projectQuickSelect2($term, $limit, $page);
        echo json_encode($data);exit;
    }

    /**
     * @deprecated
     */
    public function paginationJs_old()
    {
        $formData = $this->input->post();
        $pageSize = $formData['pageSize'];
        $pageNumber = $formData['pageNumber'] == 1?($formData['pageNumber'] - 1):(($formData['pageNumber']-1)*20)+1;
        $textToSearch = isset($formData['textToSearch'])?$formData['textToSearch']:"";
        $additionalParameters = isset($formData["additionalParameters"])?$formData["additionalParameters"]:array();
        $additionalParameters["has-location"] = 1;
        $response = $this->_is("fiscal");
        if($response == 1)
        {
            $additionalParameters["fiscal-responsible-id"] = $this->sessionUser->id;
        }
        $recordsTotal = Model_project::countAll($additionalParameters);
        $recordsFiltered = $recordsTotal;
        if ($textToSearch == "")
        {
            $resultArray = Model_project::getAll($pageSize, $pageNumber, NULL, "asc", $additionalParameters);
        }
        else
        {   
            $resultArray = Model_project::search($textToSearch, $pageSize, $pageNumber, NULL, "asc", array("code_pro"), $additionalParameters);
            $recordsFiltered = Model_project::searchTotalCount($textToSearch, array("code_pro"), $additionalParameters);
            
        }
        $response = array();
        $response['recordsTotal'] = $recordsTotal;
        $response['recordsFiltered'] = $recordsFiltered;
        $response['resultArray'] = $resultArray;
        echo json_encode($response);exit;
    }

    /**
     * Used to paginated the locations view
     */
    public function paginationJs()
    {
        $formData   = $this->input->post();
        $pageSize   = (int) $formData['pageSize'];
        $pageNumber = (int) $formData['pageNumber'];
    
        // Same logic as before (page 1 -> offset 0, otherwise standard
        // offset math), but using the real $pageSize instead of a
        // hardcoded 20 — keeps working today since the front-end always
        // sends pageSize=20, but won't silently break if that ever changes.
        $offset = $pageNumber == 1 ? 0 : (($pageNumber - 1) * $pageSize);
    
        $textToSearch = $formData['textToSearch'] ?? "";
        $additionalParameters = $formData["additionalParameters"] ?? [];
        $additionalParameters["has-location"] = 1;
    
        $queryParams = [
            'per_page'   => $pageSize,
            'page'       => $pageNumber,
            'order_by'   => 'code_pro',
            'order_type' => 'asc',
        ];
    
        if ($textToSearch !== "")
        {
            $queryParams['search'] = $textToSearch;
        }
    
        $filterMap = PrivateController::serebo2ApiParamNames();
        foreach ($filterMap as $oldKey => $newKey)
        {
            if (isset($additionalParameters[$oldKey]) && $additionalParameters[$oldKey] !== '')
            {
                $queryParams[$newKey] = $additionalParameters[$oldKey];
            }
        }
    
        $apiResponse = WorkflowApiClient::getPaginated($queryParams);
    
        if ($apiResponse === null || !isset($apiResponse['data']))
        {
            $response = [
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'resultArray'     => [],
            ];
            echo json_encode($response);
            exit;
        }
    
        $recordsTotal = $apiResponse['meta']['total'] ?? 0;
    
        $response = [
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsTotal,
            'resultArray'     => $apiResponse['data'],
        ];
    
        echo json_encode($response);
        exit;
    }
}
