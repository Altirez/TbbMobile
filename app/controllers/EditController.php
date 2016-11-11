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

class EditController extends Controller
{
    public function __construct(){
        parent::__contruct();
    }

    public function index(Request $request, Application $app){
    	if(null === $app['session']->get('new_access_token')){
            return $this->generate_error($app, 401, "No new access_token");
        }

        $device_id = $app["session"]->get("id_list_mobile_devices");

        $edit_object = $request->get('edit_fields');
        if(empty($edit_object)){
        	return $this->generate_error($app, 404, "Nothing to edit");
        }

        $edit_object_p = json_decode($edit_object, true);
        if(empty($edit_object_p["fields"])){
        	return $this->generate_error($app, 404, "Nothing to edit2");
        }

        $table_id = $request->get('table');
        if(empty($table_id) || !is_numeric($table_id)) $table_id = 1;
        $field_objects = $edit_object_p["fields"];
        $edit_string = "";
        foreach($field_objects as $field_obj){
            $field_obj["field_value"] = !empty($field_obj["field_value"]) ? $field_obj["field_value"] : "null";
        	if(!empty($field_obj["field_type"]) && !empty($field_obj["field_name"])){
        		switch($field_obj["field_type"]){
        			case "date":
        				$edit_string .= ", ".$field_obj["field_name"]." = '".$field_obj["field_value"]."' ";
        				break;
        			case "string":
                        $edit_string .= ", ".$field_obj["field_name"]." = '".iconv('utf-8', 'cp1251', $field_obj["field_value"])."' ";
        				break;
                    case "dict":
                        $edit_string .= ", ".$field_obj["field_name"]." = ".$field_obj["field_value"]." ";
                        break;
        			case "bool":
        				break;
        		}
        	}
        }
        $edit_string = substr($edit_string, 1);
     //   return $this->generate_error($app, 404, "UPDATE LIST_TRAFFIC SET ".$edit_string." WHERE ID=".$edit_object_p["row_id"]); 
        $db_sql = $this->db->query("UPDATE LIST_TRAFFIC SET ".$edit_string." WHERE ID=".$edit_object_p["row_id"]);

       // if(empty($db_sql)){
       //     return $this->generate_error($app, 404, "Table not found");
       // }
        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60];
        return $app->json($response);

    }


    public function deleteBookmark(Request $request, Application $app)
    {
        //получаем данные от пользователя
        $bookmark_id = $request->get('bookmark_id');
        $this->db->query('
            UPDATE LIST_MOBILE_USER_BOOKMARKS
            SET IS_DELETED = 1 WHERE ID = '.$bookmark_id);

        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "bookmark_id" => $bookmark_id];
        return $app->json($response);
    }

    public static function diverseArray($vector) {
        $result = array();
        foreach($vector as $key1 => $value1)
            foreach($value1 as $key2 => $value2)
                $result[$key2][$key1] = $value2;
        return $result;
    }

    public function uploadDocument(Request $request, Application $app)
    {
        $id = $request->get("id");
        $type_id = $request->get("type_id");
        
	$number = $request->get("number");
        $date = $request->get("date");

	if (empty($type_id)) {
            $type_id = 1;
        }
        $list_table_name = $request->get("list_table_name");
        $uploads_dir="/mnt/documents/mobile/{$list_table_name}/{$id}";
        if (!is_dir($uploads_dir)) {
            $ccc2 = mkdir($uploads_dir, 0777, true);
        }
        $name2 = $request->get("name");

        $name3 = iconv("utf-8", "cp1251",  $name2);
        $tmp_name = $_FILES["upload_img"]["tmp_name"];
        move_uploaded_file($tmp_name, "{$uploads_dir}/{$name2}");



	$qq = '
            INSERT INTO LIST_DOCUMENTS
            (
                '.(empty($number) ? '' : 'NUM_DOC,').'
                '.(empty($date) ? '' : 'DATE_DOC,').'
                DOC_PATH,
                LIST_TABLE_NAME,
                ID_LIST_TABLE,
                ID_LIST_DOC_NOMENCLATURE
            )
            VALUES
            (
                '.(empty($number) ? '' : $number.',').'
                '.(empty($date) ? '' : $date.',').'
                CAST(\''."/mobile/{$list_table_name}/{$id}/{$name3}".'\' AS VARCHAR(500) CHARACTER SET WIN1251),
                \''.$list_table_name.'\',
                '.$id;

        if(!empty($type_id)) {
            $qq = $qq.', '.$type_id;
        }
        $qq = $qq.' ) RETURNING ID';



        $query_res = $this->db->query($qq);
        $response = ["path"=>"/mobile/{$list_table_name}/{$id}/{$name2}", "res"=>$query_res, "name"=>"{$name3}"];
        return $app->json($response);
    }


    public function editBookmark(Request $request, Application $app)
    {
        //получаем данные от пользователя
        $bookmark_id = $request->get('bookmark_id');
        $access_token = $request->get('access_token');
        $name = iconv('utf-8', 'cp1251', $request->get('name'));

        $description = iconv('utf-8', 'cp1251', $request->get('description'));

        $this->db->query('
            UPDATE LIST_MOBILE_USER_BOOKMARKS
            SET JSON_DESC =  \''.$description.'\', NAME = \''.$name.'\' WHERE LIST_MOBILE_USER_BOOKMARKS.ID = '.$bookmark_id);

        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60];

        return $app->json($response);
    }

    public function editBookmarkOrder(Request $request, Application $app)
    {
        //получаем данные от пользователя
        $bookmark_id = $request->get('bookmark_id');
        $order = $request->get('order');
        $access_token = $request->get('access_token');
        $this->db->query('
            UPDATE LIST_MOBILE_USER_BOOKMARKS
            SET FIELDS_ORDER =  \''.$order.'\' WHERE LIST_MOBILE_USER_BOOKMARKS.ID = '.$bookmark_id);

        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "bookmark_id"=>["FIELDS_ORDER"=>$order, "ID"=>$bookmark_id]];

        return $app->json($response);
    }

    public function addBookmark(Request $request, Application $app)
    {
        //получаем данные от пользователя
       // $bookmark_id = $request->get('bookmark_id');
        $access_token = $request->get('access_token');
        $login = $request->get('login');
        $name = iconv('utf-8', 'cp1251', $request->get('name'));

        $description = iconv('utf-8', 'cp1251', $request->get('description'));
        $order = $request->get('order'); 
        $user_id = $this->db->query("SELECT * FROM LIST_MOBILE_USERS
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID           
            WHERE LIST_USERS.LOGIN = '".$login."'");
        $query_res = $this->db->query('
            INSERT INTO LIST_MOBILE_USER_BOOKMARKS
            (
                USER_ID,
                JSON_DESC,
                IS_DELETED,
                FIELDS_ORDER,
                NAME
            )
            VALUES
            (
                '.$user_id[0]["ID"].' ,
                \''.$description.'\' ,
                0, 
                \''.$order.'\' ,
                \''.$name.'\'
            ) RETURNING ID');

        $response = ["access_token"=>$app["session"]->get("new_access_token"), "expires_in"=>time()+7*24*60*60, "users"=>$user_id[0]["ID"], "res"=>$query_res, "fields_order"=>$order];

        return $app->json($response);
    }



    
}
