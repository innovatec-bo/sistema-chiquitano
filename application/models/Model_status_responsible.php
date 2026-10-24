<?php
/**
 * Created by PhpStorm.
 * User: Jair
 * Date: 17/07/2018
 * Time: 04:01 PM
 */

class Model_status_responsible extends Model_status_responsible_base
{
    public function __construct($userId = "", $statusId = "", $active = TRUE)
    {
        parent::__construct($userId, $statusId, $active);
    }

    public static function getUsersResponsible($keyword = "", $userId = NULL)
    {
        $ci = &get_instance();
        $ci->load->database();
		$keywordFilter = "";
        if($keyword != "")
        {
            $keywordFilter = " and keyword_pst = ".$ci->db->escape($keyword);
        }
        $userIdFilter = "";
		if(!is_null($userId))
		{
			$userIdFilter = " and id_usr = ".$ci->db->escape($userId);
		}

        $sql = "
            SELECT
                sec_users.*,
                id_sre,
                id_pst,
                status_name_pst,
                keyword_pst
            FROM
            wfl_status_responsibles
            LEFT JOIN sec_users on user_id_sre = id_usr
            LEFT JOIN wfl_project_status on status_id_sre = id_pst
            WHERE 
            deleted_usr != 1
            and deleted_sre != 1
            and active_sre = 1
            ".$keywordFilter."
            ".$userIdFilter."
        ";
        $query = $ci->db->query($sql);
        $result = $query->result_array();
        return $result;
    }

    public static function getResponsibleDetailListByStatusKeyword($statusKeyword, $roleKeyword)
    {
        $ci = &get_instance();
        $ci->load->database();

        $roleKeywordSql = "";
        foreach ($roleKeyword as $keyword)
        {
            $roleKeywordSql .= $ci->db->escape($keyword).', ';
        }

        $roleKeywordSql = substr($roleKeywordSql, 0, -2);

        $sql = "
        SELECT
            id_sre,
            keyword_rol,
            sec_users.*
        FROM
            wfl_status_responsibles
        LEFT JOIN sec_users on user_id_sre = id_usr
        LEFT JOIN wfl_project_status on id_pst = status_id_sre
        LEFT JOIN (
            SELECT 
                userid_uro,
                keyword_rol
            FROM sec_userroles 
            LEFT JOIN sec_roles on id_rol = roleid_uro
            WHERE keyword_rol in (".$roleKeywordSql.") and deleted_uro != 1
        ) role on role.userid_uro = id_usr
        where keyword_pst = ".$ci->db->escape($statusKeyword)." and role.keyword_rol is not null and active_sre = 1
        ";
//echo"<pre>";var_dump($sql);exit;
        $query = $ci->db->query($sql);
        $result = $query->result_array();
        return $result;
    }


    public static function getStatusSetByResponsibleGroup(string $responsibleGroup = "")
    {
        //Responsible on creation
        $arrayStatus["creation_process"][] = "project_has_been_created";
        
        //Responsible on design
        $arrayStatus["design_process"][] = "design";
        $arrayStatus["design_process"][] = "rectify_design";
        $arrayStatus["design_process"][] = "rectify_illustration";
        $arrayStatus["design_process"][] = "returned";
        $arrayStatus["design_process"][] = "schedule";

        //Responsible on stakes
        $arrayStatus["stakes_process"][] = "stakes";
        $arrayStatus["stakes_process"][] = "rd_stakes";

        //Responsible on drawing
        $arrayStatus["drawing_process"][] = "drawing";
        $arrayStatus["drawing_process"][] = "rd_drawing";
        $arrayStatus["drawing_process"][] = "ri_drawing";

        //Responsible on approvement
        $arrayStatus["approvement_process"][] = "ready_to_send";
        $arrayStatus["approvement_process"][] = "already_sent";
        $arrayStatus["approvement_process"][] = "approvement";

        //Responsible on digitization
        $arrayStatus["digitization_process"][] = "digitization";
        $arrayStatus["digitization_process"][] = "rd_digitization";
        $arrayStatus["digitization_process"][] = "ri_digitization";

        //Responsible on building process
        $arrayStatus["building_process"][] = "assign_to";
        $arrayStatus["building_process"][] = "building";
        $arrayStatus["building_process"][] = "ready_to_start";
        $arrayStatus["building_process"][] = "in_progress";
        $arrayStatus["building_process"][] = "stopped";
        $arrayStatus["building_process"][] = "paused";
        $arrayStatus["building_process"][] = "completed";
        $arrayStatus["building_process"][] = "as_built";
        $arrayStatus["building_process"][] = "conciliation_reception";
        $arrayStatus["building_process"][] = "conciliation_shipment";
        $arrayStatus["building_process"][] = "cre_return_order";
        $arrayStatus["building_process"][] = "project_return_materials";

        //Responsible on warehouse process
        $arrayStatus["warehouse_process"][] = "warehouse";
        $arrayStatus["warehouse_process"][] = "record_building_materials";
        $arrayStatus["warehouse_process"][] = "get_materials";
        $arrayStatus["warehouse_process"][] = "deliver_materials";
        $arrayStatus["warehouse_process"][] = "return_materials";

        return $arrayStatus[$responsibleGroup] ?? $arrayStatus;
    }

