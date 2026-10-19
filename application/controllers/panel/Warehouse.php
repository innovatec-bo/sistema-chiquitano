<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 09/08/2018
 * Time: 10:34 AM
 */

require FCPATH . 'application/libraries/PhpSpreadsheet/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Reader\Xls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class Warehouse extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
    }

	private function _index($metaData) : void
	{
		$materialSummary = NULL;
		if(isset($_GET['request-id']))
		{
			$materialsRequestId = $_GET['request-id'];
			$method = debug_backtrace()[1]['function'];
			$redirectTo = "panel/Warehouse/".$method;
			/** @var Model_material_summary $materialSummary */
			$materialSummary = $this->_validateObjectToEdit($materialsRequestId,Model_material_summary::class, $redirectTo, "No se encuentra la lista de solicitud.");
			$requests = Model_material_summary_type::getByMovementType(['request','request_cre']);
			if(!isset($requests[$materialSummary->getSummaryType()]))
			{
				$this->session->set_flashdata("errorMessage", "Debe ingresar un c&oacute;digo de una lista de solcitud.");
			}
		}
		/** View complements */
		$this->complementHandler->addViewComplement("jquery.datatables");
		$this->complementHandler->addViewComplement("jquery.datatables.bootstrap");
		$this->complementHandler->addViewComplement('select2');
		$this->complementHandler->addViewComplement("parsley292");
		$this->complementHandler->addViewComplement("parsley292.spanish");
		$this->complementHandler->addViewComplement("date-time-picker");
		$this->complementHandler->addViewComplement('jquery.inputmask.bundle');
		$this->complementHandler->addProjectJs('DTAdditionalParameterHandler', TRUE);
		$this->complementHandler->addProjectCss('warehouse.entry', TRUE);
		$this->complementHandler->addProjectJs('WarehouseHandler', TRUE);
		$this->complementHandler->addProjectJs('warehouse.entry', TRUE);
		// $this->complementHandler->addReactComponent('like_button');
		$this->complementHandler->addReactComponent('hello_world');

		/** Server Side Validations **/
		$this->form_validation->set_rules('summary-type', 'Tipo de movimiento', 'trim|required');
		//$this->form_validation->set_rules('summary', 'Materiales','callback_validate_summary_materials');
		$this->form_validation->set_rules('summary[]', 'Materiales','trim|required');
		$data = array();
		$fiscals = Model_user::getByRoleKeyword('fiscal');
		$builders = Model_user::getByRoleKeyword('builder');

		/*** data from request list - begin*/
		$data['materialSummary'] = [];
		$data['materialList'] = [];
		$data['summaryTypeId'] = [];
		if($materialSummary instanceof Model_material_summary)
		{
			//summary type id calling another summary type
			$outList = [
				14 => "materials_delivered_to_builder",
				15 => "materials_delivered_to_builder_loan",
				16 => "materials_additional_list"
			];
			$data['materialSummary'] = Model_material_summary::getMasterDetailByListId($materialSummary->getId());
			$data['materialList'] = Model_project_material::getBySummaryId($materialSummary->getId());
			$summaryType = Model_material_summary_type::getByKeyword([$outList[$materialSummary->getSummaryType()]]);
			$summaryType = array_values($summaryType);
			$data['summaryTypeId'] = $summaryType[0]->getId();
		}
		/*** data from request list - end*/		

		$data['fiscals'] = $fiscals;
		$data['builders'] = $builders;
		$data = array_merge($data,$metaData);

		if($this->form_validation->run() === FALSE)
		{
			$this->_loadPanelView("warehouse/entry",$data);
		}
		else
		{
			$formData = $this->input->post();
			$method = debug_backtrace()[1]['function'];

			$projectId = $formData['project'];
			
			$reservationNumber = $formData['reservation-number'];
			if($reservationNumber == "")
			{
				$data = Model_material_summary::getReservationNumbersByProjectId($projectId);
				if(count($data) > 0)
					$reservationNumber = $data[0]['reservation_number'];
				else
				{
					$this->session->set_flashdata("errorMessage", "La lista inicial de materiales no cuenta con el numero de reserva, por favor contacte con soporte.");
					redirect(base_url("panel/Warehouse/".$method));
				}
					

			}

			$summaryType = $formData['summary-type'];
			$builder = $formData['builder'];
			$fiscal = $formData['fiscal'];
			$entryDate = $formData['entry-date'];
			$entryDate = DateTime::createFromFormat('d-m-Y', $entryDate);
			$entryDate = date_format($entryDate, 'Y-m-d');
			$entryDate = $entryDate." ".date("H:i:s");
			$materials = array_values($formData['summary']);
			$summaryWithBuilderAndFiscal = array(4,10,11,14,15,17,18);
			$summaryWithReservationNumber = array(2,3,8);
			$currentUser = PrivateController::getSessionUser();
			$currentUserId = isset($currentUser) ? $currentUser->id:NULL;

			$newMaterialSummary = new Model_material_summary(NULL,'TODOS',$projectId,$projectId,'','',$entryDate,'',$currentUserId,$summaryType, NULL);

			$newMaterialSummary->setBuilderResponsible(NULL);
			$newMaterialSummary->setFiscalResponsible(NULL);
			if(array_search($summaryType, $summaryWithBuilderAndFiscal) !== FALSE)
			{
				$newMaterialSummary->setBuilderResponsible($builder);
				$newMaterialSummary->setFiscalResponsible($fiscal);
			}
			if(isset($_GET['request-id']))
			{
				$newMaterialSummary->setParentSummaryId($_GET['request-id']);
			}
			if(array_search($summaryType, $summaryWithReservationNumber) !== FALSE)
			{
				
				$newMaterialSummary->setReservationNumber($reservationNumber);
			}

			$summariesByProjectAndType = Model_material_summary::getSummariesByProjectAndType($newMaterialSummary->getProjectId(),$summaryType);
			$correlativeCounter = count($summariesByProjectAndType) + 1;
			$newMaterialSummary->setCorrelativeCounter($correlativeCounter);
			$newMaterialSummary->setFiscalResponsible($fiscal);
			$newMaterialSummary->save();
			$newMaterialSummary->saveMaterials($materials);
			WorkflowSyncNotifier::notify($projectId);
			$requestID = "";
			if($newMaterialSummary->getSummaryType() == 14 || $newMaterialSummary->getSummaryType() == 15)
				$requestID = "<strong>Su c&oacute;digo de solicitud es : ".$newMaterialSummary->getId()."</strong> <a href='javascript:void(0)' onclick='window.print();'>Imprimir</a>";
			$this->session->set_flashdata("successMessage", "La lista se creo correctamente.".$requestID);
			$printView = $this->printView($newMaterialSummary->getId(),1);
			$this->session->set_flashdata("printView", $printView);
			
			redirect(base_url("panel/Warehouse/".$method));
		}
	}

	public function registerMovement()
	{
		$summaryTypes = Model_material_summary_type::getByMovementType(['in','out']);
		$projects = Model_project::getByStatusKeywordList(['approved','assign_to','in_progress','stopped','paused','completed','as_built','conciliation_reception','conciliation_shipment','cre_return_order','project_return_materials','project_energized']);
		$metaData = [
			'viewTitle' => 'Registrar movimiento de materiales',
			'summaryTypeTitle' => 'Tipo de movimiento',
			'summaryTypes' => $summaryTypes,
			'showSearchBox' => 1,
			'materialsTitle' => 'Lista general',
			'showAssignedMaterialsOnly' => 0,
			'showBtnListAll' => 1,
			'projects' => $projects
		];
		$this->_index($metaData);
	}

	public function requestMaterials()
	{
		$summaryTypes = Model_material_summary_type::getByMovementType(['request']);
		$projects = Model_project::getByStatusKeywordList(['approved','assign_to','in_progress','stopped','paused','completed']);
		$metaData = [
			'viewTitle' => 'Solicitar materiales',
			'summaryTypeTitle' => 'Tipo de solicitud',
			'summaryTypes' => $summaryTypes,
			'showSearchBox' => 0,
			'materialsTitle' => 'Materiales asignados',
			'showAssignedMaterialsOnly' => 1,
			'showBtnListAll' => 1,
			'projects' => $projects
		];
		$this->_tabTitle = "Solicitar materiales";
		$this->_index($metaData);
	}

    public function registerAdditionalList()
    {
        $summaryTypes = Model_material_summary_type::getByKeyword(['materials_additional_list']);
		$projects = Model_project::getByStatusKeywordList(['approved','assign_to','in_progress','stopped','paused','completed','as_built','conciliation_reception','conciliation_shipment','cre_return_order','project_return_materials','project_energized']);
		$metaData = [
			'viewTitle' => 'Ingresar lista de adicionales',
			'summaryTypeTitle' => 'Lista',
			'summaryTypes' => $summaryTypes,
			'showSearchBox' => 1,
			'materialsTitle' => 'Materiales en el sistema',
			'showAssignedMaterialsOnly' => 0,
			'showBtnListAll' => 0,
			'projects' => $projects
		];
		$this->_tabTitle = "Ingresar lista de adicionales";
		$this->_index($metaData);
    }

	public function printView($summaryId = NULL, $getAsVariable = NULL)
	{
		$materialSummary = NULL;
		if(!is_null($summaryId))
		{
			$materialsRequestId = $summaryId;
			$method = debug_backtrace()[1]['function'];
			$redirectTo = "panel/Warehouse/".$method;
			/** @var Model_material_summary $materialSummary */
			$materialSummary = $this->_validateObjectToEdit($materialsRequestId,Model_material_summary::class, $redirectTo, "No se encuentra la lista de solicitud.");
		}

		$this->complementHandler->addProjectCss('warehouse.print-view', TRUE);
		$this->complementHandler->addProjectJs('warehouse.print-view', TRUE);

		/*** data from request list - begin*/
		$materialList = NULL;
		$summaryTypeId = NULL;
		if($materialSummary instanceof Model_material_summary)
		{
			$materialSummary = Model_material_summary::getMasterDetailByListId($materialSummary->getId());
			$materialList = Model_project_material::getBySummaryId($materialSummary['summary_id']);
			$summaryType = Model_material_summary_type::getById($materialSummary['summary_type']);
			$summaryTypeId = $summaryType->getId();
		}
		/*** data from request list - end*/
		$viewTitle = "Movimiento de materiales";
		if($summaryTypeId == 15 || $summaryTypeId == 14)
			$viewTitle = "Solicitud de materiales";


		if($getAsVariable == 1)
		{
			$visiblePrintBlock = "visible-print-block";
			return $this->load->view("default-template/panel/content/warehouse/print-view",compact('viewTitle','materialSummary','materialList','summaryTypeId','summaryType','visiblePrintBlock'), TRUE);
		}

		else
		{
			$visiblePrintBlock = "";
			$this->_loadPanelView("warehouse/print-view",compact('viewTitle','materialSummary','materialList','summaryTypeId','summaryType','visiblePrintBlock'));
		}
	}

	public function requestAdditionalList()
	{
		$this->complementHandler->addViewComplement("parsley292");
		$this->complementHandler->addViewComplement("parsley292.spanish");
		$this->complementHandler->addViewComplement("redips-table");
		$this->complementHandler->addViewComplement("date-time-picker");
		$this->complementHandler->addViewComplement('jquery.inputmask.bundle');
		$this->complementHandler->addProjectCss('warehouse.request-additional-list', TRUE);
		$this->complementHandler->addProjectJs('warehouse.request-additional-list', TRUE);
		//Server side validations
		$this->form_validation->set_rules('entry-date', 'Fecha', 'trim|required');
		$this->form_validation->set_rules('builder-id', 'Constructor', 'trim|required');
		$this->_tabTitle = "Solicitar lista de adicionales";
		$builders = Model_user::getByRoleKeyword('builder');
		$data = [];
		if($this->form_validation->run() === FALSE)
		{
			$this->_loadPanelView("warehouse/request-additional-list",compact('builders'));
		}
		else
		{
			$formData = $this->input->post();
			// dd($formData);
			$entryDate = $formData['entry-date'];
			$projectId = $formData['project'];
			$builderId = $formData['builder-id'];
			$projectWorkflow = WorkflowApiClient::getOne($projectId) ?? [];
			$entryDate = DateTime::createFromFormat('d-m-Y', $entryDate);
			$entryDate = date_format($entryDate, 'Y-m-d');
			$entryDate = $entryDate." ".date("H:i:s");
			$materials = array_values($formData['summary']);
			$currentUser = PrivateController::getSessionUser();
			$currentUserId = isset($currentUser) ? $currentUser->id:NULL;
			$summaryType = Model_material_summary_type::getByKeyword(['request_additionals_to_cre']);
			$summaryType = array_values($summaryType);
			/** @var Model_material_summary_type $summaryType */
			$summaryType = $summaryType[0];
			$newMaterialSummary = new Model_material_summary(NULL,'TODOS',$projectId,$projectId,'','',$entryDate,'',$currentUserId, $summaryType->getId(), NULL);
			//13/01/2022 - se modificaron las asignaciones
			$newMaterialSummary->setBuilderResponsible($builderId);
			$newMaterialSummary->setFiscalResponsible($projectWorkflow['fiscal_responsible_id']);
			
			$summariesByProjectAndType = Model_material_summary::getSummariesByProjectAndType($newMaterialSummary->getProjectId(),$newMaterialSummary->getSummaryType());
			$correlativeCounter = count($summariesByProjectAndType) + 1;
			
			$newMaterialSummary->setCorrelativeCounter($correlativeCounter);
			$newMaterialSummary->save();
			$newMaterialSummary->saveMaterials($materials);
			$this->session->set_flashdata("successMessage", "Solicitud creada correctamente. Esta solicitud sirve como garantia para pedir un prestamo en el almacen de SEREBO");
			$this->session->set_flashdata("requestId", $newMaterialSummary->getId());
			$method = debug_backtrace()[1]['function'];
			redirect(base_url("panel/Warehouse/requestAdditionalList"));
		}		
	}

	public function loadInitialList()
	{
		$this->_tabTitle = "Cargar lista inicial de materiales";
		// $this->form_validation->set_rules('materials-file', 'File', 'trim|required');
		$this->form_validation->set_rules('project-code', 'Project Code', 'trim|required');
		if ($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("warehouse/load-initial-list");
        }
        else
        {
			if (!empty($_FILES['materials-file']['name']))
			{
				try
				{
					$formData = $this->input->post();
					$project = Model_project::getByCode($formData['project-code']);
					$projectId = $project->getId();
					$fileHandler = new FileHandler();
					$materialsFile = $fileHandler->fileUpload($_FILES['materials-file'], "materials_doc", "documents", "document");
					$materialsFile->save();
					$materialsFileReader = new MaterialsFileReader($projectId, $materialsFile);
					$materialsFileReader->saveMaterialsInDataBase();
					// if($registerMaterialsInSystem == 1)
					// {
						$currentUser = PrivateController::getSessionUser();
						$currentUserId = isset($currentUser) ? $currentUser->id:NULL;
						$log = Model_project_status_log::getLogByProjectIdAndStatusKeyWord($projectId,'approved');
						$materialsFileReader->registerMaterialsInSystem($log[0]['id_psl'], $log[0]['manual_entry_date_psl'], NULL,1,"",null,$log[0]['reservation_number_prb']);
					// }
					WorkflowSyncNotifier::notify($projectId);
					$response['success'] = 1;
					$response['message'] = '';
					$response['data']['file']['id'] = $materialsFile->getId();
					$this->session->set_flashdata("successMessage", 'Materiales cargados correctamente');
				}
				catch (Exception $e)
				{
					$response['success'] = 0;
					$response['message'] = $e->getMessage();
					$response['data'] = array();
					$this->session->set_flashdata("errorMessage", $response['message']);
				}
			}
			else
			{
				$response['success'] = 0;
				$response['message'] = 'No se selecciono ningun archivo de materiales para revisar.';
				$response['data']['file'] = array();
				$this->session->set_flashdata("errorMessage", $response['message']);
			}
			redirect(current_url());
		}
	}
	
	public function importMaterials()
	{
		$this->_tabTitle = "Importar materiales";
        /** Load libraries */
        $this->load->library('form_validation');

		

        $this->form_validation->set_rules('materials-file', 'File', 'trim');

        /** Breadcrumbs */

        if ($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("warehouse/import-materials");
        }
        else
        {
            if (!empty($_FILES['materials-file']['name']))
            {
                try
                {
                    $fileHandler = new FileHandler();
                    $document = $fileHandler->fileUpload($_FILES['materials-file'], "import_materials_doc", "documents", "document");
                    $document->save();

					switch (strtolower($document->getExtension()))
					{
						case 'xlsx':
							$reader = new Xlsx();
							break;
						case 'xls':
							$reader = new Xls();
							break;
						default:
							$reader = new Csv();
							$reader->setDelimiter(';');
							break;
					}
					
					$fileLocation = FCPATH.$document->getUrl();
					$spreadsheet = $reader->load($fileLocation);
					$sheetList = $spreadsheet->getAllSheets();
					$sheetData = $sheetList[0];
					$excelArrayData = $sheetData->toArray();
					array_shift($excelArrayData);
					$codesInExcel = array_column($excelArrayData,0);
					$existingMaterials = Model_material::getByCodeList($codesInExcel);
					
					$toUpdate = [];
					$toSave = [];
					$i = 0;
					/** @var Model_material $existingMaterial */
					foreach($existingMaterials as $existingMaterial)
					{
						$key = array_search($existingMaterial->getCode(), $codesInExcel);
						if($key !== FALSE)
						{
							$descriptionInExcel = $excelArrayData[$key][1];
							$unitOfMeasurementInExcel = $excelArrayData[$key][2];
							if($descriptionInExcel != "")
								$existingMaterial->setDescription($descriptionInExcel);
							if($unitOfMeasurementInExcel != "")
								$existingMaterial->setUnitOfMeasurement($unitOfMeasurementInExcel);
							if($descriptionInExcel != "" || $unitOfMeasurementInExcel != "")
							{
								$existingMaterial->setEditedOn(date('Y-m-d H:i:s'));
								$existingMaterial->setEditedBy($this->sessionUser->id);
							}
								
							$toUpdate[] = $existingMaterial->toArray(); 
							unset($codesInExcel[$key]);
							unset($excelArrayData[$key]);
						}	
						$i++;
					}
					$excelArrayData = array_values($excelArrayData);
					foreach($excelArrayData as $excelData)
					{
						$code = $excelData[0];
						$descriptionInExcel = $excelData[1];
						$unitOfMeasurementInExcel = $excelData[2];
						// dd($code,$descriptionInExcel, $unitOfMeasurementInExcel);
						$newMaterial = new Model_material($code,NULL,$descriptionInExcel,$unitOfMeasurementInExcel);
						$newMaterial->setCreatedOn(date('Y-m-d H:i:s'));
						$newMaterial->setCreatedBy($this->sessionUser->id);
						$toSave[] = $newMaterial->toArray();
					}
					$totalToUpdate = count($toUpdate);
					if($totalToUpdate > 0)
						$updatedRows = Model_material::updateBatch($toUpdate,'code_mat');
					$totalToSave = count($toSave);
					if($totalToSave > 0)
						$insertRows = Model_material::insertBatch($toSave);

					$this->session->set_flashdata("successMessage", "Archivo leido correctamente.");
                }
                catch (Exception $e)
                {
					$this->session->set_flashdata("errorMessage", $e->getMessage());
                }
            }
            else
            {
				$this->session->set_flashdata("errorMessage", "No se selecciono ningun archivo!");
            }
            redirect(base_url("panel/Warehouse/importMaterials"));
        }  
    }

	public function setUp()
	{
		$this->_tabTitle = "Configuracion de almacen";
		// Crear una vista para configurar el almacen
		// Primer parametro de configuracion de almacen => definir los dias de vigencia de una solicitud de fiscal a almacen.
		// Ejecutar el cerrado de solicitudes pendientes que han vencido.
	}

	public function getMySummaries()
	{
		//El fiscal podra ver las listas que ha creado, pueden ser sus solicitudes al almacen interno, o las listas que envio a CRE para solicitar materiales.
		//Cada lita debe tener sus botones de accion.
	}

	public function downloadExcelRequestAdditionalToCRE()
	{
		$summaryId = $this->input->post('summary-id');
		// $summaryId = 16;
		$excelRequestMaterialToCRE = new ExcelRequestMaterialToCRE($this->sessionUser, $summaryId);
		$excelRequestMaterialToCRE->getReport();
		// dd($materialSummary, $materialList);
	}

	public function downloadExcelRequestAdditionalToCREV2()
	{
		$summaryId = $this->input->post('summary-id');
		// $summaryId = 16;
		$excelRequestMaterialToCRE = new ExcelRequestMaterialToCREV2($this->sessionUser, $summaryId);
		$excelRequestMaterialToCRE->getReport();
		// dd($materialSummary, $materialList);
	}

	public function movementsLog()
	{
		$projectCode = $this->input->get('project-code');
		$log = [];
		if(!is_null($projectCode))
		{
			$project = Model_project::getByCode($projectCode);
			$query = Model_material_summary::getHistoryLog($project->getId());
			$log = $query->result_array();
		}
		
		$this->_loadPanelView("warehouse/movements-log", compact('log','projectCode'));
	}

	public function summaryByProject()
	{
		
	}
}
