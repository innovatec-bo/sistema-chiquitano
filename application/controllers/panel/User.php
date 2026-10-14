<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 10/1/2018
 * Time: 2:01 PM
 */


class User extends PrivateController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $this->_validateFeature('user_index');
        $this->complementHandler->addViewComplement("jquery.datatables");
        $this->complementHandler->addViewComplement("bootbox");
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
        $this->complementHandler->addProjectCss('user.index');
        $this->complementHandler->addProjectJs('user.index');
        $this->_loadPanelView("user/index");
    }

    public function add()
    {
        $this->_validateFeature('user_add');
        /** View complements */
        $this->complementHandler->addViewComplement("parsley");
        /** Server Side Validations **/
        $this->form_validation->set_rules('first-name', 'Email', 'trim|required');
        $this->form_validation->set_rules('last-name', 'Email', 'trim|required');
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email|callback_unique_email');
        $this->form_validation->set_rules('roles[]', 'Roles', 'callback_validate_roles');
        $this->form_validation->set_rules('password', 'Password', 'trim|required');
        $this->form_validation->set_rules('confirm-password', 'Confirm password', 'trim|required|matches[password]');

        $roleList = Model_role::getAll(100,0);
        $responsibleGroupList = Model_status_responsible::getStatusSetByResponsibleGroup();
        $data["roleList"] = $roleList;
        $data["responsibleGroupList"] = $responsibleGroupList;

        if($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("user/add",$data);
        }
        else
        {
            $formData = $this->input->post();
            $responsibleGroup = $formData["responsible-group"]??[];
            $firstName = $formData["first-name"];
            $lastName = $formData["last-name"];
            $email = $formData["email"];
            $password = $formData["password"];
            $userRoleList = $formData["roles"];
            $user = new Model_user(
                $firstName,
                $lastName,
                $email,
                NULL,
                NULL,
                $this->_encryptPassword($password)
            );
            $user->save();
            Model_user_role::saveUserRoleList($user->getId(), $userRoleList, $this->sessionUser);
            if(count($responsibleGroup) > 0)
            {
                foreach ($responsibleGroup as $group) {
                    Model_status_responsible::saveUserResponsible($user->getId(),$group);
                }
            }
            $this->session->set_flashdata("successMessage", "User was added successfully");
            redirect(base_url("panel/User"));
        }
    }

    public function edit($userId = NULL)
    {
        $this->_validateFeature('user_edit');
        $this->_formEditUser($userId);
    }

    public function myProfile()
    {
        $this->_validateFeature('user_profile');
        $this->_formEditUser($this->sessionUser->id);
    }

    private function _formEditUser($userId = NULL)
    {
    	/** @var Model_user $user */
        $user = $this->_validateObjectToEdit($userId,"Model_user","panel/User");
        /** View complements */
        $this->complementHandler->addViewComplement("parsley");
		$this->complementHandler->addViewComplement("jquery.inputmask.bundle");
		$this->complementHandler->addProjectJs("user.edit");
		$isSuperAdmin = $this->_is("super_admin");
        /** Server Side Validations **/
        $this->form_validation->set_rules('first-name', 'Email', 'trim|required');
        $this->form_validation->set_rules('last-name', 'Email', 'trim|required');
        if($isSuperAdmin == 1)
        	$this->form_validation->set_rules('roles[]', 'Roles', 'callback_validate_roles');
        $this->form_validation->set_rules('password', 'Password', 'trim');
        $this->form_validation->set_rules('confirm-password', 'Confirm password', 'trim|matches[password]');

        $roleList = Model_role::getAll(100,0);
        $userRoleList = Model_role::getByUserId($user->getId());
        $data["roleList"] = $roleList;
        $data["user"] = $user->toArray();
        $data["userRoleList"] = $userRoleList;
        $responsibleGroupList = Model_status_responsible::getStatusSetByResponsibleGroup();
        $data["responsibleGroupList"] = $responsibleGroupList;
        $userResponsibleCurrentData = Model_status_responsible::getUsersResponsible("", $user->getId());

        // Un grupo se considera marcado si el usuario tiene al menos uno de sus estados activos
        if ($this->input->post()) {
            $checkedGroups = $this->input->post("responsible-group") ?? [];
        } else {
            $currentKeywords = array_column($userResponsibleCurrentData, "keyword_pst");
            $checkedGroups = [];
            foreach ($responsibleGroupList as $group => $statuses) {
                if (count(array_intersect($statuses, $currentKeywords)) > 0) {
                    $checkedGroups[] = $group;
                }
            }
        }
        $data["checkedGroups"] = $checkedGroups;

        // dd($responsibleGroupList, $userResponsibleCurrentData);
        //TODO:this variable is passed to define weather show or not the role section, would be handled as functionality.
        $data["isSuperAdmin"] = $isSuperAdmin;
        if($this->form_validation->run() === FALSE)
        {
            $this->_loadPanelView("user/edit",$data);
        }
        else
        {
            $formData = $this->input->post();
            $firstName = $formData["first-name"];
            $lastName = $formData["last-name"];
            $umbo = str_replace(",","",$formData["umbo"]);
            $roleListToSave = $formData["roles"];
            $responsibleGroup = $formData["responsible-group"] ?? [];
            $user->setFirstName($firstName);
            $user->setLastName($lastName);
            $user->setUMBO($umbo);
            if(isset($formData["update-password"]))
            {
                $password = $formData["password"];
                $passwordEncrypted = $this->_encryptPassword($password);
                $user->setPassword($passwordEncrypted);
            }
            $user->save();
            if(count($roleListToSave) > 0)
            {
                Model_user_role::saveUserRoleList($user->getId(), $roleListToSave, $this->sessionUser);
            }
           
            if($isSuperAdmin == 1)
            {
                $responsibleGroup = $formData["responsible-group"] ?? [];
                Model_status_responsible::syncUserResponsible(
                    $user->getId(),
                    $responsibleGroup,
                    $this->sessionUser->id
                );
            }

            $user->disparar_sincronizacion_roles_a_laravel();
            $this->session->set_flashdata("successMessage", "User was updated successfully.");
			redirect(current_url());

        }
    }

    public function unique_email($email)
    {
        $user = Model_user::getByEmail($email);
        //If the user exist then notice to user that request the signup
        $response = TRUE;
        if($user instanceof Model_user)
        {
            $this->form_validation->set_message('unique_email', 'The email {field} already exist.');
            $response = FALSE;
        }
        return $response;
    }

    public function validate_roles()
    {
        $formData = $this->input->post();
        $roles = $formData['roles'];
        $arrayRoleList = array();
        $roleList = Model_role::getAll(100,0);
        foreach ($roleList as $role)
        {
            $arrayRoleList[] = (array)$role;
        }
        $validRoleListIds = array_column((array)$arrayRoleList,'id_rol');
        $quantityValidIds = 0;
        foreach ($roles as $roleId)
        {
            if(array_search($roleId, $validRoleListIds) !== FALSE)
            {
                $quantityValidIds++;
            }
        }
        $response = TRUE;
        if($quantityValidIds != count($roles))
        {
            $this->form_validation->set_message('validate_roles', 'You need to adds valid roles');
            $response = FALSE;
        }
        return $response;
    }

    public function testSave()
    {
        $user = new Model_user(
            "new",
            "user2",
            "nuser2@mailinator.com",
            NULL,
            NULL,
            ""
        );
        $user->save();
    }

    public function delete($userId = NULL)
    {
        $this->_validateFeature("delete_user");
        $user = $this->_validateObjectToEdit($userId,"Model_user","panel/User");
        $user->delete();
        $this->session->set_flashdata("successMessage", "Usuario eliminado!");
        redirect(base_url("panel/User"));
    }
}
