<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 04/06/2018
 * Time: 10:34 AM
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use GuzzleHttp\Client;

class Project extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
//        $this->_validateFeature('project_index');
        $this->complementHandler->addViewComplement("bootbox");
        $this->complementHandler->addViewComplement("jquery.datatables");
        $this->complementHandler->addViewComplement("jquery.datatables.bootstrap");
        $this->complementHandler->addViewComplement("jquery.datatables.buttons");
        $this->complementHandler->addViewComplement("jquery.datatables.buttons.bootstrap");
        $this->complementHandler->addViewComplement("jquery.datatables.buttons.flash");
        $this->complementHandler->addViewComplement("jquery.datatables.buttons.html5");
        $this->complementHandler->addViewComplement("jquery.datatables.buttons.print");
        $this->complementHandler->addViewComplement("jquery.datatables.jszip");
        $this->complementHandler->addViewComplement("jquery.datatables.pdfmake");
        $this->complementHandler->addViewComplement("jquery.datatables.vfs_fonts");
        $this->complementHandler->addViewComplement("jquery.datatables.filterdelay");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("parsley.spanish");
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addProjectJs('DTAdditionalParameterHandler', TRUE);
        $this->complementHandler->addProjectCss('project.index',TRUE);
        $this->complementHandler->addProjectJs('project.index',TRUE);
        $data["viewTitle"] = "Lista de proyectos";
        $data["status"] = '';//todos los estados
        $data["statusSet"] = "none";
        $data["projectSystems"] = $this->_projectSystems;
        $data['fiscalList'] = Model_user::getByRoleKeyword('fiscal');
        $data['builderList'] = Model_user::getByRoleKeyword('builder');
		$data['showEditButton'] = $this->_validateFeature('project_edit', TRUE);
		$data['showDeleteButton'] = $this->_validateFeature('delete_project', TRUE);
        // echo"<pre>";var_dump($data['builderList']);exit;
        $projectStatus = Model_project_status::getAll(100,0);
        $statusInLog = Model_project_status::getAllInLog();
        $arrayStatus = array();
        foreach ($projectStatus as $status)
        {
            $status = (array)$status;
            $arrayStatus[$status['id_pst']] = $status["status_name_pst"];
        }
        $data["projectStatusJson"] = json_encode($arrayStatus);
        $data["statusInLog"] = $statusInLog;
        $this->_loadPanelView("project/index", $data);
    }

    public function add()
    {
        $this->_validateFeature('project_add');

        /** View complements */
        $this->complementHandler->addViewComplement('select2');
        $this->complementHandler->addViewComplement("jquery.inputmask.bundle");
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("parsley.spanish");
        $this->complementHandler->addViewComplement("google.maps.api");
        // $this->complementHandler->addViewComplement("gmaps");
        // $this->complementHandler->addProjectJs('gmaps-script-handler');
        $this->complementHandler->addProjectJs('MapsHandler',TRUE);
        $this->complementHandler->addProjectCss('project.add', TRUE);
        $this->complementHandler->addProjectJs('project.add', TRUE);

        /** Server Side Validations **/
        $this->form_validation->set_rules('project-code', 'Codigo del proyecto', 'trim|required|callback_validate_code');
        $this->form_validation->set_rules('project-name', 'Nombre del proyecto', 'trim');
        $this->form_validation->set_rules('project-entry-date', 'Nombre del proyecto', 'trim|required');
        $this->form_validation->set_rules('project-folder-date', 'Fecha de folder', 'trim|required');
        $this->form_validation->set_rules('project-cre-fiscal', 'Fiscal de CRE', 'trim|required');
        $this->form_validation->set_rules('project-system', 'Sistema', 'trim|required');
        $this->form_validation->set_rules('project-address', 'Direccion/Ubicacion', 'trim|required');
        $this->form_validation->set_rules('project-points', 'Cantidad de puntos', 'trim|required|numeric');
        $this->form_validation->set_rules('project-meters-distance', 'Metros de distancia', 'trim|required|numeric');
        $this->form_validation->set_rules('project-status', 'Estado', 'trim|numeric');
        $this->form_validation->set_rules('project-budgetary-position', 'Posicion presupuestaria', 'trim|numeric');
        $this->form_validation->set_rules('project-contract-id', 'Contract ID', 'trim|numeric');
        $this->form_validation->set_rules('project-detail', 'Detalle', 'trim|required');

        $projectStatusList = Model_project_status::getAll(100,0);
        $contractList = Model_contract::getAll(100, 0);
//        $creFiscalList = Model_cre_fiscal::getAll(100,0);
        $creFiscalList = Model_user::getByRoleKeyword("cre_fiscal");
        $data["projectStatusList"] = $projectStatusList;
        $data["projectSystems"] = $this->_projectSystems;
        $data["contractList"] = $contractList;
        $data["creFiscalList"] = $creFiscalList;

        if($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("project/add",$data);
        }
        else
        {
            $formData = $this->input->post();
            $projectInitialDesignBudget =  str_replace(',','',$formData['project-initial-design-budget']);
            $projectInitialBuildingBudget = str_replace(',','',$formData['project-initial-building-budget']);

            $projectCode = $formData["project-code"];
            $projectName = $formData["project-name"];

            $projectEntryDate = $formData["project-entry-date"];
            $projectEntryDate = DateTime::createFromFormat('d-m-Y', $projectEntryDate);
            $projectEntryDate = date_format($projectEntryDate, 'Y-m-d');
            $projectEntryDate = $projectEntryDate." ".date("H:i:s");

            $projectFolderDate = $formData["project-folder-date"];
            $projectFolderDate = DateTime::createFromFormat('d-m-Y', $projectFolderDate);
            $projectFolderDate = date_format($projectFolderDate, 'Y-m-d');
            $projectFolderDate = $projectFolderDate." ".date("H:i:s");

            $projectCreFiscal = $formData["project-cre-fiscal"];
            $projectSystem = $formData["project-system"];
            $projectAddress = $formData["project-address"];
            $projectPoints = $formData["project-points"];
            $projectMetersDistance = $formData["project-meters-distance"];
            $managementBy = $formData["management-by"];
            $qualityLevel = $formData["quality-level"];

            $creDesignCompletionDate = $formData["cre-design-completion-date"];
            $creDesignCompletionDate = DateTime::createFromFormat('d-m-Y', $creDesignCompletionDate);
            $creDesignCompletionDate = date_format($creDesignCompletionDate, 'Y-m-d');
            $creDesignCompletionDate = $creDesignCompletionDate." ".date("H:i:s");

            $creBuildingCompletionDate = $formData["cre-building-completion-date"];
            $creBuildingCompletionDate = DateTime::createFromFormat('d-m-Y', $creBuildingCompletionDate);
            $creBuildingCompletionDate = date_format($creBuildingCompletionDate, 'Y-m-d');
            $creBuildingCompletionDate = $creBuildingCompletionDate." ".date("H:i:s");

            $budgetaryPosition = $formData["project-budgetary-position"];
            $contractId = $formData["project-contract-id"];
            $detail = $formData["project-detail"];
            $latitude = $formData["latitude"];
            $longitude = $formData["longitude"];
            $workArea = $formData['work-area'];
            $projectYear = $formData['project-year'];

            $minorEnlargement = NULL;
            if(isset($formData['minor-enlargement']))
            {
                $minorEnlargement = 'AM';
            }

			//Let's search the status responsible
			$responsibleList = Model_status_responsible::getUsersResponsible("project_has_been_created", $this->sessionUser->id);
			//Removed old validation
            // if(count($responsibleList) <= 0)
			// {
			// 	$this->session->set_flashdata("errorMessage", "No esta habilitado como responsable para la creacion de proyectos");
			// 	redirect(base_url("panel/Project"));
			// }
            //Our first project status is 'project_has_been_created'
            $statusHasBeenCreated = "46";
            $project = new Model_project($projectCode, $projectName, $projectSystem, $projectAddress, $projectEntryDate, $projectCreFiscal, $statusHasBeenCreated,NULL,NULL,$projectPoints,$projectMetersDistance,
                $managementBy, $qualityLevel, $creDesignCompletionDate, $creBuildingCompletionDate, $budgetaryPosition, $projectCode, $projectFolderDate, $contractId,$detail,0,0,$latitude, $longitude, $workArea, $projectYear);
            $project->setInitialDesignBudget($projectInitialDesignBudget);
            $project->setInitialBuildingBudget($projectInitialBuildingBudget);
            $project->setEndContract($contractId);
            $project->setMinorEnlargement($minorEnlargement);
            $project->save();
            $responsibleList = $responsibleList[0];//array_column($responsibleList,'id_sre');
            $responsibleList = array($responsibleList['id_sre']);
            $project->savePoints($projectPoints, $projectMetersDistance, $statusHasBeenCreated,"El proyecto ha sido creado.", $projectEntryDate,$responsibleList);


            if(isset($formData["instant-approvement"]))
            {
                $entryDate = $formData["approved-entry-date"];
                $statusDetail = $formData["approved-detail"];
                $design = $formData["design-budget"];
                $building = $formData["building-budget"];
                $graphNumber = $formData["graph-number-budget"];
                $reservationNumber = $formData["reservation-number-budget"];
                $transportation = $formData["transportation-budget"];
                $liveLine = $formData["live-line-budget"];
                $rightOfWay = $formData["right-of-way-budget"];
                $secondaryCode = $formData["secondary-code"];
                $manpowerFileId = !isset($formData["manpower-file-id"]) || $formData["manpower-file-id"] == ''?NULL:$formData["manpower-file-id"];
                $project->approveThisProject($entryDate, $statusDetail, $design, $building, $graphNumber, $reservationNumber, $transportation, $liveLine, $rightOfWay, $secondaryCode, $manpowerFileId);
            }

            $this->session->set_flashdata("successMessage", "Proyecto agregado exitosamente!");
            WorkflowSyncNotifier::notify($project->getId());
            redirect(base_url("panel/Project"));
        }
    }

    public function edit($projectId = NULL)
    {
        $this->_validateFeature('project_edit');
        /** @var Model_project $project */
        $project = $this->_validateObjectToEdit($projectId,"Model_project","panel/Project");

        /** View complements */
        $this->complementHandler->addViewComplement('select2');
        $this->complementHandler->addViewComplement("jquery.inputmask.bundle");
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("google.maps.api");
        // $this->complementHandler->addViewComplement("gmaps");
        // $this->complementHandler->addProjectJs('gmaps-script-handler');
        $this->complementHandler->addProjectJs('MapsHandler',TRUE);
        $this->complementHandler->addProjectCss('project.edit',TRUE);
        $this->complementHandler->addProjectJs('project.edit', TRUE);

        /** Server Side Validations **/
        $this->form_validation->set_rules('project-code', 'Codigo del proyecto', 'trim|required|callback_validate_code');
        $this->form_validation->set_rules('project-secondary-code', 'Codigo del proyecto', 'trim|required|callback_validate_secondary_code');
        $this->form_validation->set_rules('project-folder-date', 'Fecha de folder', 'trim');
        $this->form_validation->set_rules('project-cre-fiscal', 'Fiscal', 'trim|required');
        $this->form_validation->set_rules('project-system', 'sistema', 'trim|required');
        $this->form_validation->set_rules('project-address', 'Direccion', 'trim');
        $this->form_validation->set_rules('project-status', 'Estado', 'trim|numeric');
        $this->form_validation->set_rules('project-budgetary-position', 'Posicion presupuestaria', 'trim|numeric');
        $this->form_validation->set_rules('project-contract-id', 'Contract ID', 'trim|numeric');
        $this->form_validation->set_rules('project-end-contract-id', 'End contract ID', 'trim|numeric');
        $this->form_validation->set_rules('project-detail', 'Detalle', 'trim|required');

        $getLastProjectStatus = Model_project_status_log::getLastProjectStatusLogByProjectId($project->getId());
//        $creFiscalList = Model_cre_fiscal::getAll(100,0);
        $creFiscalList = Model_user::getByRoleKeyword("cre_fiscal");
        $contractList = Model_contract::getAll(100, 0);
        $data["lastProjectStatus"] = $getLastProjectStatus;
        $projectStatusList = Model_project_status::getAll(100,0);
        $data["projectStatusList"] = $projectStatusList;
        $data["project"] = $project->toArray();
        $data["projectSystems"] = $this->_projectSystems;
        $data["contractList"] = $contractList;
        $data["creFiscalList"] = $creFiscalList;
        if($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("project/edit", $data);
        }
        else
        {
            $formData = $this->input->post();
            $projectInitialDesignBudget =  str_replace(',','',$formData['project-initial-design-budget']);
            $projectInitialBuildingBudget = str_replace(',','',$formData['project-initial-building-budget']);

            $projectCode = $formData["project-code"];
            $secondaryCode = $formData["project-secondary-code"];
            $projectName = $formData["project-name"];

            $entryDate = NULL;
            if($formData["project-entry-date"] != "")
            {
                $entryDate = $formData["project-entry-date"];
                $entryDate = DateTime::createFromFormat('d-m-Y', $entryDate);
                $entryDate = date_format($entryDate, 'Y-m-d');
                $entryDate = $entryDate." ".date("H:i:s");
            }
            
            $folderDate = NULL;
            if($formData["project-folder-date"] != "")
            {
                $folderDate = $formData["project-folder-date"];
                $folderDate = DateTime::createFromFormat('d-m-Y', $folderDate);
                $folderDate = date_format($folderDate, 'Y-m-d');
                $folderDate = $folderDate." ".date("H:i:s");
            }

            $projectCreFiscal = $formData["project-cre-fiscal"];
            $projectSystem = $formData["project-system"];
            $projectAddress = $formData["project-address"];
            $projectStatus = $formData["project-status"];
            $managementBy = $formData["management-by"];
            $qualityLevel = $formData["quality-level"];

            $creDesignCompletionDate = NULL;
            if($formData["cre-design-completion-date"] != "")
            {
                $creDesignCompletionDate = $formData["cre-design-completion-date"];
                $creDesignCompletionDate = DateTime::createFromFormat('d-m-Y', $creDesignCompletionDate);
                $creDesignCompletionDate = date_format($creDesignCompletionDate, 'Y-m-d');
                $creDesignCompletionDate = $creDesignCompletionDate." ".date("H:i:s");
            }

            $creBuildingCompletionDate = NULL;
            if($formData["cre-building-completion-date"] != "")
            {
                $creBuildingCompletionDate = $formData["cre-building-completion-date"];
                $creBuildingCompletionDate = DateTime::createFromFormat('d-m-Y', $creBuildingCompletionDate);
                $creBuildingCompletionDate = date_format($creBuildingCompletionDate, 'Y-m-d');
                $creBuildingCompletionDate = $creBuildingCompletionDate." ".date("H:i:s");
            }


            $budgetaryPosition = $formData["project-budgetary-position"];
            $contractId = $formData["project-contract-id"];
            $endContractId = $formData["project-end-contract-id"] == ""?NULL:$formData["project-end-contract-id"];
            $detail = $formData["project-detail"];
            $latitude = $formData["latitude"];
            $longitude = $formData["longitude"];
            $workArea = $formData['work-area'];
            $projectYear = $formData['project-year'];
//			echo "<pre>";var_dump($endContractId);exit();
            $project->setLatitude($latitude);
            $project->setLongitude($longitude);
            if($projectStatus != "")
            {
                $project->setStatus($projectStatus);
            }
            $project->setSecondaryCode($secondaryCode);
            $project->setFolderDate($folderDate);
            $project->setEntryDate($entryDate);
            $project->setCREFiscal($projectCreFiscal);
            $project->setSystem($projectSystem);
            $project->setAddress($projectAddress);
            $project->setManagementBy($managementBy);
            $project->setQualityLevel($qualityLevel);
            $project->setCreDesignCompletionDate($creDesignCompletionDate);
            $project->setCreBuildingCompletionDate($creBuildingCompletionDate);
            $project->setBudgetaryPosition($budgetaryPosition);
            $project->setContractId($contractId);
            $project->setEndContract($endContractId);
            $project->setDetail($detail);
            $project->setWorkArea($workArea);
            $project->setProjectYear($projectYear);
            $project->setInitialDesignBudget($projectInitialDesignBudget);
            $project->setInitialBuildingBudget($projectInitialBuildingBudget);
            $minorEnlargement = NULL;
            if(isset($formData['minor-enlargement']))
            {
                $minorEnlargement = 'AM';
            }
            $project->setMinorEnlargement($minorEnlargement);
            $project->save();
            //The status isn't empty when is send to design
            if($projectStatus != "")
            {
                $responsibleList = Model_status_responsible::getUsersResponsible("design");
                $responsibleList = $responsibleList[0];
                $responsibleList = array($responsibleList['id_sre']);
                $project->addStatusToLog($projectStatus,$detail = "Inicio de diseño del proyecto", date("Y-m-d H:i:s"), $responsibleList);
            }

            $this->session->set_flashdata("successMessage", "Proyecto editado correctamente!");
            // PrivateController::updateWorkflow([$project->getId()]);
            WorkflowSyncNotifier::notify($project->getId());
            redirect(base_url("panel/Project/edit/".$project->getId()));
        }
    }

    public function delete($projectId = NULL)
    {
        $this->_validateFeature("delete_project");
        $project = $this->_validateObjectToEdit($projectId,"Model_project","panel/Project");
        $project->delete();
        $this->session->set_flashdata("successMessage", "Proyecto eliminado!");
        redirect(base_url("panel/Project"));
    }

    public function test()
    {
        $a1=array(0 => 2, 1=>3);
        $a2=array(0 => 2, 1=>4);

        $result=array_diff($a1,$a2);
        print_r($result);exit;
    }

    public function validate_code()
    {
        $formData = $this->input->post();
        $projectId = isset($formData["project-id"])?$formData["project-id"]:"";
        $code = isset($formData["project-code"])?$formData["project-code"]:"";
        $isDuplicated = Model_project::projectCodeDuplicated($code, $projectId);
        $result = TRUE;
        if($isDuplicated)
        {
            $this->form_validation->set_message('validate_code', 'Ya existe un proyecto con el codigo '.$code);
            $result = FALSE;
        }
        return $result;
    }

	public function validate_entity_id($entityId)
	{
		$result = TRUE;
		if(!is_numeric($entityId))
		{
			$this-> form_validation->set_message('validate_entity_id', 'El campo {field} debe ser un identificador valido ');
			$result = FALSE;
		}
		return $result;
	}

    public function validate_secondary_code()
    {
        $formData = $this->input->post();
        $projectId = isset($formData["project-id"])?$formData["project-id"]:"";
        $code = isset($formData["project-secondary-code"])?$formData["project-secondary-code"]:"";
        $isDuplicated = Model_project::projectSecondaryCodeDuplicated($code, $projectId);
        $result = TRUE;
        if($isDuplicated)
        {
            $this->form_validation->set_message('validate_secondary_code', 'Ya existe un proyecto con ese codigo secundario '.$code);
            $result = FALSE;
        }
        return $result;
    }

    private function saveApproved()
    {
        $formData = $this->input->post();
        $projectId = $formData["projectId"];
        $entryDate = $formData["entryDate"];
        $entryDate = DateTime::createFromFormat('d-m-Y', $entryDate);
        $entryDate = date_format($entryDate, 'Y-m-d');
        $entryDate = $entryDate." ".date("H:i:s");
        $statusId = $formData["statusId"];
        $statusDetail = $formData["statusDetail"];
        $design = $formData["design"];
        $design = str_replace(",","",$design);
        $building = $formData["building"];
        $building = str_replace(",","",$building);
        $graphNumber = $formData["graphNumber"];
        $reservationNumber = $formData["reservationNumber"];
        $transportation = $formData["transportation"];
        $transportation = str_replace(",","",$transportation);
        $liveLine = $formData["liveLine"];
        $liveLine = str_replace(",","",$liveLine);
        $rightOfWay = $formData["rightOfWay"];
        $rightOfWay = str_replace(",","",$rightOfWay);
        $responsibleList = $formData["responsibleList"];
        /** @var Model_project $project */
        $project = Model_project::getById($projectId);
        $project->setStatus($statusId);
        $project->save();
        $project->saveBudget($design, $building, $graphNumber, $reservationNumber, $transportation, $liveLine, $rightOfWay, 0, $statusId, $statusDetail, $entryDate, $responsibleList);
        $wareHouse = Model_warehouse::getByProjectId($project->getId());
        if(!$wareHouse instanceof Model_warehouse)
        {
            $project->startWarehouseProcess($entryDate);
        }
        $response["success"] = 1;
        $response["message"] = "Operacion realizada con exito.";
        echo json_encode($response);exit;
    }

    public function getNewProjectsByMonthAndYear()
    {
        $excel = new ExcelNewProjectsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function getProjectWorkFlowReport()
    {
        set_time_limit(300);
		ini_set('memory_limit','1024M');
        $formData = $this->input->post();
        $codeList = $formData["code-list"];
        $specialColumns = isset($formData["columns-to-download"])?$formData["columns-to-download"]:array();
        $specialColumns = explode(",",$specialColumns);
        $additionalParameters = array("code-list" => $codeList);
        $excel = new ExcelProjectWorkflow($this->sessionUser);
        $excel->setAdditionalParameters($additionalParameters);
        $excel->setColumnDefinition($specialColumns);
        $excel->getReport();
    }

    public function getApprovedProjectsByMonthAndYear()
    {
        $excel = new ExcelApprovedProjectsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function getConciliatedProjectsByMonthAndYear()
    {
        $excel = new ExcelConciliatedProjectsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function getAsBuiltProjectsByMonthAndYear()
    {
        $excel = new ExcelAsBuiltProjectsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function orderNumberAndTotalsByMonthAndYear()
    {
        $excel = new ExcelOrderNumberAndTotalsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function paymentSettledAndTotalsByMonthAndYear()
    {
        $excel = new ExcelPaymentSettledAndTotalsByYearAndMonth($this->sessionUser);
        $excel->getReport();
    }

    public function networksBuilding()
    {
        $formData = $this->input->post();
        $mainList = $formData["keyword"];
        $year = $formData["building-report-year"];
        $excel = new ExcelNetworksBuilding($this->sessionUser, $mainList, $year);
        $excel->getReport();
    }

    public function getCurrentStatusSummary()
    {
        $formData = $this->input->post();
        $system = $formData["project-system"];
        $managementBy = $formData["management-by"];
        $contract = $formData["contract-number"];
        $excel = new ExcelCurrentStatusSummary($this->sessionUser, $system, $managementBy, $contract);
        $excel->getReport();
    }

    public function downloadWorkflowWithParameters()
    {
       	set_time_limit(300);
		ini_set('memory_limit','256M');
        $additionalParameters = $this->input->post();
        // echo"<pre>";var_dump($additionalParameters);exit;
        $excel = new ExcelProjectWorkflow($this->sessionUser);
        $excel->setAdditionalParameters($additionalParameters);
        $excel->getReport();
    }

    public function getExecutiveSummaryReport()
    {
        $formData = $this->input->post();
        $excel = new ExcelExecutiveSummary($this->sessionUser);
        $excel->getReport();
    }

    public function getStakeReport()
    {
		ini_set('memory_limit','256M');
        $formData = $this->input->post();
        $startDate = $formData["stake-report-from"];
        $startDate = DateTime::createFromFormat('d-m-Y', $startDate);
        $startDate = date_format($startDate, 'Y-m-d');
        $startDate = $startDate." 00:00:00";

        $endDate = $formData["stake-report-to"];
        $endDate = DateTime::createFromFormat('d-m-Y', $endDate);
        $endDate = date_format($endDate, 'Y-m-d');
        $endDate = $endDate." 23:59:59";
        $excel = new ExcelStakesReport($this->sessionUser, $startDate, $endDate);
        $excel->getReport();
    }

    public function getBuilderReport()
    {
        set_time_limit(240);
        ini_set('memory_limit','256M');
        $formData = $this->input->post();
        $startDate = $formData["builder-report-from"];
        $startDate = DateTime::createFromFormat('d-m-Y', $startDate);
        $startDate = date_format($startDate, 'Y-m-d');
        $startDate = $startDate." 00:00:00";

        $endDate = $formData["builder-report-to"];
        $endDate = DateTime::createFromFormat('d-m-Y', $endDate);
        $endDate = date_format($endDate, 'Y-m-d');
        $endDate = $endDate." 23:59:59";
        $excel = new ExcelBuilderReport($this->sessionUser, $startDate, $endDate);
        $excel->getReport();
    }

    public function downloadManPowerFile(string $fileHash)
    {
        /** @var Model_file $file */
        $file = Model_file::getByHash($fileHash);
        $fileHandler = new FileHandler();
        $fileName = str_replace('.xlsx','', $file->getOriginalFileName());
        $fileName = str_replace('.xls','', $fileName);
        $fileHandler->download($file, $fileName);
    }

    public function manpower(int $projectId)
    {
        $this->_validateFeature('project_manpower');
        /** @var Model_project $project */
        $project = $this->_validateObjectToEdit($projectId,"Model_project","panel/Project");
        $result = Model_structure_by_point::getByProjectId($projectId);
        if(count($result) > 0)
        {
            redirect(base_url('panel/Project/buildingPoints/'.$projectId));
        }
		$this->complementHandler->addViewComplement("jquery.datatables");
		$this->complementHandler->addViewComplement("jquery.datatables.bootstrap");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.bootstrap");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.flash");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.html5");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.print");
		$this->complementHandler->addViewComplement("jquery.datatables.jszip");
		$this->complementHandler->addViewComplement("jquery.datatables.pdfmake");
		$this->complementHandler->addViewComplement("jquery.datatables.vfs_fonts");
		$this->complementHandler->addViewComplement("moment-range");
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addViewComplement("jquery.inputmask.bundle");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("parsley.spanish");
        $this->complementHandler->addViewComplement('select2');
		$this->complementHandler->addProjectJs('StructureUsageValidator', TRUE);
        $this->complementHandler->addProjectCss('project.manpower', TRUE);
        $this->complementHandler->addProjectJs('project.manpower', TRUE);
        $this->complementHandler->addProjectCss('ManpowerHandler', TRUE);
        $this->complementHandler->addProjectJs('ManpowerHandler', TRUE);
        $this->complementHandler->addProjectJs('LaborCostLogHandler', TRUE);
        $projectWorkflow = WorkflowApiClient::getOne($projectId) ?? [];
        $data['project'] = $project->toArray();
		$data['workflow'] = $projectWorkflow;
        $data['hasChangeLog'] = Model_labor_cost::hasChangeLog($projectId);
        $this->_loadPanelView('project/manpower', $data);
    }

    public function buildingPoints(int $projectId)
    {
        $this->_validateFeature('project_manpower');
        /** @var Model_project $project */
        $project = $this->_validateObjectToEdit($projectId,"Model_project","panel/Project");
		$this->complementHandler->addViewComplement("jquery.datatables");
		$this->complementHandler->addViewComplement("jquery.datatables.bootstrap");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.bootstrap");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.flash");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.html5");
		$this->complementHandler->addViewComplement("jquery.datatables.buttons.print");
		$this->complementHandler->addViewComplement("jquery.datatables.jszip");
		$this->complementHandler->addViewComplement("jquery.datatables.pdfmake");
		$this->complementHandler->addViewComplement("jquery.datatables.vfs_fonts");
		$this->complementHandler->addViewComplement("moment-range");
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addViewComplement("jquery.inputmask.bundle");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("parsley.spanish");
        $this->complementHandler->addViewComplement('select2');
		$this->complementHandler->addViewComplement("pagination-js");
		$this->complementHandler->addViewComplement("google.maps.api.cluster");
		$this->complementHandler->addViewComplement("google.maps.api");
//		$this->complementHandler->addProjectJs('DTAdditionalParameterHandler');
		$this->complementHandler->addProjectJs('PointsLocationHandler', TRUE);
		$this->complementHandler->addProjectJs('StructureUsageValidator', TRUE);
        $this->complementHandler->addProjectCss('project.building-points', TRUE);
        $this->complementHandler->addProjectJs('project.building-points', TRUE);
        $this->complementHandler->addProjectCss('BuildingPointHandler', TRUE);
        $this->complementHandler->addProjectjs('BuildingPointHandler', TRUE);
        $this->complementHandler->addProjectCss('PointToPointHandler', TRUE);
        $this->complementHandler->addProjectJs('PointToPointHandler', TRUE);
		$this->complementHandler->addProjectCss('ManpowerHandler', TRUE);
		$this->complementHandler->addProjectJs('ManpowerHandler', TRUE);
        $this->complementHandler->addProjectJs('LaborCostLogHandler', TRUE);

        $projectWorkflow = WorkflowApiClient::getOne($projectId) ?? [];
        $data['project'] = $project->toArray();
        $data['workflow'] = $projectWorkflow;

        $this->_loadPanelView('project/building-points', $data);
    }

    public function getWorkPlanReport()
    {
        $formData = $this->input->post();
        $startDate = $formData["work-plan-report-from"];
        $startDate = DateTime::createFromFormat('d-m-Y', $startDate);
        $startDate = date_format($startDate, 'Y-m-d');

        $endDate = $formData["work-plan-report-to"];
        $endDate = DateTime::createFromFormat('d-m-Y', $endDate);
        $endDate = date_format($endDate, 'Y-m-d');
        $report = new ExcelWorkPlanReport($this->sessionUser, $startDate, $endDate);
        $report->getReport();
    }

    public function getManpowerActivityForm($projectId)
    {
        $report = new ExcelManPowerEntryActivity($this->sessionUser, $projectId);
        $report->getReport();   
    }

    public function uploadActivityByExcelFile($projectId)
    {

        if (!empty($_FILES['file']['name']))
        {
            try
            {
                $fileHandler = new FileHandler();
                $document = $fileHandler->fileUpload($_FILES['file'],"activity_form","documents","document");
                $document->save();

                $excelManPowerEntryActivity = new ExcelManPowerEntryActivity($this->sessionUser, $projectId);
                $log = $excelManPowerEntryActivity->uploadActivity($document);
                // $log = Model_team::uploadXlsx($document);
                if (count($log) > 0)
                {
                    $errorList = array();
                    foreach ($log as $form)
                    {
                        if(count($form)>0)
                        {
                            $errorList = array_merge($errorList,$form);
                        }
                    }
                    if(count($errorList)>0)
                    {
                        $logHtml = implode("<br/>", $errorList);
                        $this->session->set_flashdata('errorMessage', "<br/>". $logHtml);    
                    }
                    else
                    {
                        $this->session->set_flashdata('successMessage', "Todos los formularios se ingresaron correctamente.");       
                    }
                }
                else
                {
                    $this->session->set_flashdata('successMessage', "Todos los formularios se ingresaron correctamente.");
                }
            }catch (Exception $e)
            {
                $this->session->set_flashdata('errorMessage', $e->getMessage());
                redirect(base_url("panel/Project/manpower/".$projectId));
                
            }
        }
        redirect(base_url("panel/Project/manpower/".$projectId));
    }

    public function locations()
    {
        $this->_validateFeature('project_locations');
        $this->complementHandler->addViewComplement('select2');
        $this->complementHandler->addViewComplement("date-time-picker");
        $this->complementHandler->addViewComplement("parsley");
        $this->complementHandler->addViewComplement("pagination-js");
        $this->complementHandler->addViewComplement("google.maps.api.cluster");
        $this->complementHandler->addViewComplement("google.maps.api");
        $this->complementHandler->addProjectJs('DTAdditionalParameterHandler');
        $this->complementHandler->addProjectJs('ProjectsLocationHandler', TRUE);
        $this->complementHandler->addProjectCss('project.locations',TRUE);
        $this->complementHandler->addProjectJs('project.locations', TRUE);       
        $data = array();
		$statusInLog = Model_project_status::getAllInLog();
        $data['fiscalList'] = Model_user::getByRoleKeyword('fiscal');
        $data['builderList'] = Model_user::getByRoleKeyword('builder');
        $data['statusInLog'] = $statusInLog;
        $this->_loadPanelView("project/locations", $data);
    }

    public function getAllProjectsLog()
    {
		set_time_limit(300);
		ini_set('memory_limit','700M');
        $report = new ExcelAllProjectsLog($this->sessionUser);
        $report->getReport();
    }

    public function projectBudgets($month, $year)
    {
        $startDate = $year."-".$month."-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
        $logDateRange = array("from" => $startDate, "to" => $endDate);
        $report = new ExcelProjectMasterDetail($this->sessionUser, $logDateRange);
        $report->getReport();
    }

    public function gisGirMonthlyReport()
    {
        set_time_limit(300);
		ini_set('memory_limit','700M');
        $report = new ExcelGisGirMonthlyDetail($this->sessionUser);
        $report->getReport();
    }

    public function postProductionBalance()
    {
        set_time_limit(300);
		ini_set('memory_limit','700M');
        $report = new PostProductionBalance($this->sessionUser);
        $report->getReport();
    }

    public function dailyProductivityReport($month, $year)
    {
        $startDate = $year."-".$month."-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
        $logDateRange = array("from" => $startDate, "to" => $endDate);
        $pdf = new ExcelDailyProductivityReport($this->sessionUser, $logDateRange);
        $pdf->getReport();
    }

	public function executiveReport($reportType)
	{
		set_time_limit(300);
		ini_set('memory_limit','256M');
		if($reportType == 1)
		{
			$report = new ExcelExternalExecutiveReport($this->sessionUser);
			$report->getReport();
		}
		elseif($reportType == 2)
		{
			$report = new ExcelInternalExecutiveReport($this->sessionUser);
			$report->getReport();
		}
		else
		{
			$this->session->set_flashdata("errorMessage", "Tipo de reporte desconocido!");
			redirect(base_url('panel/Home'));
		}
	}

	public function importItems()
	{
		set_time_limit(240);
		ini_set('memory_limit','512M');
		require FCPATH . 'application/libraries/PhpSpreadsheet/vendor/autoload.php';
		$currentMaterials = Model_material::getAll(2000,0);
		$currentCodes = array();
		foreach ($currentMaterials as $material)
		{
			$currentCodes[] = $material->code_mat;
		}

//		echo"<pre>";var_dump($currentCodes);exit;
		$inputFileName = FCPATH.'assets/MATERIALES-2019-CRE.xlsx';
		/** Load $inputFileName to a Spreadsheet Object  **/
		$spreadsheet = IOFactory::load($inputFileName);
		$items = $spreadsheet->getSheet(0);
		$arrayItems = $items->toArray();
		$itemsToSave = array();
		$i = 0;

		foreach ($arrayItems as $row)
		{
			if($i > 0)
			{
				$code = $row[0];
				$description = $row[1];
				if(array_search($code,$currentCodes) === FALSE)
				{
					$itemsToSave[] = array(
						'code_mat' => $row[0],
						'description_mat' => $row[1]
					);
				}
			}
			$i++;
		}
//		echo"<pre>";var_dump($itemsToSave);exit;
		if(count($itemsToSave) > 0)
			Model_material::insertBatch($itemsToSave);
	}

	public function assignItemsToBuildingStructures()
	{
		set_time_limit(240);
		ini_set('memory_limit','512M');
		require FCPATH . 'application/libraries/PhpSpreadsheet/vendor/autoload.php';
		$buildingStructures = Model_building_structure::getAll(2000,0);
		$arrayBuildingStructures = array();
		foreach ($buildingStructures as $row)
		{
			$arrayBuildingStructures[$row->structure_code_bus] = (array)$row;
		}

		$currentMaterials = Model_material::getAll(2000,0);
		$arrayMaterials = array();
		foreach ($currentMaterials as $material)
		{
			$arrayMaterials[$material->code_mat] = (array)$material;
		}

		$currentDefaultStructureMaterials = Model_default_structure_material::getAll(10000,0);
		$arrayCurrentDefaultStructureMaterials = array();
		foreach ($currentDefaultStructureMaterials as $row)
		{
			$arrayCurrentDefaultStructureMaterials[$row->structure_id_dsm] = (array)$row;
		}
//		echo"<pre>";var_dump($arrayCurrentDefaultStructureMaterials);exit;
		$inputFileName = FCPATH.'assets/Materiales-x-Estructuras-2020-01-07.xlsx';
		/** Load $inputFileName to a Spreadsheet Obj, kmijuect  **/
		$spreadsheet = IOFactory::load($inputFileName);
		$items = $spreadsheet->getSheet(1);
		$arrayItemsBuildingStructures = $items->toArray();
		$dataToSave = array();

		$i = 0;
		$notFoundStructures = array();
		$newMaterialsToSave = array();
		foreach ($arrayItemsBuildingStructures as $row)
		{
			if($i > 1)
			{
				$structureCode = $row[0];
				$materialCode = $row[3];
				$quantity = $row[4];
				$description = $row[5];
				$completedData = TRUE;
				$structureId = NULL;
				if(isset($arrayBuildingStructures[$structureCode]))
				{
					$structureId = $arrayBuildingStructures[$structureCode]['id_bus'];
				}
				else
				{
					$completedData = FALSE;
					$notFoundStructures[] = $row[0];
				}

				$materialId = NULL;

				if(isset($arrayMaterials[$materialCode]))
				{
					$materialId = $arrayMaterials[$materialCode]['id_mat'];
				}
				else
				{
					$completedData = FALSE;
					$newMaterialsToSave[$materialCode] = array(
						'code_mat' => $materialCode,
						'description_mat' => $description
					);
				}

				if($completedData && !isset($arrayCurrentDefaultStructureMaterials[$structureId]))
				{
					$dataToSave[] = [
						'structure_id_dsm' => $structureId,
						'material_id_dsm' => $materialId,
						'quantity_dsm' => $quantity
					];
				}
			}
			$i++;
		}
		if(count($dataToSave) > 0)
			Model_default_structure_material::insertBatch($dataToSave);
	}

	public function quickSetup(int $projectId)
	{
		$this->_validateFeature('project_edit');
		/** @var Model_project $project */
		$project = $this->_validateObjectToEdit($projectId,"Model_project","panel/Project");

		/** View complements */
		$this->complementHandler->addViewComplement('select2');
		$this->complementHandler->addViewComplement("parsley");
		$this->complementHandler->addProjectCss('project.quick-setup',TRUE);
		$this->complementHandler->addProjectJs('project.quick-setup', TRUE);

		//Get all approved status to assign a Manager
		$allApprovesStatus = Model_project_status_log::getLogByProjectIdAndStatusKeyWord($project->getId(), 'approved');
		foreach ($allApprovesStatus as $row)
		{
			$assignmentRecords = Model_construction_assignment::getAssignmentRecords($row['project_id_psl']);
			//If the approved status does not assigned a manager then let's assign one
			if(count($assignmentRecords) <= 0)
			{
				$constructionAssignment = new Model_construction_assignment($row['id_psl'], "", "", 0, 0, 0, 0, NULL);
				$constructionAssignment->save();
			}
		}

        $productionLimit = Model_production_limit::getByProjectId($projectId);
        if(!$productionLimit instanceof Model_production_limit)
        {
            $productionLimit = new Model_production_limit($projectId, 110, date('Y-m-d H:i:s'), null);
            $productionLimit->save();
        }
		$workFlow = Model_project::getWorkflowDetail(array('id-list'=>$projectId));
		$workFlow = $workFlow[0];
		$projectManagers = Model_user::getByRoleKeyword('project_manager');
		$responsibleListStacker = Model_status_responsible::getResponsibleDetailListByStatusKeyword("stakes", array('stacker'));
		$responsibleListFiscal = Model_status_responsible::getResponsibleDetailListByStatusKeyword("assign_to", array('fiscal'));
		$responsibleListBuilder = Model_status_responsible::getResponsibleDetailListByStatusKeyword("assign_to", array('builder'));
		$creFiscalList = Model_user::getByRoleKeyword("cre_fiscal");
		$contractList = Model_contract::getAll(100, 0);

		$data["contractList"] = $contractList;
		$data["creFiscalList"] = $creFiscalList;
		$data["workFlow"] = $workFlow;
		$data["projectManagers"] = $projectManagers;
		$data["responsibleListFiscal"] = $responsibleListFiscal;
		$data["responsibleListBuilder"] = $responsibleListBuilder;
		$data["responsibleListStacker"] = $responsibleListStacker;
        $data["productionLimit"] = $productionLimit;

		/** Server Side Validations **/
		$this->form_validation->set_rules('project-code', 'Codigo del proyecto', 'trim|required|callback_validate_code');
		$this->form_validation->set_rules('project-cre-fiscal', 'Fiscal', 'trim|required');
		$this->form_validation->set_rules('work-area', 'Work area', 'trim|required');
		$this->form_validation->set_rules('project-contract-id', 'Contract ID', 'trim|numeric|required');

		if($this->form_validation->run() === FALSE)
		{
			$this->_loadPanelView("project/quick-setup", $data);
		}
		else
		{
			$formData = $this->input->post();//echo"<pre>";var_dump($formData);exit;
			$projectLog = Model_project_status_log::getLogByProjectId($projectId);
			$projectCode = $formData["project-code"];
			$projectManager = $formData['project-manager']??NULL;

			$responsibleIds = $formData["responsible-ids"]??NULL;
			$staker = $formData['staker']??NULL;
			$projectCreFiscal = $formData["project-cre-fiscal"];
            $newProductionLimit = $formData["project-production-limit"];
			$contractId = $formData["project-contract-id"];
			$workArea = $formData['work-area'];
			$project->setCREFiscal($projectCreFiscal);
			$project->setContractId($contractId);
			$project->setWorkArea($workArea);
			$project->setCode($projectCode);
			$project->setSecondaryCode($projectCode);
			$project->save();
			$assignmentRecords = Model_construction_assignment::getAssignmentRecords($project->getId());
			//Change manager
			/** @var Model_construction_assignment $row */
			foreach ($assignmentRecords as $row)
			{
				if(!is_null($projectManager) && $projectManager != "")
				{
					$row->setProjectManager($projectManager);
					$row->save();
				}
			}
			//Change stacker
			foreach ($projectLog as $log)
			{
				if($log['keyword_pst'] == 'stakes' || $log['keyword_pst'] == 'rd_stakes')
				{
					/** @var Model_status_log_responsible $statusLogResponsible */
					$statusLogResponsibleList = Model_status_log_responsible::getObjectsByStatusLogId($log['id_psl']);
					foreach ($statusLogResponsibleList as $responsible)
					{
						$responsible->setResponsibleId($staker);
						$responsible->save();
					}
				}
			}

            if($productionLimit->getLimit() != $newProductionLimit)
                Model_production_limit::newProductionLimit($projectId, (float)$newProductionLimit);
                
			//change responsible list in building process
			if(!is_null($responsibleIds))
				Model_status_log_responsible::reAssignResponsibleIds($responsibleIds, $project->getId());
			$this->session->set_flashdata("successMessage", "Proyecto modificado correctamente!");
			redirect(base_url("panel/Project/quickSetup/".$project->getId()));
		}
	}

    public function loadPendingsFile()
    {
        /** Load libraries */
        $this->load->library('form_validation');
        // $this->form_validation->set_rules('pendings-file', 'File', 'trim');

        /** Breadcrumbs */

        if ($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("warehouse/import-materials");
        }
        else
        {
            if (!empty($_FILES['pendings-file']['name']))
            {
                try
                {
                    $formData = $this->input->post();
                    $createPendingSummary = $formData['create-pending-summary'];
                    $fileHandler = new FileHandler();
                    $document = $fileHandler->fileUpload($_FILES['pendings-file'], "pendings_doc", "documents", "document");
                    $document->save();
                    $pendingReturnsFileReader = new PendingReturnsFileReader($document);
                    if($createPendingSummary == 1)
                    {
                        $pendingReturnsFileReader->createPendingSummary();
                    }
                    else
                    {
                        $preview = $pendingReturnsFileReader->previewPendingSummary();
                        $this->_loadPanelView("project/upload-pendings-file",['preview'=>$preview]);
                    }
                    
                    $response['success'] = 1;
                    $response['message'] = '';
                    $response['data']['preview'] = $preview??[];
                }
                catch (Exception $e)
                {
                    $response['success'] = 0;
                    $response['message'] = $e->getMessage();
                    $response['data'] = array();
                }
            }
            else
            {
                $response['success'] = 0;
                $response['message'] = 'No se selecciono ningun archivo de Pendientes para revisar.';
                $response['data']['file'] = array();
            }
            //echo json_encode($response);exit;
        }  
    }

    public function setEstimatedDays()
    {exit('disabled');
        $newDays = [
            "ro.21.0070" => 7, 
            "ro.21.0127" => 2, 
            "ro.21.0083" => 3, 
            "ro.21.0368" => 4, 
            "ro.21.0095" => 4, 
            "ro.21.0091" => 2, 
            "ro.21.0265" => 6, 
            "ro.21.0253" => 2, 
            "ro.21.0301" => 6, 
            "ro.21.0163" => 6, 
            "ro.21.0077" => 4, 
            'ra.21.2208' => 3, 
            'ra.21.2454' => 2, 
            'ra.21.2677' => 2, 
            'ra.21.2744' => 7, 
            'ra.21.2487' => 4
        ];
        $codeList = array_keys($newDays);

        // $newDays = ['RD.20.0067' => 3];
        // $codeList = array_keys($newDays);

        $projectList = Model_project::getByCodeList($codeList);

        /** @var Model_project $project */
        foreach($projectList as $project)
        {
            // Model_project
            $assignmentRecords = Model_construction_assignment::getAssignmentRecords($project->getId());

            /** @var Model_construction_assignment $row */
            foreach ($assignmentRecords as $row)
            {
                $newQuantityDays = $newDays[$project->getCode()];
                
                $row->setEstimatedTime($newQuantityDays);

                $startDate = New DateTime($row->getStartDate());
                $startDate->modify("+".$newQuantityDays." days");
                
                $row->setEndDate($startDate->format("Y-m-d H:i:s"));
                $row->save();
            }
        }
        
    }

    public function batchStatusUpdate()
    {
        $data['viewTitle'] = "Actualizacion Masiva";
        $this->_loadPanelView("project/batch-status-update", $data);       
    }

    public function downloadLaborCostChangeLogReport($projectId)
    {
        $client = new Client(['base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL')]);
        $apiResponse = $client->request('GET', 'api/v1/labor-cost-change-log/'.$projectId);
        $arrayResponse = json_decode($apiResponse->getBody(),true);
        $project = Model_project::getById($projectId);
        $file="Reporte de modificacion de cantidades - mano de obra ".$project->getCode()." - ".date("d-m-Y H.i.s").".xls";
        header('Content-type: application/excel');
        header("Content-Disposition: attachment; filename=$file");
        echo utf8_decode($arrayResponse['data']['file']);
    }

    public function downloadDailyReportsP1()
    {
        $startDate = date('Y')."-".date("m")."-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
		$builderGeneralReport = new ExcelBuildersGeneralReport($this->sessionUser, $startDate, $endDate);
		$builderGeneralReport->getReport();
    }

    public function downloadDailyReportsP2()
    {
        $startDate = date('Y')."-".date("m")."-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
        $logDateRange = array("from" => $startDate, "to" => $endDate);
        $dailyProductivityReport = new ExcelDailyProductivityReport($this->sessionUser, $logDateRange);
        $dailyProductivityReport->getReport();
    }

    public function downloadDailyReportsP3()
    {
        set_time_limit(300);
		ini_set('memory_limit','524M');
        $workflowReport = new ExcelProjectWorkflow($this->sessionUser);
        $workflowReport->getReport();
    }

    public function downloadDailyReportsP4()
    {
        set_time_limit(300);
		ini_set('memory_limit','700M');
        $allProjectsLog = new ExcelAllProjectsLog($this->sessionUser);
        $allProjectsLog->getReport();
    }
}