    public static function saveUserResponsible($userId, $responsibleGroup)
    {
        $statusKeywordList = static::getStatusSetByResponsibleGroup($responsibleGroup);
        $statusList = Model_project_status::getByStatusKeywordList($statusKeywordList);
        foreach ($statusList as $status)
        {
            $statusResponsible = new Model_status_responsible($userId,$status->getId());
            $statusResponsible->save();
        }
    }

    public static function getResponsiblesByStatusId()
    {
        $sql = "
        SELECT
            id_sre,	
            firstname_usr,
            lastname_usr,
            id_pst,
            status_name_pst	
        FROM
            wfl_status_responsibles
        LEFT JOIN sec_users on user_id_sre = id_usr
        LEFT JOIN wfl_project_status on status_id_sre = id_pst
        where deleted_sre != 1 and active_sre = 1
        order by order_pst
        ";
    }

    public static function syncUserResponsible(int $userId, array $selectedGroups, $editorUserId = NULL)
    {
        $ci = &get_instance();
        $ci->load->database();

        $allGroups = static::getStatusSetByResponsibleGroup();

        // Solo se aceptan grupos que existan (evita valores manipulados en el POST)
        $selectedGroups = array_intersect($selectedGroups, array_keys($allGroups));

        $managedKeywords = array_merge(...array_values($allGroups));
        $desiredKeywords = [];
        foreach ($selectedGroups as $group) {
            $desiredKeywords = array_merge($desiredKeywords, $allGroups[$group]);
        }

        // IDs de todos los estados que administra este formulario, y de los que deben quedar activos
        $managedIds = [];
        foreach (Model_project_status::getByStatusKeywordList($managedKeywords) as $status) {
            $managedIds[] = (int)$status->getId();
        }
        $desiredIds = [];
        if (count($desiredKeywords) > 0) {
            foreach (Model_project_status::getByStatusKeywordList($desiredKeywords) as $status) {
                $desiredIds[] = (int)$status->getId();
            }
        }

        // Filas actuales del usuario (activas e inactivas), agrupadas por estado
        $rows = $ci->db->select("id_sre, status_id_sre, active_sre")
            ->where("user_id_sre", $userId)
            ->where("deleted_sre !=", 1)
            ->get("wfl_status_responsibles")
            ->result_array();
        $rowsByStatus = [];
        foreach ($rows as $row) {
            $rowsByStatus[(int)$row["status_id_sre"]][] = $row;
        }

        $toDeactivate = [];
        $toReactivate = [];
        $toInsert = [];

        // Desactivar lo que ya no está marcado
        foreach ($managedIds as $statusId) {
            if (in_array($statusId, $desiredIds, true)) {
                continue;
            }
            foreach ($rowsByStatus[$statusId] ?? [] as $row) {
                if ((int)$row["active_sre"] === 1) {
                    $toDeactivate[] = $row["id_sre"];
                }
            }
        }

        // Reactivar o insertar lo que sí está marcado
        foreach ($desiredIds as $statusId) {
            $statusRows = $rowsByStatus[$statusId] ?? [];
            if (count($statusRows) === 0) {
                $toInsert[] = $statusId;
                continue;
            }
            $hasActive = false;
            foreach ($statusRows as $row) {
                if ((int)$row["active_sre"] === 1) {
                    $hasActive = true;
                    break;
                }
            }
            if (!$hasActive) {
                $toReactivate[] = $statusRows[0]["id_sre"]; // se reactiva solo una
            }
        }

        $audit = [
            "editedon_sre" => date("Y-m-d H:i:s"),
            "editedby_sre" => $editorUserId,
        ];

        $ci->db->trans_start();
        if (count($toDeactivate) > 0) {
            $ci->db->where_in("id_sre", $toDeactivate)
                ->update("wfl_status_responsibles", ["active_sre" => 0] + $audit);
        }
        if (count($toReactivate) > 0) {
            $ci->db->where_in("id_sre", $toReactivate)
                ->update("wfl_status_responsibles", ["active_sre" => 1] + $audit);
        }
        foreach ($toInsert as $statusId) {
            $statusResponsible = new Model_status_responsible($userId, $statusId);
            $statusResponsible->save();
        }
        $ci->db->trans_complete();

        return $ci->db->trans_status();
    }
}
