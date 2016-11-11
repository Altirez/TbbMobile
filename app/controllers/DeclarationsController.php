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

class DeclarationsController extends Controller
{
    public function __construct(){
        parent::__contruct();
    }

    public function dateTest(Request $request, Application $app) {
        $first_date = date("j.m.Y");
        $next_date = date("t.m.Y");
        $prev_date1 = date("01.m.Y", strtotime("-1 month") ) ;
        $prev_date2 = date("t.m.Y", strtotime("-1 month") ) ;
        $prev_date3 = date("t.m.Y", strtotime("-2 month") );
        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "d1" => $first_date, "d2" => $next_date, "d3" => $prev_date1, "d4"=>$prev_date2, "d5" => $prev_date3];
        return $app->json($response);
    }

    public function editDeclarations(Request $request, Application $app) {
        if(null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }
        $edit_object = $request->get('edit_fields');
        if(empty($edit_object)){
            return $this->generate_error($app, 404, "Nothing to edit");
        }

        $edit_object_p = json_decode($edit_object, true);
        if(empty($edit_object_p["fields"])){
            return $this->generate_error($app, 404, "Nothing to edit2");
        }
        //$this->db->query("UPDATE LIST_GTD SET IS_HAND = 1 WHERE ID=".$edit_object_p["row_id"]);
        $field_objects = $edit_object_p["fields"];
        $edit_string = "";

        foreach($field_objects as $field_obj){
            $field_obj["field_value"] =  !empty($field_obj["field_value"]) ? $field_obj["field_value"] : "NULL";

            if(!empty($field_obj["field_type"]) && !empty($field_obj["field_name"])){
                switch($field_obj["field_type"]){
                    case "date":
                        $edit_string .= ", ".substr($field_obj["field_name"],4)." = '".$field_obj["field_value"]."' ";
                        break;
                    case "string":
                        $edit_string .= ", ".substr($field_obj["field_name"],4)." = '".iconv('utf-8', 'cp1251',$field_obj["field_value"])."' ";
                        break;
                    case "dict":
                        $edit_string .= ", ".substr($field_obj["field_name"],4)." = ".$field_obj["field_value"]." ";
                        break;
                    case "bool":
                        break;
                }
            }
        }
        $edit_string = substr($edit_string, 1);
        //   return $this->generate_error($app, 404, "UPDATE LIST_TRAFFIC SET ".$edit_string." WHERE ID=".$edit_object_p["row_id"]);
        $db_sql = $this->db->query("UPDATE LIST_GTD SET ".$edit_string." WHERE ID=".$edit_object_p["row_id"]);

        // if(empty($db_sql)){
        //     return $this->generate_error($app, 404, "Table not found");
        // }
        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "res"=>$db_sql];
        return $app->json($response);
    }

    public function findByID(Request $request, Application $app){
        if(null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }
        $record_id = $request->get('record_id');


        $query = "SELECT LIST_GTD.ID as DEC_ID,
                     list_customs.list_customs_number,
                     list_client.list_client_name,
                     LIST_INSPECTOR_OUT.list_inspector_name,
                     LIST_INSPECTOR_IN.LIST_INSPECTOR_NAME AS LIST_INSPECTOR_IN_NAME,
                     list_gtd.declare_owner,
                     list_gtd.terminal,
                     LIST_CLIENT.LIST_CLIENT_NAME as DEC_LIST_CLIENT_NAME,
                     LIST_GTD.GTD_NUMBER as DEC_GTD_NUMBER,
                     LIST_GTD.CONT_NUMBERS as DEC_CONT_NUMBERS,
                     LIST_GTD.TOTAL_FEACC_NO as DEC_TOTAL_FEACC_NO,
                     LIST_GTD.TOTAL_LOT_CASE_NO as DEC_TOTAL_LOT_CASE_NO,
                     LIST_GTD.COUNT_CONT as DEC_COUNT_CONT,
                     LIST_GTD.DATE_CLEAR as DEC_DATE_CLEAR,
                     LIST_INSPECTOR_IN.LIST_INSPECTOR_NAME AS DEC_LIST_INSPECTOR_IN_NAME,
                     LIST_INSPECTOR_OUT.LIST_INSPECTOR_NAME AS DEC_LIST_INSPECTOR_OUT_NAME,
                     LIST_FEACC_GTD.FEACC_TEXT as DEC_FEACC_TEXT,
                     LIST_GTD.NOTE as DEC_NOTE,
                     LIST_GTD.IS_GROUP,
                     LIST_GTD.ID_LIST_INSPECTOR_OUT ,
                     LIST_GTD.ID_LIST_CUSTOMS
                     FROM LIST_GTD
                     left join list_customs on list_gtd.id_list_customs = list_customs.id
                     LEFT OUTER JOIN LIST_CLIENT ON (LIST_GTD.ID_LIST_CLIENT = LIST_CLIENT.ID)
                     LEFT OUTER JOIN LIST_INSPECTOR AS LIST_INSPECTOR_OUT ON (LIST_GTD.ID_LIST_INSPECTOR_OUT = LIST_INSPECTOR_OUT.ID)
                     LEFT OUTER JOIN LIST_INSPECTOR AS LIST_INSPECTOR_IN ON (LIST_GTD.ID_LIST_INSPECTOR_IN = LIST_INSPECTOR_IN.ID)
                     LEFT OUTER JOIN LIST_FEACC_GTD ON ( LIST_GTD.ID_LIST_FEACC_GTD = LIST_FEACC_GTD.ID )
                     WHERE ( LIST_GTD.DELETED = 0 AND LIST_GTD.ID = ".$record_id.")";



        $declarations = $this->db->query($query);
        $result = array();
        foreach($declarations as $row){
            $result[] = $row["DEC_ID"];
        }
       //
     //   $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, ID_LIST_TABLE as RECORD_ID FROM LIST_DOCUMENTS WHERE LIST_TABLE_NAME = 'LIST_GTD' AND ID_LIST_TABLE
     //   IN (".implode(',',$result).") GROUP BY ID_LIST_TABLE";
     //   $res2 = $this->db->query($query2);





        /*$query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, list_gtd.id as RECORD_ID, SUBSTRING(list_gtd.gtd_number FROM 17) as num_gtd,  list_gtd.gtd_date,
         iif(position(';', list_gtd.cont_numbers) > 0, left(list_gtd.cont_numbers, position(';', list_gtd.cont_numbers)-1), '') as first_cont, SUBSTRING(list_gtd.gtd_number FROM 1 FOR 8) as post FROM list_gtd
        left outer join LIST_DOCUMENTS on id_list_table = list_gtd.id and LIST_TABLE_NAME = 'LIST_GTD' WHERE  list_gtd.id
        IN (".implode(',',$result).") GROUP BY first_cont, list_gtd.id, num_gtd, post, gtd_date";
        $res2 = $this->db->query($query2);



        foreach($res2 as $k=>$row) {

            $post = $row["POST"];
            $num = $row["NUM_GTD"];
            $date_n = $row['GTD_DATE'];
            $first_cont = $row['GTD_DATE'];
            $res2[$k]["ADD_DOCUMENTS"] = "";
            if($date_n && $num) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $num . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $num . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
            if($date_n && $first_cont) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $first_cont . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $first_cont . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= "," . implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
        }*/
        //  var_dump($res2);

        $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, list_gtd.id as RECORD_ID, SUBSTRING(list_gtd.gtd_number FROM 17) as num_gtd,  list_gtd.gtd_date,
         iif(position(';', list_gtd.cont_numbers) > 0, left(list_gtd.cont_numbers, position(';', list_gtd.cont_numbers)-1), iif (list_gtd.cont_numbers <> '' and list_gtd.cont_numbers is not null, list_gtd.cont_numbers, '' )) as first_cont, SUBSTRING(list_gtd.gtd_number FROM 1 FOR 8) as post FROM list_gtd
        left outer join LIST_DOCUMENTS on id_list_table = list_gtd.id and LIST_TABLE_NAME = 'LIST_GTD' WHERE  list_gtd.id
        IN (".implode(',',$result).") GROUP BY first_cont, list_gtd.id, num_gtd, post, gtd_date";
        $res2 = $this->db->query($query2);



        foreach($res2 as $k=>$row) {
            // var_dump($res2);
            $post = $row["POST"];
            $num = $row["NUM_GTD"];
            $date_n = $row['GTD_DATE'];
            $first_cont = $row['FIRST_CONT'];
            $res2[$k]["ADD_DOCUMENTS"] = "";
            if($date_n && $num) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $num . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $num . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
            if($date_n && $first_cont) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $first_cont . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $first_cont . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= "," . implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
        }



        // var_dump(scandir("http://185.97.165.7/gtd/10216100/2015-12-28/0094699"));
        // var_dump($query);






        /*
                $arr11 = [];

                $res11 = ibase_query('
                SELECT
                  list_gtd.id,
                  list_gtd.gtd_number
                from list_gtd
                where list_gtd.id in '.(int)$_GET['id']);

                $rows = [];
                while ($row = ibase_fetch_assoc($res11)) {
                    $rows[] = $row;
                    $gtd_number = $row['GTD_NUMBER'];
                    if (!empty($gtd_number)) {
                        list($post, $date, $num) = explode('/', $gtd_number);
                }



                $date_n = date_create_from_format('dmy', $date, new DateTimeZone('Europe/Moscow'));

                $search_path = $post . '/' . $date_n->format("Y-m-d");
                foreach ($containers as $container) {

                    exec('ls "/mnt/gtd/' . $search_path . '/' . $num . '"', $var);

                    foreach ($var as $doc) {
                        //var_dump($doc);
                        $arr[] = array(
                            "ID" => 'e_gtd',
                            "LIST_DOC_NOMENCLATURE_NAME" => iconv('UTF-8', 'CP1251', "���������� (��������)"),
                            "DT_CREATE" => '',
                            "DOC_PATH" => '/gtd/' . $search_path . "/" . $num . "/" . iconv('UTF-8', 'CP1251', $doc),
                            "DOC_NAME" => iconv('UTF-8', 'CP1251', $doc)
                        );
                    }


                }

                $arr11 = array_unique($arr11, SORT_REGULAR);
                var_dump($arr11);





        */






       // $response = ["count"=>$q_count[0]["COUNT"], "access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result"=>$declarations, "documents"=>$res2];
        //return $app->json($response);




        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result" => $declarations, "count" => count($declarations),"documents" => $res2];

        return $app->json($response);

    }


    public function index(Request $request, Application $app){
    	if(null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }

        $overAllTimer = microtime(true);
        $timer =  microtime(true);
        $execTimes =array();

        $limit = $request->get('limit');
        $offset = $request->get('offset');
        if(empty($limit)){
            $limit = 100;
        }
        if(empty($offset)){
            $offset = 0;
        }
        $rows_offset = " ".($offset + 1)." TO ".($limit + $offset)." ";
        $type = $request->get('type');
        $query = "SELECT LIST_GTD.ID as DEC_ID,
                     list_customs.list_customs_number,
                     list_client.list_client_name,
                     LIST_INSPECTOR_OUT.list_inspector_name,
                     LIST_INSPECTOR_IN.LIST_INSPECTOR_NAME AS LIST_INSPECTOR_IN_NAME,
                     list_gtd.declare_owner,
                     list_gtd.terminal,
                     LIST_CLIENT.LIST_CLIENT_NAME as DEC_LIST_CLIENT_NAME,
                     LIST_GTD.GTD_NUMBER as DEC_GTD_NUMBER,
                     LIST_GTD.CONT_NUMBERS as DEC_CONT_NUMBERS,
                     LIST_GTD.TOTAL_FEACC_NO as DEC_TOTAL_FEACC_NO,
                     LIST_GTD.TOTAL_LOT_CASE_NO as DEC_TOTAL_LOT_CASE_NO,
                     LIST_GTD.COUNT_CONT as DEC_COUNT_CONT,
                     LIST_GTD.DATE_CLEAR as DEC_DATE_CLEAR,
                     LIST_INSPECTOR_IN.LIST_INSPECTOR_NAME AS DEC_LIST_INSPECTOR_IN_NAME,
                     LIST_INSPECTOR_OUT.LIST_INSPECTOR_NAME AS DEC_LIST_INSPECTOR_OUT_NAME,
                     LIST_FEACC_GTD.FEACC_TEXT as DEC_FEACC_TEXT,
                     LIST_GTD.NOTE as DEC_NOTE,
                     LIST_GTD.IS_GROUP,
                     LIST_GTD.ID_LIST_INSPECTOR_OUT ,
                     LIST_GTD.ID_LIST_CUSTOMS,
                     LIST_GTD.GTD_COUNT as DEC_GTD_COUNT
                     FROM LIST_GTD
                     left join list_customs on list_gtd.id_list_customs = list_customs.id
                     LEFT OUTER JOIN LIST_CLIENT ON (LIST_GTD.ID_LIST_CLIENT = LIST_CLIENT.ID)
                     LEFT OUTER JOIN LIST_INSPECTOR AS LIST_INSPECTOR_OUT ON (LIST_GTD.ID_LIST_INSPECTOR_OUT = LIST_INSPECTOR_OUT.ID)
                     LEFT OUTER JOIN LIST_INSPECTOR AS LIST_INSPECTOR_IN ON (LIST_GTD.ID_LIST_INSPECTOR_IN = LIST_INSPECTOR_IN.ID)
                     LEFT OUTER JOIN LIST_FEACC_GTD ON ( LIST_GTD.ID_LIST_FEACC_GTD = LIST_FEACC_GTD.ID )
                     WHERE ( LIST_GTD.DELETED = 0  AND LIST_GTD.DT_CREATE > '". date('d.m.y', strtotime('-8 month')) ."') ";

        $queryWithoutFiler = "SELECT LIST_GTD.ID as DEC_ID,
                     LIST_GTD.GTD_NUMBER as DEC_GTD_NUMBER,
                     LIST_GTD.CONT_NUMBERS as DEC_CONT_NUMBERS,
                     LIST_GTD.TOTAL_FEACC_NO as DEC_TOTAL_FEACC_NO,
                     LIST_GTD.TOTAL_LOT_CASE_NO as DEC_TOTAL_LOT_CASE_NO,
                     LIST_GTD.COUNT_CONT as DEC_COUNT_CONT,
                     LIST_GTD.DATE_CLEAR as DEC_DATE_CLEAR
                     FROM LIST_GTD
                     WHERE ( LIST_GTD.DELETED = 0  AND LIST_GTD.DT_CREATE > '". date('d.m.y', strtotime('-8 month')) ."') ";

        $first_date = date("01.m.Y");
        $next_date = date("t.m.Y");
        $prev_date1 = date("01.m.Y", strtotime("-1 month") ) ;
        $prev_date2 = date("t.m.Y", strtotime("-1 month") ) ;
        $prev_date3 = date("t.m.Y", strtotime("-2 month") );
        switch($type) {
            case "cur":
                $query = $query." AND LIST_GTD.DATE_CLEAR BETWEEN '".$first_date."' AND '".$next_date."'";
                $queryWithoutFiler = $queryWithoutFiler." AND LIST_GTD.DATE_CLEAR BETWEEN '".$first_date."' AND '".$next_date."'";
                break;
            case "last":
                $query = $query." AND LIST_GTD.DATE_CLEAR BETWEEN '".$prev_date1."' AND '".$prev_date2."'";
                $queryWithoutFiler = $queryWithoutFiler." AND LIST_GTD.DATE_CLEAR BETWEEN '".$prev_date1."' AND '".$prev_date2."'";
                break;
            case "arc":
                $query = $query." AND LIST_GTD.DATE_CLEAR <= '".$prev_date3."'";
                $queryWithoutFiler = $queryWithoutFiler." AND LIST_GTD.DATE_CLEAR <= '".$prev_date3."'";
                break;
            case "num":
                $num = $request->get('num');
                $query = $query."AND LIST_GTD.GTD_NUMBER LIKE ('%".$num."%')";
                $queryWithoutFiler = $queryWithoutFiler."AND LIST_GTD.GTD_NUMBER LIKE ('%".$num."%')";
                break;
            default: break;

        }
        if (substr($type, 0, 6) == "status") {
            $query = $query." AND LIST_GTD.ID_STATUS_EX = ".substr($type, 6)." ";
            $queryWithoutFiler = $queryWithoutFiler." AND LIST_GTD.ID_STATUS_EX = ".substr($type, 6)." ";
        }

        $where_condition = $this->db->query("SELECT list_users.login, coalesce(list_mobile_groups_tables.where_condition, '1=1') as where_condition  FROM list_mobile_users
                                    LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
                                    left join list_mobile_groups on list_mobile_users.id_list_mobile_groups = list_mobile_groups.id
                                    left join list_mobile_groups_tables on list_mobile_groups_tables.id_list_mobile_groups = list_mobile_groups.id
                                    left join list_mobile_devices on list_mobile_devices.id_list_mobile_users = list_mobile_users.id
                                    WHERE list_mobile_groups_tables.id_list_mobile_tables = 2 and list_mobile_devices.access_token = '".$request->get('access_token')."'");

        $execTimes['$where_condition'] = microtime(true) - $timer;
        $timer =  microtime(true);

        // var_dump($where_condition);
        $where_condition = $where_condition[0]["WHERE_CONDITION"];
        if (empty($where_condition)) $where_condition = " 1 = 1 ";
        $where_condition = iconv('utf-8', 'cp1251', $where_condition);


        $query = "SELECT DEC_ID,DEC_LIST_CLIENT_NAME,DEC_GTD_NUMBER,DEC_CONT_NUMBERS,
                  DEC_TOTAL_FEACC_NO,DEC_TOTAL_LOT_CASE_NO,DEC_COUNT_CONT,declare_owner as dec_declare_owner, list_customs_number as list_post,
                  DEC_DATE_CLEAR,DEC_LIST_INSPECTOR_OUT_NAME,DEC_FEACC_TEXT,DEC_NOTE, DEC_LIST_INSPECTOR_IN_NAME,
                  IS_GROUP,ID_LIST_INSPECTOR_OUT,ID_LIST_CUSTOMS, DEC_GTD_COUNT
                  FROM (".$query.") WHERE ".$where_condition." ORDER BY DEC_DATE_CLEAR";

        //if ($where_condition == "1=1" || $where_condition == " 1 = 1 "){ // Если фильтров нет
            //$query = $queryWithoutFiler."\n\t\t\tPLAN (LIST_GTD INDEX (LIST_GTD_IDX7))";
       // }


        $q_count = $this->db->query("select count(*) FROM (".$query.")" );
        $query = $query." ROWS ".$rows_offset;
        $declarations = $this->db->query($query);

        $execTimes['$q_count'] = microtime(true) - $timer;
        $timer =  microtime(true);

        $result = array();
        foreach($declarations as $row){
            $result[] = $row["DEC_ID"];
        }

        $query2 = "SELECT LIST(DOC_PATH, ',') as DOCUMENTS, list_gtd.id as RECORD_ID, SUBSTRING(list_gtd.gtd_number FROM 17) as num_gtd,  list_gtd.gtd_date,
         iif(position(';', list_gtd.cont_numbers) > 0, left(list_gtd.cont_numbers, position(';', list_gtd.cont_numbers)-1), iif (list_gtd.cont_numbers <> '' and list_gtd.cont_numbers is not null, list_gtd.cont_numbers, '' )) as first_cont, SUBSTRING(list_gtd.gtd_number FROM 1 FOR 8) as post FROM list_gtd
        left outer join LIST_DOCUMENTS on id_list_table = list_gtd.id and LIST_TABLE_NAME = 'LIST_GTD' WHERE  list_gtd.id
        IN (".implode(',',$result).") GROUP BY first_cont, list_gtd.id, num_gtd, post, gtd_date";
        $res2 = $this->db->query($query2);

        $execTimes['$query2'] = microtime(true) - $timer;
        $timer =  microtime(true);



        foreach($res2 as $k=>$row) {
           // var_dump($res2);
            $post = $row["POST"];
            $num = $row["NUM_GTD"];
            $date_n = $row['GTD_DATE'];
            $first_cont = $row['FIRST_CONT'];
            $res2[$k]["ADD_DOCUMENTS"] = "";
            if($date_n && $num) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $num . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $num . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
            if($date_n && $first_cont) {
                $arr = array();
                $var = array();
                $search_path = $post.'/'.date('Y-m-d', $date_n);
                //$search_path = $post.'/'.iconv('UTF-8', 'CP1251',$date_n);
                $comm = 'ls /mnt/gtd/' . $search_path . '/' . $first_cont . '/';
                //var_dump($comm);
                exec($comm, $var);
                foreach ($var as $doc) {

                    $arr[] =  '/gtd/' . $search_path . "/" . $first_cont . "/" . $doc;//iconv('UTF-8', 'CP1251', $doc);
                }
                if (count($arr) > 0) {
                    $res2[$k]["ADD_DOCUMENTS"] .= "," . implode(',', $arr);
                    //var_dump(implode(',', $arr));
                }
            }
        }
      //  var_dump($res2);





       // var_dump(scandir("http://185.97.165.7/gtd/10216100/2015-12-28/0094699"));
       // var_dump($query);






/*
        $arr11 = [];

        $res11 = ibase_query('
        SELECT
          list_gtd.id,
          list_gtd.gtd_number
        from list_gtd
        where list_gtd.id in '.(int)$_GET['id']);

        $rows = [];
        while ($row = ibase_fetch_assoc($res11)) {
            $rows[] = $row;
            $gtd_number = $row['GTD_NUMBER'];
            if (!empty($gtd_number)) {
                list($post, $date, $num) = explode('/', $gtd_number);
        }



        $date_n = date_create_from_format('dmy', $date, new DateTimeZone('Europe/Moscow'));

        $search_path = $post . '/' . $date_n->format("Y-m-d");
        foreach ($containers as $container) {

            exec('ls "/mnt/gtd/' . $search_path . '/' . $num . '"', $var);

            foreach ($var as $doc) {
                //var_dump($doc);
                $arr[] = array(
                    "ID" => 'e_gtd',
                    "LIST_DOC_NOMENCLATURE_NAME" => iconv('UTF-8', 'CP1251', "���������� (��������)"),
                    "DT_CREATE" => '',
                    "DOC_PATH" => '/gtd/' . $search_path . "/" . $num . "/" . iconv('UTF-8', 'CP1251', $doc),
                    "DOC_NAME" => iconv('UTF-8', 'CP1251', $doc)
                );
            }


        }

        $arr11 = array_unique($arr11, SORT_REGULAR);
        var_dump($arr11);





*/





        $execTimes["overAll"] = microtime(true) - $overAllTimer;

        $response = ["count"=>$q_count[0]["COUNT"], "timings" => $execTimes, "access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "result"=>$declarations, "documents"=>$res2];
        return $app->json($response);

    }




}