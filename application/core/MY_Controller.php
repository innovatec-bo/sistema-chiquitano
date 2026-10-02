<?php

use Assert\Assertion;
use Assert\Assert;
use Assert\LazyAssertionException;
use Assert\AssertionFailedException;
use GuzzleHttp\Client;

class PublicController extends CI_Controller
{
    protected $_ci;
    /**
     * @var ComplementHandler
     */
    protected $complementHandler;

    /**
     * @var string
     */
    protected $_panelTmpl;
    protected $_tabTitle;

    public function __construct()
    {
        parent::__construct();
        date_default_timezone_set('America/La_Paz');
        $this->load->helper("ssl_helper");
        $this->_evalSslUsage();
        $this->_ci = &get_instance();
        $this->load->driver('session');
        $this->load->library('form_validation');
        $this->_panelTmpl = "default-template";
        $this->_tabTitle = "Login";
        $this->complementHandler = new ComplementHandler();
        $this->complementHandler->addViewComplement("jquery");
        $this->complementHandler->addViewComplement("bootstrap");
        $this->complementHandler->addViewComplement("metisMenu");
        $this->complementHandler->addViewComplement("font-awesome");
        $this->complementHandler->addViewComplement("sb-admin-2");
        $this->complementHandler->addViewComplement("jquery.blockui");
        $this->complementHandler->addProjectCss('public-custom-style', TRUE);
    }

    protected function _loadPublicView($contentView, $contentData = array())
    {
        $contentData["complementHandler"] = $this->complementHandler;
        $contentData["tabTitle"] = $this->_tabTitle;
        $contentData["contentView"] = $contentView;
        $this->load->view($this->_panelTmpl."/public/master/master", array("contentData" => $contentData));
    }

    protected function _encryptPassword($password)
    {
        $options = [
            'cost' => 10
        ];
		return password_hash($password, PASSWORD_BCRYPT, $options);
    }

    public function loadView($viewFile, $contentData = array(), $returnAsData = FALSE)
    {
        if($returnAsData)
        {
            return $this->load->view($this->_panelTmpl."/".$viewFile, $contentData,$returnAsData);
        }
        else
        {
            $this->load->view($this->_panelTmpl."/".$viewFile, $contentData);
        }
    }

	/**
	 * @param $parameter
	 * @param $class
	 * @param $onFailRedirectTo
	 * @param string $nonExistentObjectMessage
	 * @return mixed
	 */
    protected function _validateObjectToEdit($parameter, $class, $onFailRedirectTo, $nonExistentObjectMessage = "El objeto no existe.")
    {
        if(!is_numeric($parameter))
        {
            $this->session->set_flashdata("errorMessage", "Parametro incorrecto.");
            redirect(base_url($onFailRedirectTo));
        }
        $object = $class::getById($parameter);

        if(!$object instanceof $class)
        {
            $this->session->set_flashdata("errorMessage", $nonExistentObjectMessage);
            redirect(base_url($onFailRedirectTo));
        }

        return $object;
    }

    protected function _evalSslUsage()
    {
        if(ENVIRONMENT == "production" || ENVIRONMENT == "testing" )
        {
            force_ssl();
        }
    }

    protected function _validateStatusSet($statusSet, $project)
    {
        switch ($statusSet)
        {
            case 'design':
                $keywordList = array("project_has_been_created","stakes","returned","digitization","drawing","schedule","canceled");
                break;
            case 'approvement':
                $keywordList = array("ready_to_send","already_sent","approved","canceled","rectify_design","rectify_illustration","returned");
                break;
            case 'rectify_design':
                $keywordList = array("rectify_design", "rd_stakes", "rd_digitization", "rd_drawing","returned");
                break;
            case 'rectify_illustration':
                $keywordList = array("rectify_illustration", "ri_digitization", "ri_drawing");
                break;
//            case 'warehouse':
//                $keywordList = array("warehouse","record_building_materials", "get_materials", "deliver_materials", "assign_to", "return_materials","materials_reception");
//                break;
            case 'building':
                $keywordList = array("assign_to","in_progress", "paused", "stopped", "completed","project_energized","as_built", "conciliation_reception", "conciliation_shipment","cre_return_order","project_return_materials", "project_real_budget_confirmation");
                break;
            default:
                $keywordList = array();
                $this->session->set_flashdata("errorMessage","El conjunto de estados es incorrecto!");
                redirect("panel/Project");
        }

        return $keywordList;
    }

