<?php
/**
 * Created by PhpStorm.
 * User: asaelko
 * Date: 26.12.14
 * Time: 16:30
 */

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/Controller.php';

class SearchController extends Controller
{
    public function __construct(){
        parent::__contruct();
    }

    public function testBookmarks(Request $request, Application $app) {
        $bookmarks = ["req" => $request->getRequestUri()];
        return $app->json($bookmarks);
    }


    public function findById(Request $request, Application $app){
        if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }
        $device_id = $app["session"]->get("id_list_mobile_devices");
        $table_id = $request->get('table');
        if(empty($table_id) || !is_numeric($table_id)) $table_id = 1;
        $db_sql = $this->db->query("SELECT
            SELECT_SQL
            FROM LIST_MOBILE_TABLES
            WHERE ID = ".$table_id."
        ");

        if(empty($db_sql)){
            return $this->generate_error($app, 404, "Table not found");
        }

        $sql = $db_sql[0]["SELECT_SQL"];

        $fields_to_get = "*";
        if(!empty($device_id)){
            $q = "SELECT LIST(DISTINCT(NAME), ', ') AS FIELD_LIST FROM LIST_MOBILE_DEVICES_FIELDS WHERE
        ID_LIST_MOBILE_TABLES = ".$table_id."
        AND ID_LIST_MOBILE_DEVICES = ".$device_id."
        AND VIEW_TYPE IN (0,1)";

            $res = $this->db->query($q);
            if(!empty($res) && !empty($res[0]["FIELD_LIST"])){
                $fields_to_get = $res[0]["FIELD_LIST"];
                $fields_to_get = "RECORD_ID, ".$fields_to_get;
            }
        }

        $user_id = $this->db->query('SELECT list_users.id as UID, list_mobile_groups.show_all as show_all from list_users
            join list_mobile_users on list_users.id = list_mobile_users.id_list_users
            join list_mobile_devices on list_mobile_devices.id_list_mobile_users = list_mobile_users.id
            join list_mobile_groups on list_mobile_groups.id = list_mobile_users.id_list_mobile_groups
            where list_mobile_devices.access_token = \''.$request->get('access_token').'\'');

        if ($user_id[0]["SHOW_ALL"] == 1) {
            $where_con = " 1 = 1 ";
        } else {
            $where_con = "ID_EXECUTOR=".$user_id[0]["UID"];
        }

        $where_con = " 1 = 1 ";

        $query = "SELECT ".$fields_to_get." FROM (".$sql.") as LIST_TRAFFIC  "." WHERE RECORD_ID = ".$request->get('record_id')." and ".$where_con;

        $res = $this->db->query($query);

        if(count($res)>500){
            return $this->generate_error($app, 404, "Results count is too big");
        }
        $result = array();
        foreach($res as $row){
            $result[] = $row["RECORD_ID"];
        }

        $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, ID_LIST_TABLE as RECORD_ID FROM LIST_DOCUMENTS WHERE LIST_TABLE_NAME = 'LIST_TRAFFIC' AND ID_LIST_TABLE
        IN (".implode(',',$result).") GROUP BY ID_LIST_TABLE";
        $res2 = $this->db->query($query2);

        $response = ["count"=>"1", "access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result" => $res, "documents" => $res2 ];
        return $app->json($response);

    }