    public static function array_unshift_assoc(&$arr, $key, $val)
    {
        $arr = array_reverse($arr, true);
        $arr[$key] = $val;
        $arr = array_reverse($arr, true);
        return $arr;
    }

    public static function creFiscalSupervisingList($creFiscalEmail)
    {
        $list = array(
//            SISTEMA INTEGRADO
            'joseosy@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo','lorgiocc@cre.com.bo'),
            'luisdf@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'salviocm@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'rolandodc@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'juancmg@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'erlinac@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'mariodgr@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'josers@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'javiervm@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'miltonmr@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),
            'jhonyvv@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),//Not in excel list
            'reneoom@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),//Not in excel list
            'christianvr@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo'),//Not in excel list
			//'victormg@cre.com.bo' => array('albertol@cre.com.bo','nicolaps@cre.com.bo','martinlp@cre.com.bo'),//Not in excel list
//            SISTEMA INTEGRADO
            'juancgh@cre.com.bo' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo'),
            'joseeba@cre.com.bo' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo'),
            'miltonro@cre.com.bo' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo'),
            'rclaure@cruztel.com' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo'),
            'pablopdvm@gmail.com' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo'),
            'layonelrlm@cre.com.bo' => array('jorgedo@cre.com.bo','sergiommp@cre.com.bo','percygg@cre.com.bo','albertol@cre.com.bo','nicolaps@cre.com.bo','martinlp@cre.com.bo'),//Not in excel list
//            SISTEMA INTEGRADO
            'carlosagad@cre.com.bo' => array('jorgedo@cre.com.bo','carlosmc@cre.com.bo','percygg@cre.com.bo'),
            'diegoasr@cre.com.bo' => array('jorgedo@cre.com.bo','carlosmc@cre.com.bo','percygg@cre.com.bo'),
            'dariojfm@cre.com.bo' => array('jorgedo@cre.com.bo','percygg@cre.com.bo','sergiommp@cre.com.bo'),
//            SISTEMA MISIONES
            'santosbcg@cre.com.bo' => array('oscarbr@cre.com.bo','anibalga@cre.com.bo', 'jhonnyrc@cre.com.bo'),
            'walterag@cre.com.bo' => array('oscarbr@cre.com.bo','anibalga@cre.com.bo', 'jhonnyrc@cre.com.bo'),
            'hermanvf@cre.com.bo' => array('oscarbr@cre.com.bo','anibalga@cre.com.bo', 'jhonnyrc@cre.com.bo'),
            'santiagojse@cre.com.bo' => array('oscarbr@cre.com.bo','anibalga@cre.com.bo', 'jhonnyrc@cre.com.bo'),
//            SISTEMA GERMAN BUSH
            'joselrs@cre.com.bo' => array('rolandsh@cre.com.bo','juanjal@cre.com.bo'),
//            SISTEMA  ROBORE
            'darwindm@cre.com.bo' => array('rolandsh@cre.com.bo','wilsongg@cre.com.bo'),
//            SISTEMA  VALLES
            'oresterb@cre.com.bo' => array('rolandoecp@cre.com.bo','rogerwrc@cre.com.bo'),
            //News(Not in excel list//Not in excel list)
            'sergiommp@cre.com.bo' => array('jorgedo@cre.com.bo'),
            'paulrs@cre.com.bo' => array('dariojfm@cre.com.bo','sergiommp@cre.com.bo','jorgedo@cre.com.bo','percygg@cre.com.bo'),
            'robertomm@cre.com.bo' => array('sergiommp@cre.com.bo','jorgedo@cre.com.bo')
        );
        return isset($list[$creFiscalEmail])?$list[$creFiscalEmail]:array();
    }

    public static function internalNoticeByStatus($status)
    {
        $statusList = array(
            "project_has_been_created" => array("to" => array("carloseduardol@serebo.com"), "cc" => array()),
            "approved" => array("to" => array("maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "assign_to" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "in_progress" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "paused" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "completed" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "project_energized" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "cre_return_order" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array()),
            "project_return_materials" => array("to" => array("maguilera@serebo.com"), "cc" => array()),
            "conciliation_reception" => array("to" => array("fiscal","maguilera@serebo.com","eddysonca@serebo.com"), "cc" => array())
        );
        return $statusList[$status];
    }

	public static function getPaymentByStatusFromWorkflow($row, $forceKeyword = "")
	{
		$currentKeyword = $row['keyword_pst'];
		$keyword = $currentKeyword;
		if($forceKeyword != "")
			$keyword = $forceKeyword;
		switch($keyword)
		{
			//First budget stage
			case 'schedule':
			case "ready_to_send":
			case "already_sent":
			case "rectify_design":
			case "rectify_illustration":
			case "rd_stakes":
			case "rd_digitization":
			case "rd_drawing":
			case "ri_digitization":
			case "ri_drawing":
			case "canceled":
				$budget = $row['schedule_design_budget'];
				if($row['schedulee_tentative_total_budget'] != null && $row['schedulee_tentative_total_budget'] > 0)
				{
					$budget = $row['schedulee_tentative_total_budget'];
				}
				break;
			//Second budget stage
			case 'approved':
			case "assign_to":
			case "in_progress":
			case "paused":
			case "stopped":
			case "completed":
			case "project_energized":
			case "as_built":
				$budget = $row['total_approved'];
				break;
			//Third budget stage - This stage search budgets in payments orders, then if does not exist use get the budgets from conciliation shipments
			case "conciliation_reception":
            case 'conciliation_shipment':
			case "cre_return_order":
			case "project_return_materials":
			case "project_real_budget_confirmation":
				$budget = $row['payment_order_registered_total_real_budget'];
				break;
			default:
				$budget = 0;
		}
		return $budget;
	}

    /**
     * Validate materials to be saved in summary list
     */
    public function validate_summary_materials()
    {
        $formData = $this->input->post();
        $summary = array_values($formData['summary']);
        $i = 1;
        $errors = [];
        foreach($summary as $row)
        {
            $quantity = str_replace(',','',$row['quantity']);
			$quantity = floatval($quantity);
            try
            {
                if($i == 1)
                {
                    Assert::lazy()
                    ->that($row, 'Material')->keyExists('id')
                    ->that($row, 'Material')->keyExists('status')
                    ->that($row, 'Material')->keyExists('tension')
                    ->verifyNow();
                Assert::lazy()->that($row['id'], 'ID')
                    ->notEmpty()
                    ->notBlank()
                    ->numeric()
                    ->that($row['status'], 'estado')
                    ->notEmpty()
                    ->notBlank()
                    ->numeric()
                    ->that($row['tension'], 'tension')
                    ->notEmpty()
                    ->notBlank()
                    ->numeric()
                    ->that($row['quantity'], 'Cantidad')
                    ->notEmpty()
                    ->notBlank()
                    ->numeric()
                    ->between(1,1,"El material {$row['code']} excede su limite")
                    ->verifyNow();
                }
                else
                {
                    Assert::lazy()
                    ->that($row, 'Material')->keyExists('id')
                    ->that($row, 'Material')->keyExists('status')
                    ->that($row, 'Material')->keyExists('tension')
                    ->verifyNow();
                    Assert::lazy()->that($row['id'], 'ID')
                        ->notEmpty()
                        ->notBlank()
                        ->numeric()
                        ->that($row['status'], 'estado')
                        ->notEmpty()
                        ->notBlank()
                        ->numeric()
                        ->that($row['tension'], 'tension')
                        ->notEmpty()
                        ->notBlank()
                        ->numeric()
                        ->that($row['quantity'], 'tension')
                        ->notEmpty()
                        ->notBlank()
                        ->numeric()
                        ->verifyNow();
                }
                
            }
            catch(LazyAssertionException $e) 
            {
                $message = "In position {$i} ".$e->getMessage();
                $errors[$i] = nl2br($message);
            }
            $i++;
        }

        
        $response = TRUE;
        if(count($errors) > 0)
        {//dd($errors);
            $this->form_validation->set_message('validate_summary_materials', implode("<br>",$errors) );
            $response = FALSE;
        }
        return $response;
    }
}

class PrivateController extends PublicController
{
    /**
     * @var Model_User
     */
    protected $sessionUser;
    protected $_projectSystems;

    public function __construct()
    {
        parent::__construct();
        //Add General Components
        $this->complementHandler = new ComplementHandler();
        $this->_tabTitle = "Panel";

        $this->complementHandler->addViewComplement("jquery");
        $this->complementHandler->addViewComplement("bootstrap");
        $this->complementHandler->addViewComplement('moment-with-locales');
        $this->complementHandler->addViewComplement("metisMenu");
        $this->complementHandler->addViewComplement("font-awesome");
        $this->complementHandler->addViewComplement("sb-admin-2");
        $this->complementHandler->addViewComplement("toastr");
        $this->complementHandler->addViewComplement('bootbox');
        $this->complementHandler->addViewComplement('sweet-alert2');
        $this->complementHandler->addViewComplement("handlebars");
        $this->complementHandler->addViewComplement("handlebars.custom.helpers");
        $this->complementHandler->addViewComplement("font-awesome");
        $this->complementHandler->addViewComplement('select2');
        $this->complementHandler->addProjectCss('general-custom-style', TRUE);
        $this->complementHandler->addViewComplement("jquery.blockui");
        $this->complementHandler->addProjectJs('IncidentHandler');
        $this->complementHandler->addProjectJs('general-scripts', TRUE);
        $this->_projectSystems = array(
            1 => "Sistema Santa Cruz",
            2 => "Sistema Velasco",
            3 => "Sistema Misiones",
            4 => "Sistema Camiri",
            5 => "Sistema German bush",
            6 => "Sistema Robore",
            7 => "Sistema Valles"
        );
        $this->_validateSession();
    }

    protected function _loadPanelView($contentView, $contentData = array())
    {
        $contentData["complementHandler"] = $this->complementHandler;
        $contentData["contentView"] = $contentView;
        $contentData["sessionUser"] = $this->sessionUser;
        $contentData["tabTitle"] = $this->_tabTitle;
        $contentData["isSuperAdmin"] = $this->_is("super_admin");
        $contentData["isFiscal"] = $this->_is("fiscal");
        $contentData["showProjectQuickSearch"] = $this->_validateFeature('project_quick_search', TRUE);
        $contentData['showEditButton'] = $this->_validateFeature('project_edit', TRUE);
        $contentData['showAddProgressButton'] = $this->_validateFeature('project_manpower', TRUE);
        $contentData['showAssignProjectButton'] = $this->_validateFeature('project_status_ready_to_assign', TRUE);
        $contentData['showStatusManagementButton'] = $this->_validateFeature('project_status_management', true);
        $featureList = unserialize($this->sessionUser->featureList);
        $treeFeatureHtml = Model_feature::drawTreeHtml(NULL,$featureList,array());
        $contentData["treeFeatureHtml"] = $treeFeatureHtml;
        $contentData["magicLogin"] = $this->magicLoginEncryption($this->sessionUser->id);

        $this->load->view($this->_panelTmpl."/panel/master/master", array("contentData" => $contentData));
    }

    private function _validateSession()
    {
        
        if ($this->session->has_userdata("authenticated") && $this->session->userdata("authenticated") === 1)
        {
            $this->sessionUser = $this->session->userdata("sessionUser");
            $user = Model_user::getById($this->sessionUser->id);

            if (is_null($user->getPassword()) || empty($user->getPassword())) {
                $this->session->sess_destroy();
                $this->session->set_flashdata("errorMessage","Your session has expired!");
                redirect(base_url("Login"));
            }
        }
        else
        {
            // Store the requested URI (e.g., "dashboard/settings") in session
            $this->session->set_userdata('redirect_url', uri_string());
            $this->session->set_flashdata("errorMessage","Your session has expired!");
            redirect(base_url("Login"));
        }
    }

    protected function _validateFeature_deprecated($securityString)
    {
        $featureList = unserialize($this->sessionUser->featureList);
        $key = array_search($securityString, array_column($featureList, 'securitystring_fes'));

        if($key === FALSE)
        {
            if($this->input->is_ajax_request())
            {
                $response["success"] = 0;
                $response["message"] = "Permission denied!";
                echo json_encode($response);exit;
            }
            else{
                $this->session->set_flashdata("errorMessage", "Permission denied!");
                redirect(base_url("panel/Home"));
            }
        }
    }

    protected function _validateFeature($securityString, $binaryResponse = FALSE)
    {
        $featureList = unserialize($this->sessionUser->featureList);
        $key = array_search($securityString, array_column($featureList, 'securitystring_fes'));

        if(!$binaryResponse)
        {
            if($key === FALSE)
            {
                if($this->input->is_ajax_request())
                {
                    $response["success"] = 0;
                    $response["message"] = "Access denied!!!!";
                    echo json_encode($response);exit;
                }
                else{
                    $this->session->set_flashdata("errorMessage", "Access denied!");
                    redirect(base_url("panel/Home"));
                }
            }
        }
        else
        {
            $response = $key === FALSE?$key:TRUE;
            return +$response;
        }
    }

    protected function _is($roleKeyWord)
    {
        $roleList = unserialize($this->sessionUser->roleList);
        $response = array_search($roleKeyWord, $roleList);
        if($response !== FALSE)
        {
            $response = TRUE;
        }
        return +$response;
    }

    public static function getSessionUser()
    {
        $ci = &get_instance();
        $currentUser = NULL;
        if ($ci->session->has_userdata("authenticated") && $ci->session->userdata("authenticated") === 1)
        {
            $currentUser = $ci->session->userdata("sessionUser");
        }
        return $currentUser;
    }

    public static function getWorkflowColumns()
    {
        $columnList = array(
            "code_pro" => "CODIGO",
            "initial_contract_number_con" => "CONTRATO",
            "work_area_pro" => "AREA DE TRABAJO",
            "detail_pro" => "DETALLE DEL PROYECTO",
            "production_percentage" => "CONSTRUCCION - % FISICO",
            "detail_inc" => "DETALLE - INCIDENCIA",
            "status_name_pst" => "ESTADO",
            "project_percentage_pro" => "PROGRESO GENERAL",
            "status_log_manual_entry_date" => "INGRESO EN STATUS",
            "static_days" => "DIAS ESTATICO",
            "entry_date_pro" => "FECHA INGRESO",
            "folder_date_pro" => "FECHA CARPETA",
            "cre_fiscal_pro" => "FISCAL DE CRE",
            "system_pro" => "SISTEMA",
            "management_by_pro" => "ADMINISTRADO POR",
            "address_pro" => "DIRECCION",
            "points_pro" => "PUNTOS",
            "distance_pro" => "DISTANCIA",
            "quality_level_pro" => "NIVEL DE CALIDAD",
            "budgetary_position_pro" => "POSICION PRESUPUESTARIA",
            "cre_design_completion_date_pro" => "FECHA COMPLETADO DE DISEÑO",
            "cre_building_completion_date_pro" => "FECHA COMPLETADO DE CONSTRUCCION",            
            "stake_date" => "FECHA DE ESTAQUEADO",
            "stake_responsible" => "RESPONSABLES DE ESTAQUEADO",
            "digitization_points_quantity" => "PUNTOS DIGITALIZADOS",
            "digitization_distance" => "DISTANCIA DIGITALIZADA",
            "rd_digitization_points_quantity" => "PUNTOS RECTIFICADOS EN DIGITALIZACION",
            "rd_digitization_distance" => "DISTANCIA RECTIFICADA EN DIGITALIZACION",
            "returned_date" => "NO FACTIBLE - DEVUELTO A CRE",
            "digitization_date" => "FECHA DIGITALIZACION",
            "drawing_date" => "FECHA DIBUJO",
            "schedule_date" => "FECHA DEFINICION DE CRONOGRAMA",
            "schedule_start" => "FECHA CRONOGRAMA INICIO",
            "schedule_end" => "FECHA CRONOGRAMA FIN",
            "schedule_design_budget" => "CRONOGRAMA - IMPORTE DISEÑO",
            "already_sent_date" => "FECHA PROYECTO ENVIADO A CRE",
            "approved_date" => "FECHA APROBACION",
            "canceled_date" => "FECHA CANCELADO",
            "rectify_design_date" => "FECHA RECTIFICACION DISEÑO",
            "rectify_illustration_date" => "FECHA RECTIFICACION ILUSTRACION",
            "design_budget" => "IMPORTE - DISEÑO",
            "building_budget" => "IMPORTE - CONSTRUCCION",
            "transportation_budget" => "IMPORTE - TRANSPORTE",
            "live_line_budget" => "IMPORTE - LINEA VIVA",
            "right_of_way_budget" => "IMPORTE - DERECHO DE VIA",
            "total_approved" => "TOTAL IMPORTE APROBADO",
            "record_building_materials_date" => "FECHA GRABADO DE MATERIALES",
            "get_materials_date" => "FECHA RETIRO DE MATERIALES",
            "deliver_materials_date" => "FECHA MATERIALES A CONSTRUCCION",
            "materials_reception_date" => "FECHA RECEPCION DE MATERIALES DE CONSTR.",
            "assign_to_date" => "FECHA ASIGNACION DE RESPONSABLES CONSTR.",
            "live_line_assigned" => "LINEA VIVA",
            "power_down_assigned" => "CORTE",
            "maneuver_assigned" => "MANIOBRA",
            "builder_responsible" => "RESPONSABLE CONSTRUC.",
            "fiscal_responsible" => "RESPONSABLE FISCAL",
            "start_date_assigned" => "INICIO DE OBRA EN ASIGNACION",
            "end_date_assigned" => "FIN DE OBRA EN ASIGNACION",
            "estimated_time_assigned" => "DIAS ESTIMADOS EN ASIGNACION",
            "in_progress_date" => "FECHA INICIO DE CONSTRUC.",
            "completed_date" => "CONSTRUCCION COMPLETADA",
            "energized_pro" => "ENERGIZADO",
            "project_energized_entry_date" => "FECHA DE ENERGIZADO",
            "paused_date" => "FECHA DE PAUSA DE CONSTRUC",
            "percentage_paused" => "% DE PAUSA",
            "stopped_date" => "FECHA DE CONSTRUCCION DETENIDA",
            "percentage_stopped" => "% DE CONTRUC. DETENIDA",
            "as_built_date" => "FECHA DE ENVIO DE AS BUILT",
            "as_built_points_quantity" => "AS BUILT - PUNTOS",
            "as_built_distance" => "AS BUILT - DISTANCE",
            "conciliation_reception_date" => "FECHA RECEPCION DE CONCILIACION",
            "conciliation_shipment_date" => "FECHA ENVIO DE CONCILIACION",
            "cre_return_order_date" => "ORDEN DE DEVOLUCION DE MATERIALES",
            "project_return_materials_date" => "CONFIRMACION DE DEVOLUCION DE MATERIALES",
            "payment_order_registered_date" => "FECHA DE REGISTRO DE ORDEN DE PAGO",
            "payment_order_registered_order_number" => "NRO ORDEN DE PAGO",
            "payment_order_registered_design_budget" => "IMPORTE REAL - DISEÑO",
            "payment_order_registered_transportation_budget" => "IMPORTE REAL - TRANSPORTE",
            "payment_order_registered_live_line_budget" => "IMPORTE REAL - LINEA VIVA",
            "payment_order_registered_building_budget" => "IMPORTE REAL - CONSTRUCCION",
            "payment_order_registered_right_of_way_budget" => "IMPORTE REAL - DERECHO DE VIA",
            "payment_order_registered_total_real_budget" => "IMPORTE REAL - TOTAL",
            "payment_order_registered_invoice_number" => "NRO FACTURA",
            "payment_order_invoice_sent_date" => "FECHA DE ENVIO DE FACTURA",
            "payment_order_has_been_settled_date" => "FECHA DE LIQUIDACION",
            "project_manager_assigned" => "ENCARGADO DEL PROYECTO",
			"payment_status" => "ESTADO DEL PAGO",
			"project_return_materials2_date" => "FECHA DE DEVULUCION DE MATERIALES A CRE",
			"in_progress_first_detail_date" => "1RA. FECHA DE INICIO DE CONSTRUC.",
			'ready_to_send_date' => "POR ENVIAR A CRE - FECHA",
			'project_current_budget' => "IMPORTE ACTUAL DEL PROYECTO",
			'production_total_bs' => "PRODUCCION ACTUAL DEL PROYECTO",
            // 'payment_order_registered_contract_number' => "CONTRATO FINAL",
            'final_contract_number_con' => "CONTRATO FINAL",
            'minor_enlargement' => 'AMPLIACION MENOR',
            'trim_tree' => 'PODA'
        );
        return $columnList;
    }

    /**
     * Centralized mapping between the OLD WorkflowPaginationHandler filter
     * keys (the ones _additionalParameters() used to understand, like
     * 'code-list', 'status-keyword', etc.) and the query param names that
     * Serebo2's API (GET /api/v1/workflows) expects.
     *
     * Every place in Serebo that used to call the local WorkflowPaginationHandler
     * and now calls WorkflowApiClient instead should pull this map from here,
     * instead of redeclaring the same array locally. That way, if a new filter
     * is added to Serebo2's API in the future, it only needs to be added once.
     *
     * Usage:
     *   $filterMap = PrivateController::serebo2ApiParamNames();
     *   foreach ($filterMap as $oldKey => $newKey)
     *   {
     *       if (isset($additionalParameters[$oldKey]) && $additionalParameters[$oldKey] !== '')
     *       {
     *           $queryParams[$newKey] = $additionalParameters[$oldKey];
     *       }
     *   }
     *
     * @return array
     */
    public static function serebo2ApiParamNames()
    {
        $filterMap = [
            'code-list'                          => 'code_list',
            'id-list'                             => 'id_list',
            'status-keyword'                      => 'keyword',
            'status'                               => 'status',
            'contract-id'                          => 'contract_id',
            'system'                               => 'system',
            'management-by'                       => 'management_by',
            'work-area'                            => 'work_area',
            'fiscal-responsible-id'                => 'fiscal_id',
            'builder-responsible-id'               => 'builder_id',
            'manpower-uploaded'                    => 'manpower_uploaded',
            'trim-tree'                            => 'trim_tree',
            'has-location'                         => 'has_location',
            'quantity-picked-up-from-cre'          => 'quantity_picked_up_from_cre',
            'quantity-pending-in-cre'              => 'quantity_pending_in_cre',
            'all-materials-picked-up-from-cre'     => 'all_materials_picked_up_from_cre',
            'none-materials-picked-up-from-cre'    => 'none_materials_picked_up_from_cre',
            'keyword'                              => 'keyword',
            'year'                                 => 'year',
            'month'                                => 'month',
        ];
        return $filterMap;
    }

	public function testMailServer($to = 'jair@twiiti.com')
	{
		$ci = &get_instance();
//		$ci->load->library('encrypt');
		$emailHandler = new EmailHandler();
		$email = $emailHandler->initialize();
		$config = $emailHandler->getConfig();
		$email->from(EmailHandler::getSender(), 'Serebo admin');
		$email->reply_to('info@serebo.com', 'Serebo admin');
		$email->to($emailHandler->getEmailByEnvironment($to));
		$email->subject("Prueba de servidor de correos");
//		$message = json_encode($config);
		$data['config'] = $config;
		$data['emailFrom'] = EmailHandler::getSender();
		$message = $ci->load->view("default-template/panel/email-template/test-mail-server", $data, true);
		$email->message($message);
//		$ci->load->view("public/t1/email-template/test-mail-server", $data);
		try
		{
			if($email->Send())
			{
				$sendMessageResponse['success'] = 1;
				$sendMessageResponse['message'] = "Notice sent successfully.";
			}
			else
			{
				$sendMessageResponse['success'] = 0;
				$sendMessageResponse['message'] = "Something went wrong!";
			}
		}
		catch (Exception $e)
		{
			$sendMessageResponse['success'] = 0;
			$sendMessageResponse['message'] = "Internal server error, please try again.";
		}
		$data['sendMessageResponse'] = $sendMessageResponse;
		$ci->load->view("default-template/panel/email-template/test-mail-server", $data);
//		echo "<pre>"; $sendMessageResponse;exit;
	}

    public function magicLoginEncryption($userId)
    {
        // Store a string into the variable which
        // need to be Encrypted
        $simple_string = 'abc';
        
        // Display the original string
        // dump("Original String: " . $simple_string);
        
        // Store cipher method
        $ciphering = "AES-256-CBC";
        
        // Use OpenSSl encryption method
        $iv_length = openssl_cipher_iv_length($ciphering);
        $options = 0;
        
        // Use random_bytes() function which gives
        // randomly 16 digit values
        $encryption_iv = random_bytes($iv_length);
        
        // Alternatively, we can use any 16 digit
        // characters or numeric for iv
        $encryption_key = openssl_digest(php_uname(), 'MD5', TRUE);
        
        // Encryption of string process starts
        $encryption = openssl_encrypt($simple_string, $ciphering, $encryption_key, $options, $encryption_iv);
        $encryption = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($encryption));
        return $encryption;
        // Display the encrypted string
        // dump("Encrypted String: " . $encryption);
        
        // Decryption of string process starts
        // Used random_bytes() which gives randomly
        // 16 digit values
        // $decryption_iv = random_bytes($iv_length);
        
        // Store the decryption key
        // $decryption_key = openssl_digest(php_uname(), 'MD5', TRUE);
        
        // Descrypt the string
        // $decryption = openssl_decrypt (urldecode($encryption), $ciphering, $decryption_key, $options, $encryption_iv);
        
        // Display the decrypted string
        // dump("Decrypted String: " . $decryption);
    }

    public static function updateWorkflow($projectIds)
    {
        $client = new Client(['base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL')]);
        $apiResponse = $client->request('POST', 'api/v1/workflows',[
            'form_params' => [
                "projects" => $projectIds,
            ]
        ]);
    }
}