    public function bookmarkInnerSearch($bookmark_id, $app) {
        $bookmarks = $this->db->query("SELECT
            JSON_DESC,
            FIELDS_ORDER
            FROM LIST_MOBILE_USER_BOOKMARKS
            WHERE ID = ".$bookmark_id."
        ");
        $bookmark = $bookmarks[0];
        $arrs = split("<<<", $bookmark["FIELDS_ORDER"]);
        $table_orders = split(">", $arrs[0]);
        $single_orders = split(">", $arrs[1]);

        $filter_object_p = json_decode($bookmark["JSON_DESC"], true);

        if(empty($filter_object_p["main_filter"]) && empty($filter_object_p["filters"]) && !empty($app)){
            return $this->generate_error($app, 404, "Nothing to search");
        }
        $table_id = 1;


        //main filter
        $main_filter = iconv('utf-8', 'cp1251', $filter_object_p["main_filter"]);

        //fields filters
        $filters_obj = $filter_object_p["filters"];

        $search_string = "  WHERE 1=1 ";
        $has_date = false;
        foreach($filters_obj as $filter_obj){
            if(!empty($filter_obj["field_type"]) && !empty($filter_obj["field_name"]) && !empty($filter_obj["field_value"])){
                switch($filter_obj["field_type"]){
                    case "date":
                        $has_date = true;
                        if(empty($filter_obj["field_value2"])){
                            $search_string .= " AND ".$filter_obj["field_name"]." = '".$filter_obj["field_value"]."' ";
                        } else {
                            $search_string .= " AND ".$filter_obj["field_name"]." BETWEEN '".$filter_obj["field_value"]."' AND '".$filter_obj["field_value2"]."'";
                        }
                        break;
                    case "string":
                        if ($filter_obj["field_name"] != "ID_STATUS_EX2")
                            $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                        else
                            $tmpval = $filter_obj["field_value"];
                        $search_string .= " AND UPPER(".$filter_obj["field_name"].") LIKE UPPER('%".$tmpval."%')";
                        break;
                    case "dict":
                        if ($filter_obj["field_name"] != "ID_STATUS_EX2")
                            $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                        else
                            $tmpval = $filter_obj["field_value"];
                        if (!(($filter_obj["field_name"] == "ID_STATUS_EX") && ($tmpval == iconv('utf-8', 'cp1251', "Ожидаются"))))
                            $search_string .= " AND POSITION(UPPER(CAST('".$tmpval."' AS VARCHAR(1000) CHARACTER SET WIN1251)) IN UPPER(CAST(".$filter_obj["field_name"]." AS VARCHAR(1000) CHARACTER SET WIN1251))) > 0";
                        else {
                            $search_string .= " AND STATUS_ID = 11";
                            $stm = "ok";
                        }
                        //$search_string .= " AND UPPER(CAST(".$filter_obj["field_name"]." AS VARCHAR(1000) CHARACTER SET WIN1251)) = UPPER(CAST('".$tmpval."' AS VARCHAR(1000) CHARACTER SET WIN1251))";
                        break;
                    case "bool":
                        if($filter_obj["field_value"] == true){
                            $search_string .= " AND ".$filter_obj["field_name"]." IS NOT NULL AND ".$filter_obj["field_name"]." <> 0";
                        } else {
                            $search_string .= " AND ".$filter_obj["field_name"]." IS NULL OR ".$filter_obj["field_name"]." = 0";
                        }
                        break;
                }
            }
        }
        if (!$has_date) $search_string .= " AND DT_CREATE > '". date('d.m.y', strtotime('-8 month')) ."' ";
        $db_sql = $this->db->query("SELECT
            SELECT_SQL
            FROM LIST_MOBILE_TABLES
            WHERE ID = ".$table_id."
        ");

        if(empty($db_sql)){
            return $this->generate_error($app, 404, "Table not found");
        }

        $sql = $db_sql[0]["SELECT_SQL"];


        if(!empty($main_filter)){
            $db_fields = $this->db->query("SELECT
                NAME
                FROM LIST_MOBILE_FIELDS
                WHERE ID_LIST_MOBILE_TABLES = ".$table_id."
                AND TYPE = 'string'
            ");

            $fields_array = array();
            $first = true;
            foreach($db_fields as $arr){
                $fields_array[$arr["NAME"]] = $arr;

                if($first){
                    $search_string .= " AND (";
                    $first = false;
                    $search_string .= "(UPPER(COALESCE(".$arr["NAME"].", ''))";
                } else {
                    $search_string .= "||UPPER(COALESCE(".$arr["NAME"].",''))";
                }
            }
            if(!$first){
                $search_string .= " LIKE UPPER('%".$main_filter."%')))";
            }
        }
        $orders = $single_orders;
        //$orders = array_merge($single_orders, array_diff($single_orders, $table_orders));
        foreach ($table_orders as $field_id) {
            if (!in_array($field_id, $single_orders)) {
                $orders[] = $field_id;
            }
        }
        $keys = $this->db->query("SELECT ID,
                NAME
                FROM LIST_MOBILE_FIELDS
                WHERE ID_LIST_MOBILE_TABLES = 1");
        $fields_to_get = "RECORD_ID";
        foreach($orders as $arr){
            foreach ($keys as $key) {
                if ($key["ID"] == $arr) {
                    $fields_to_get = $fields_to_get.", ".$key["NAME"];
                }
            }

        }

        $query = "SELECT ".$fields_to_get." FROM (".$sql.") ".$search_string." ORDER BY TARGET_DATE_ARRIVAL DESC ROWS 250";
        $query_cnt = "SELECT count(*) as b_count FROM (".$sql.") ".$search_string;

        //var_dump($query);
        //exit();

        $res = $this->db->query($query);
        $res_cnt = $this->db->query($query_cnt);
        $ttt = "";
        foreach($res_cnt as $key=>$val){
            $upd_query = "UPDATE LIST_MOBILE_USER_BOOKMARKS SET RECORDS_COUNT =  ".$val["B_COUNT"].", LAST_UPDATE = CURRENT_TIMESTAMP WHERE LIST_MOBILE_USER_BOOKMARKS.ID = ".$bookmark_id;
            $ttt = $this->db->query($upd_query);
        }
        if(count($res)>500){
            return $this->generate_error($app, 404, "Results count is too big");
        }
        $result = array();

        foreach($res as $row){
            $result[] = $row["RECORD_ID"];
        }
        if (!empty($result)) {
            $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, ID_LIST_TABLE as RECORD_ID FROM LIST_DOCUMENTS WHERE LIST_TABLE_NAME = 'LIST_TRAFFIC' AND ID_LIST_TABLE
            IN (" . implode(',', $result) . ") GROUP BY ID_LIST_TABLE";
            $res2 = $this->db->query($query2);
        } else {
            $res2 = [];
        }
        if (empty($app)) return ["bookmark_id" => $bookmark_id, "result" => $res,  "documents" => $res2, "order" => $bookmark["FIELDS_ORDER"]];
        $response = ["r1"=>$ttt, "count" =>count($res), "access_token"=>$app["session"]->get("new_access_token"),"expires_in"=>time()+7*24*60*60, "bookmark_id" => $bookmark_id, "result" => $res,  "documents" => $res2, "order" => $bookmark["FIELDS_ORDER"]];
        return $response;
    }

    public function bookmarkSearch(Request $request, Application $app) {
        //1_2_3_4--1_2_3_4
        $bookmark_id = $request->get('bookmark_id');
        return $app->json($this->bookmarkInnerSearch($bookmark_id, $app));


    }

    public function getDictionaryRecords(Request $request, Application $app) {
        if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }
        $limit = $request->get('limit');
        $offset = $request->get('offset');
        if(empty($limit)){
            $limit = 100;
        }
        if(empty($offset)){
            $offset = 0;
        }
        $rows_offset = " ".($offset + 1)." TO ".($limit + $offset)." ";
        $table_name = $request->get('table_name');
        $fields_sql = $this->db->query("SELECT DISTINCT trim(list_mobile_dictionary_fields.field_key) as field_key, field_type, list_description.field_desc
            from list_mobile_dictionary_fields
            join list_description on (list_mobile_dictionary_fields.field_key = list_description.field_name
            and
            list_mobile_dictionary_fields.table_name = list_description.table_name)
            where list_mobile_dictionary_fields.table_name = '".$table_name."' order by list_description.id");
        $fields = array();
        foreach($fields_sql as $row=>$val){
            $fields[] = trim($val["FIELD_KEY"]);
        }
        $res = $this->db->query("SELECT ".implode(",", $fields)."
            from ".$table_name." where deleted = 0 order by ".$table_name."_NAME ROWS ".$rows_offset);
        $response = ["access_token"=>$app["session"]->get("new_access_token"),"expires_in"=>time()+7*24*60*60, "fields" => $fields_sql, "result" => $res];
        return $app->json($response);
    }



    public function index(Request $request, Application $app){
    	if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }

        $overAllTimer = microtime(true);
        $timer =  microtime(true);
        $execTimes =array();


        $device_id = $app["session"]->get("id_list_mobile_devices");
        $is_custom_bookmark = $request->get('is_custom_bookmark');
        if(empty($is_custom_bookmark)){
            $is_custom_bookmark = false;
        } else {
            $is_custom_bookmark = true;
        }
        $limit = $request->get('limit');
        $offset = $request->get('offset');
        if(empty($limit)){
            $limit = 100;
        }
        if(empty($offset)){
            $offset = 0;
        }
        $rows_offset = " ".($offset + 1)." TO ".($limit + $offset)." ";
        $filter_object = $request->get('filter_object');
        if(empty($filter_object)){
        	return $this->generate_error($app, 404, "Nothing to search");
        }

        $filter_object_p = json_decode($filter_object, true);

        if(empty($filter_object_p["main_filter"]) && empty($filter_object_p["filters"])){
        	return $this->generate_error($app, 404, "Nothing to search");
        }

        $table_id = $request->get('table');
        if(empty($table_id) || !is_numeric($table_id)) $table_id = 1;


        //main filter
        $main_filter = iconv('utf-8', 'cp1251', $filter_object_p["main_filter"]);

        //fields filters
        $filters_obj = $filter_object_p["filters"];

        $search_string = "  WHERE ";
        $where_condition = $this->db->query("SELECT list_users.login, coalesce(list_mobile_groups_tables.where_condition, '1=1') as where_condition  FROM list_mobile_users
                                    LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
                                    left join list_mobile_groups on list_mobile_users.id_list_mobile_groups = list_mobile_groups.id
                                    left join list_mobile_groups_tables on list_mobile_groups_tables.id_list_mobile_groups = list_mobile_groups.id
                                    WHERE list_mobile_groups_tables.id_list_mobile_tables = 1 and list_users.login = '".$request->get('login')."'");

        $execTimesх['$where_condition'] = microtime(true) - $timer;
        $timer = microtime(true);

        $where_condition = $where_condition[0]["WHERE_CONDITION"];
        if (empty($where_condition)) $where_condition = " 1 = 1 ";
        $where_condition = iconv('utf-8', 'cp1251', $where_condition);
        $search_string = $search_string." (".$where_condition.") ";
        $stm = "";
        $has_date = false;
        foreach($filters_obj as $filter_obj){
        	if(!empty($filter_obj["field_type"]) && !empty($filter_obj["field_name"]) && !empty($filter_obj["field_value"])){
        		switch($filter_obj["field_type"]){
        			case "date":
                        $has_date = true;
        				if(empty($filter_obj["field_value2"])){
        					$search_string .= " AND ".$filter_obj["field_name"]." = '".$filter_obj["field_value"]."' ";
        				} else {
        					$search_string .= " AND ".$filter_obj["field_name"]." BETWEEN '".$filter_obj["field_value"]."' AND '".$filter_obj["field_value2"]."'";
        				}
        				break;
        			case "string":
                        if ($filter_obj["field_name"] != "ID_STATUS_EX2")
        				    $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                        else
                            $tmpval = $filter_obj["field_value"];
        				$search_string .= " AND UPPER(".$filter_obj["field_name"].") LIKE UPPER('%".$tmpval."%')";
        				break;
                    case "dict":
                        if ($filter_obj["field_name"] != "ID_STATUS_EX2") {
                            $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                            $tmpval = str_replace("'", "''", $tmpval);
                        }
                        else
                            $tmpval = $filter_obj["field_value"];
                        if (!(($filter_obj["field_name"] == "ID_STATUS_EX") && ($tmpval == iconv('utf-8', 'cp1251', "Ожидаются"))))
                            $search_string .= " AND POSITION(UPPER(CAST('".$tmpval."' AS VARCHAR(1000) CHARACTER SET WIN1251)) IN UPPER(CAST(".$filter_obj["field_name"]." AS VARCHAR(1000) CHARACTER SET WIN1251))) > 0";
                        else {
                            $search_string .= " AND STATUS_ID = 11";
                            $stm = "ok";
                        }
                        //$search_string .= " AND UPPER(CAST(".$filter_obj["field_name"]." AS VARCHAR(1000) CHARACTER SET WIN1251)) = UPPER(CAST('".$tmpval."' AS VARCHAR(1000) CHARACTER SET WIN1251))";
                        break;
        			case "bool":
        				if($filter_obj["field_value"] == true){
							$search_string .= " AND ".$filter_obj["field_name"]." IS NOT NULL AND ".$filter_obj["field_name"]." <> 0";
        				} else {
							$search_string .= " AND ".$filter_obj["field_name"]." IS NULL OR ".$filter_obj["field_name"]." = 0";
        				}
        				break;
        		}
        	}
        }
        if (!$has_date) $search_string2 = " AND LIST_TRAFFIC.DT_CREATE > '". date('d.m.y', strtotime('-8 month')) ."' ";
        $db_sql = $this->db->query("SELECT 
            SELECT_SQL
            FROM LIST_MOBILE_TABLES
            WHERE ID = ".$table_id."
        ");
        $execTimes['$db_sql'] = microtime(true) - $timer;
        $timer = microtime(true);

        if(empty($db_sql)){
        	return $this->generate_error($app, 404, "Table not found");
        }

        $sql = $db_sql[0]["SELECT_SQL"];


        if(!empty($main_filter)){
	        $db_fields = $this->db->query("SELECT
	            NAME 
	            FROM LIST_MOBILE_FIELDS
	            WHERE ID_LIST_MOBILE_TABLES = ".$table_id."
	            AND TYPE = 'string'
	        ");

	        $fields_array = array();
	        $first = true;
	        foreach($db_fields as $arr){
	            $fields_array[$arr["NAME"]] = $arr;

            	if($first){
            		$search_string .= " AND (";
            		$first = false;
            		$search_string .= "(UPPER(COALESCE(".$arr["NAME"].", ''))";
            	} else {
            		$search_string .= "||UPPER(COALESCE(".$arr["NAME"].",''))";
            	}
	        }
	        if(!$first){
	        	$search_string .= " LIKE UPPER('%".$main_filter."%')))";
	        }
	    }
        $execTimes['$db_fields'] = microtime(true) - $timer;
        $timer = microtime(true);



        $bookmarks = array();
        $order = null;
        if ($is_custom_bookmark) {
            $bookmark_id = $request->get('bookmark_id');
            $bookmarks = $this->db->query("SELECT
            FIELDS_ORDER
            FROM LIST_MOBILE_CUSTOM_BOOKMARKS
            WHERE ID = ".$bookmark_id."
            ");

            $execTimes['$bookmarks'] = microtime(true) - $timer;
            $timer = microtime(true);

            $bookmark = $bookmarks[0];
            $arrs = split("<<<", $bookmark["FIELDS_ORDER"]);
            $table_orders = split(">", $arrs[0]);
            $single_orders = split(">", $arrs[1]);
            $orders = $single_orders;
            foreach ($table_orders as $field_id) {
                if (!in_array($field_id, $single_orders)) {
                    $orders[] = $field_id;
                }
            }
            $keys = $this->db->query("SELECT ID,
                NAME
                FROM LIST_MOBILE_FIELDS
                WHERE ID_LIST_MOBILE_TABLES = 1");

            $execTimes['$keys'] = microtime(true) - $timer;
            $timer = microtime(true);

            $fields_to_get = "RECORD_ID";
            foreach($orders as $arr){
                foreach ($keys as $key) {
                    if ($key["ID"] == $arr) {
                        $fields_to_get = $fields_to_get.", ".$key["NAME"];
                    }
                }

            }


            $order = $this->db->query("SELECT FIELDS_ORDER FROM LIST_MOBILE_CUSTOM_BOOKMARKS WHERE ID = ".$bookmark_id);

            $execTimes['$order'] = microtime(true) - $timer;
            $timer = microtime(true);

        } else {
            $fields_to_get = "*";

            if(!empty($device_id)){
                $q = "SELECT LIST(DISTINCT(NAME), ', ') AS FIELD_LIST FROM LIST_MOBILE_DEVICES_FIELDS WHERE
            ID_LIST_MOBILE_TABLES = ".$table_id."
            AND ID_LIST_MOBILE_DEVICES = ".$device_id."
            AND VIEW_TYPE IN (0,1)
            ";

                $res = $this->db->query($q);
                if(!empty($res) && !empty($res[0]["FIELD_LIST"])){
                    $fields_to_get = $res[0]["FIELD_LIST"];
                    $fields_to_get = "RECORD_ID, ".$fields_to_get;
                }

                $execTimes['$res'] = microtime(true) - $timer;
                $timer = microtime(true);
            }
        }


        $q_count = $this->db->query("select count(*) FROM (".$sql.$search_string2.") ".$search_string.""); //ААААААА
        $query = "SELECT ".$fields_to_get." FROM (".$sql.$search_string2.") ".$search_string." ORDER BY TARGET_DATE_ARRIVAL DESC ROWS ".$rows_offset;

        $execTimes['$q_count'] = microtime(true) - $timer;
        $timer = microtime(true);

        //var_dump($sql.$search_string2);
        //exit();

        $res = $this->db->query($query);

        $execTimes['$query'] = microtime(true) - $timer;
        $timer = microtime(true);

        if(count($res)>500){
            return $this->generate_error($app, 404, "Results count is too big");
        }
        $result = array();
        $res2 = array();

        foreach($res as $row){
            $result[] = $row["RECORD_ID"];
        }
        $isc = $request->get('is_custom_bookmark');
        $bms = $request->get('bookmark_id');
        if (empty($isc)) $isc = 0;
        if (empty($bms)) $bms = -1;
        if (!empty($result)) {
            $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, '' as ADD_DOCUMENTS, ID_LIST_TABLE as RECORD_ID FROM LIST_DOCUMENTS WHERE LIST_TABLE_NAME = 'LIST_TRAFFIC' AND ID_LIST_TABLE
            IN (".implode(',',$result).") GROUP BY ID_LIST_TABLE";
            $res2 = $this->db->query($query2);
        }

        $execTimes['$query2'] = microtime(true) - $timer;
        $execTimes['overAll'] = microtime(true) - $overAllTimer;



        $response = ["count"=>$q_count[0]["COUNT"], 'timings'=>$execTimes, "ok"=>"", "is_custom_bookmark"=>$isc,"fields_order" => $bookmark["FIELDS_ORDER"], "bookmark_id"=>$bms, "access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result" => $res, "documents" => $res2];
        return $app->json($response);

    }

    public function getDictionaryEntities(Request $request, Application $app) {
        if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }
        $table_name = $request->get('table_name');
        $field_name = $request->get('field_name');
        $filter_text = iconv('utf-8', 'cp1251', $request->get('filter_text'));
        if(empty($field_name) || empty($table_name)){
            return $this->generate_error($app, 404, "Nothing to search");
        }
        $search_string = " WHERE DELETED = 0 ";
        $search_string .= " AND POSITION(UPPER(CAST('".$filter_text."' AS VARCHAR(1000) CHARACTER SET WIN1251)) IN UPPER(CAST(".$field_name." AS VARCHAR(1000) CHARACTER SET WIN1251))) > 0";
        $que = "SELECT
            ID, ".$field_name."
            FROM ".$table_name." ".$search_string;
        $db_sql = $this->db->query($que);
        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result" => $db_sql, "res" => iconv('cp1251', 'utf-8', $que)];
        return $app->json($response);

    } 

    public function autocomplete(Request $request, Application $app){
    	if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }

		$filter_object = $request->get('filter_object');
        if(empty($filter_object)){
        	return $this->generate_error($app, 404, "Nothing to search");
        }

        $filter_object_p = json_decode($filter_object, true);

        if(empty($filter_object_p["main_filter"]) && empty($filter_object_p["filters"])){
        	return $this->generate_error($app, 404, "Nothing to search");
        }

        //main filter
        $main_filter = iconv('utf-8', 'cp1251', $filter_object_p["main_filter"]);
       // if(empty($main_filter)){
       // 	return $this->generate_error($app, 404, "Nothing to search");
       // }

        //fields filters
        $filters_obj = $filter_object_p["filters"];

        $search_string = "  WHERE 1 = 1  ";

        foreach($filters_obj as $filter_obj){
        	if(!empty($filter_obj["field_type"]) && !empty($filter_obj["field_name"]) && !empty($filter_obj["field_value"])){
        		switch($filter_obj["field_type"]){
        			case "date":
        				if(empty($filter_obj["field_value2"])){
        					$search_string .= " AND ".$filter_obj["field_name"]." = '".$filter_obj["field_value"]."' ";
        				} else {
        					$search_string .= " AND ".$filter_obj["field_name"]." BETWEEN '".$filter_obj["field_value"]."' AND '".$filter_obj["field_value2"]."'";
        				}
        				break;
        			case "string":
        				if ($filter_obj["field_name"] != "ID_STATUS_EX2")
                            $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                        else
                            $tmpval = $filter_obj["field_value"];
        				$search_string .= " AND UPPER(".$filter_obj["field_name"].") LIKE UPPER('%".$tmpval."%')";
        				break;
                    case "dict":
                        if ($filter_obj["field_name"] != "ID_STATUS_EX2")
                            $tmpval = iconv('utf-8', 'cp1251', $filter_obj["field_value"]);
                        else
                            $tmpval = $filter_obj["field_value"];
                        $search_string .= " AND UPPER(".$filter_obj["field_name"].") LIKE UPPER('%".$tmpval."%')";
                        break;

        			case "bool":
        				if($filter_obj["field_value"] == true){
							$search_string .= " AND ".$filter_obj["field_name"]." IS NOT NULL AND ".$filter_obj["field_name"]." <> 0";
        				} else {
							$search_string .= " AND ".$filter_obj["field_name"]." IS NULL OR ".$filter_obj["field_name"]." = 0";
        				}
        				break;
        		}
        	}
        }

        $db_sql = $this->db->query("SELECT
            SELECT_SQL
            FROM LIST_MOBILE_TABLES
            WHERE ID = 1
        ");

        if(empty($db_sql)){
        	return $this->generate_error($app, 404, "Table not found");
        }

        $sql = $db_sql[0]["SELECT_SQL"];


        if(!empty($main_filter)){
	        $db_fields = $this->db->query("SELECT
	            NAME 
	            FROM LIST_MOBILE_FIELDS
	            WHERE ID_LIST_MOBILE_TABLES = 1
	            AND TYPE = 'string'
	        ");

	        $fields_array = array();
	        $first = true;
	        foreach($db_fields as $arr){
                $fields_array[$arr["NAME"]] = $arr;

                if($first){
                    $search_string .= " AND (";
                    $first = false;
                    $search_string .= "(UPPER(COALESCE(".$arr["NAME"].", ''))";
                } else {
                    $search_string .= "||UPPER(COALESCE(".$arr["NAME"].",''))";
                }
            }
            if(!$first){
                $search_string .= " LIKE UPPER('%".$main_filter."%')))";
            }
	    }

        $query = "SELECT * FROM (".$sql.") ".$search_string." ORDER BY TARGET_DATE_ARRIVAL DESC ROWS 250";

        $res = $this->db->query($query);


        $alfetched = array();

        $result = array();
        foreach($res as $row){
        	foreach($row as $key=>$val){
        		if(empty($alfetched[$key])) $alfetched[$key] = array();
        		if(strpos(strtolower($val), strtolower($main_filter))!==false && !in_array($val, $alfetched[$key])){
        			$result[] = array("field_name"=>$key, "field_value"=>$val);
        			$alfetched[$key][] = $val;
        		}
        	}
        }



        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result" => $result];
        return $app->json($response);
    }


}