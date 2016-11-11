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

require_once __DIR__ . '/Controller.php';

class UserController extends Controller
{
    public function __construct()
    {
        parent::__contruct();
    }

    public function auth(Request $request, Application $app)
    {
        //получаем данные от пользователя
        $client_id = $request->get('client_id');
        $auth_token = $request->get('auth_token');
        $login = $request->get('login');

        $device_id = $request->get('device_id');

        /* if(empty($client_id) || !is_numeric($client_id) || empty($auth_token) || empty($login)){
             return $this->generate_error($app, 401, "Empty/incorrect data");
         }*/

        $clients = $this->db->query('SELECT CLIENT_KEY FROM LIST_MOBILE_CLIENTS WHERE ID = ' . $client_id . '');


        /* if(empty($clients) || count($clients)>1){
             return $this->generate_error($app, 401, "Your app unknown, go away");
         }*/

        $client_secret = $clients[0]["CLIENT_KEY"];

        $users = $this->db->query('SELECT LIST_MOBILE_USERS.ID, LIST_MOBILE_USERS.PASSWORD_HASH FROM LIST_MOBILE_USERS LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID WHERE LIST_USERS.LOGIN = \'' . $login . '\'');

        /* if(empty($users) || count($users)>1){
             return $this->generate_error($app, 401, "No such user");
         }*/

        $user_hash = $users[0]["PASSWORD_HASH"];
        $user_id = $users[0]["ID"];

        //check auth_token

        if (!(strtolower($auth_token) == hash('sha512', $user_hash . $client_id . $client_secret))) {
            return $this->generate_error($app, 401, "Hash is incorrect, correct: " . hash('sha512', $user_hash . $client_id . $client_secret) . " from data: " . $user_hash . $client_id . $client_secret);
        }


        //register user
        $access_token = hash('sha512', time() . time() . $client_secret);
        $this->db->query('
            UPDATE OR INSERT INTO LIST_MOBILE_DEVICES
            (
                ID_LIST_MOBILE_CLIENTS,
                ID_LIST_MOBILE_USERS,
                DEVICE_ID,
                ACCESS_TOKEN,
                ACCESS_TOKEN_EXPIRES
            )
            VALUES
            (
                \'' . $client_id . '\',
                \'' . $user_id . '\',
                \'' . $device_id . '\',
                \'' . $access_token . '\',
                DATEADD(7 DAY TO CURRENT_TIMESTAMP)
            ) MATCHING (
                ID_LIST_MOBILE_CLIENTS,
                ID_LIST_MOBILE_USERS
            )
        ');

        $response = ["access_token" => $access_token, "expires_in" => time() + 7 * 24 * 60 * 60];

        return $app->json($response);
    }

    public function viewDeviceIds(Request $request, Application $app)
    {
        $devices = $this->db->query("SELECT
            LIST_MOBILE_DEVICES.DEVICE_ID,
            LIST_USERS.LOGIN
            FROM LIST_MOBILE_DEVICES
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_USERS = LIST_MOBILE_USERS.ID
            LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
        ");

        if (empty($devices)) {
            //return $this->generate_error($app, 401, "Incorrect access_token — user or client not found");
        }

        $reg_ids = [];
        foreach ($devices as $device) {
            $reg_ids[] = array("user" => $device["LOGIN"], "device_id" => $device["DEVICE_ID"]);
        }

        return $app->json($reg_ids);
    }

    public function checkStatusFields(Request $request, Application $app)
    {
        $status_id = $request->get('status_id');
        $login = $request->get('login');
        $table_id = $request->get('table_id');
        $customs = $this->db->query('SELECT FIELDS_ORDER FROM LIST_MOBILE_CUSTOM_BOOKMARKS
                                     LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_CUSTOM_BOOKMARKS.USER_ID = LIST_MOBILE_USERS.ID
                                     LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
                                     WHERE STATUS_ID = ' . $status_id . '
                                     AND TABLE_ID = ' . $table_id . '
                                     AND LIST_USERS.LOGIN = \'' . $login . '\'');
        if (empty($customs)) {
            $response = ["access_token" => $request->get('access_token'), "status" => 0, "expires_in" => time() + 7 * 24 * 60 * 60];

            return $app->json($response);

        }
        if (empty($customs[0]["FIELDS_ORDER"])) {
            $response = ["access_token" => $request->get('access_token'), "status" => 0, "expires_in" => time() + 7 * 24 * 60 * 60];

            return $app->json($response);
        }

        $response = ["access_token" => $request->get('access_token'), "status" => 1, "expires_in" => time() + 7 * 24 * 60 * 60];

        return $app->json($response);
    }


    public function addCustomBookmarkSettings(Request $request, Application $app)
    {
        $table_id = 1;
        $login = $request->get('login');
        $status_id = $request->get('status_id');
        $order = $request->get('order');
        $user_id = $this->db->query("SELECT * FROM LIST_MOBILE_USERS
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID
            WHERE LIST_USERS.LOGIN = '" . $login . "'");

        $bookmarks_id = $this->db->query('
            UPDATE OR INSERT INTO LIST_MOBILE_CUSTOM_BOOKMARKS
            (
                USER_ID,
                FIELDS_ORDER,
                TABLE_ID,
                STATUS_ID
            )
            VALUES
            (
                ' . $user_id[0]["ID"] . ',
                \'' . $order . '\',
                ' . $table_id . ',
                ' . $status_id . '
            ) MATCHING (
                USER_ID,
                TABLE_ID,
                STATUS_ID
            ) RETURNING ID, FIELDS_ORDER, STATUS_ID
        ');
        $response = ["access_token" => $request->get('access_token'), "expires_in" => time() + 7 * 24 * 60 * 60, "bookmark_id" => $bookmarks_id[0]];

        return $app->json($response);
    }

    public function unregister(Request $request, Application $app)
    {
        $access_token = $request->get('access_token');
        $this->db->query("UPDATE LIST_MOBILE_DEVICES SET DEVICE_ID = NULL
            WHERE LIST_MOBILE_DEVICES.ACCESS_TOKEN = '" . $access_token . "'
        ");
        $response = ["access_token" => $request->get('access_token'), "expires_in" => time() + 7 * 24 * 60 * 60];
        return $app->json($response);
    }

    public function push(Request $request, Application $app)
    {
        $title = $request->get('title');
        $description = $request->get('description');
        $from_db = $request->get('db');

        $user = $request->get('user');
        $login = $request->get('login');
        $apiKey = 'AIzaSyC9sXg5b7Pvuw9xqWwS3N7K8M4zMyqxn7o';
        $url = 'https://android.googleapis.com/gcm/send';
        $server = 'android.googleapis.com';
        $uri = '/gcm/send';

        $query = "SELECT
            LIST_MOBILE_DEVICES.DEVICE_ID
            FROM LIST_MOBILE_DEVICES
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_USERS = LIST_MOBILE_USERS.ID
            LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
        ";

        if (!empty($user)) {
            $query .= " WHERE LIST_USERS.LOGIN = '" . $user . "'";
        }
        if (!empty($login)) {
            $query .= " WHERE LIST_USERS.LOGIN = '" . $login . "'";
        }


        $devices = $this->db->query($query);

        if (empty($devices)) {
            //return $this->generate_error($app, 401, "Incorrect access_token — user or client not found");
        }

        $reg_ids = [];
        foreach ($devices as $device) {
            $reg_ids[] = $device["DEVICE_ID"];
        }


        if ($from_db == 1) {
            $db_sql = $this->db->query("SELECT
                SELECT_SQL
                FROM LIST_MOBILE_TABLES
                WHERE ID = 1
            ");

            if (empty($db_sql)) {
                return $this->generate_error($app, 404, "Table not found");
            }

            $sql = $db_sql[0]["SELECT_SQL"];

            $db_fields = $this->db->query("SELECT
                    LIST(DISTINCT(NAME), ', ') AS FIELD_LIST
                    FROM LIST_MOBILE_FIELDS
                    WHERE ID_LIST_MOBILE_TABLES =  1
                    ROWS 50               
                ");
            if (!empty($db_fields) && !empty($db_fields[0]["FIELD_LIST"])) {
                $fields_to_get = $db_fields[0]["FIELD_LIST"];
            }

            $query = "SELECT " . $fields_to_get . " FROM (" . $sql . " ORDER BY LIST_TRAFFIC.ID ASC) ROWS 1";

            //var_dump($query);
            //exit();

            $res = $this->db->query($query)[0];
        }


        $data = new stdClass;

        $data->date = round(microtime(true) * 1000) . "";
        $data->title = $title;
        $data->description = $description;

        if ($from_db) {
            $data->data = $res;
        }


        $post = array(
            'registration_ids' => $reg_ids,
            'data' => $data,
        );
        var_dump($reg_ids);
        /* $headers = array(
             'Authorization: key=' . $apiKey,
             'Content-Type: application/json'
         );*/

        $fp = fsockopen($server, 80);
        $vars = json_encode($post);
        $content = $vars;
        fwrite($fp, "POST " . $uri . " HTTP/1.1\r\n");
        fwrite($fp, "Host: " . $server . "\r\n");
        fwrite($fp, "Content-Type: application/json\r\n");
        fwrite($fp, "Authorization: key=" . $apiKey . "\r\n");
        fwrite($fp, "Content-Length: " . strlen($content) . "\r\n");
        fwrite($fp, "Connection: close\r\n");
        fwrite($fp, "\r\n");

        fwrite($fp, $content);

        $s = "";

        while (!feof($fp)) {
            $s .= fgets($fp, 1024);
        }

        list($header, $body) = preg_split("/\R\R/", $s, 2);
        $response = json_decode($body, true);
        // $body
        // $response = ["body"=>$body];

        return $app->json($response);
        // return $body;


        /* $ch = curl_init();
         curl_setopt( $ch, CURLOPT_URL, $url );
         curl_setopt( $ch, CURLOPT_POST, true );
         curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
         curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
         curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $post ) );

         $result = curl_exec( $ch );
         if ( curl_errno( $ch ) )
         {
             echo 'GCM error: ' . curl_error( $ch );
         }

         curl_close( $ch );

         return $result;*/

    }

    public function logRequest2(Request $request)
    {
        $access_token = $request->get('login');
        $device_info = $request->get('device_info');
        if (empty($access_token)) $access_token = "SYSDBA";
        $req_str = $request->getUri();
        foreach ($request->request->all() as $key => $val) {
            $req_str = $req_str . "&" . $key . "=" . $val;
        }
        $this->db->query("insert into MOBILE_REQUESTS (REQUEST, USER_LOGIN, DEVICE_INFO)
        values ('" . $req_str . "', '" . $access_token . "', '" . $device_info . "')");
    }

    public function logRequest(Request $request)
    {
        $device_info = $request->get('device_info');
        $access_token = $request->get('access_token');
        $req_str = $request->getUri();
        foreach ($request->request->all() as $key => $val) {
            $req_str = $req_str . "&" . $key . "=" . $val;
        }
        $mobile_client = $this->db->query("SELECT
            LIST_USERS.LOGIN,
            LIST_MOBILE_DEVICES.DEVICE_ID
            FROM LIST_MOBILE_DEVICES
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_USERS = LIST_MOBILE_USERS.ID
            LEFT JOIN LIST_MOBILE_CLIENTS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_CLIENTS = LIST_MOBILE_CLIENTS.ID
            LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
            WHERE LIST_MOBILE_DEVICES.ACCESS_TOKEN = '" . $access_token . "'
        ");
        $this->db->query("insert into MOBILE_REQUESTS (REQUEST, USER_LOGIN, DEVICE_ID, DEVICE_INFO)
        values ('" . $req_str . "', '" . $mobile_client[0]["LOGIN"] . "', '" . $mobile_client[0]["DEVICE_ID"] . "', '" . $device_info . "')");
    }

    public function validate(Request $request, Application $app)
    {
        //получаем данные от пользователя
        $access_token = $request->get('access_token');
        $client_hash = $request->get('hs');

        //ищем клиент с таким access_token

        $mobile_client = $this->db->query("SELECT
            LIST_MOBILE_DEVICES.ID,
            LIST_MOBILE_USERS.PASSWORD_HASH,
            LIST_MOBILE_CLIENTS.CLIENT_KEY

            FROM LIST_MOBILE_DEVICES
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_USERS = LIST_MOBILE_USERS.ID
            LEFT JOIN LIST_MOBILE_CLIENTS ON LIST_MOBILE_DEVICES.ID_LIST_MOBILE_CLIENTS = LIST_MOBILE_CLIENTS.ID
            WHERE LIST_MOBILE_DEVICES.ACCESS_TOKEN = '" . $access_token . "'
                AND LIST_MOBILE_DEVICES.ACCESS_TOKEN_EXPIRES > CURRENT_TIMESTAMP
        ");


        if (empty($mobile_client) || count($mobile_client) > 1) {
            return $this->generate_error($app, 401, "Incorrect access_token — user or client not found");
        }
        $device_id = $mobile_client[0]["ID"];
        $client_secret = $mobile_client[0]["CLIENT_KEY"];
        $password_hash = $mobile_client[0]["PASSWORD_HASH"];

        if (empty($device_id) || empty($client_secret) || empty($password_hash)) {
            return $this->generate_error($app, 401, "No access for this client or user");
        }

        if (!(strtolower($client_hash) == hash('sha512', $access_token . $password_hash . $client_secret))) {
            return $this->generate_error($app, 401, "Hash is incorrect, correct: " . hash('sha512', $access_token . $password_hash . $client_secret) . " from data: " . $access_token . $password_hash . $client_secret);
        }
        $new_access_token = $access_token;
        //$new_access_token = hash('sha512', time().time().$client_secret);
        /* $this->db->query('
             UPDATE LIST_MOBILE_DEVICES SET
                 ACCESS_TOKEN = \''.$new_access_token.'\',
                 ACCESS_TOKEN_EXPIRES = DATEADD(7 DAY TO CURRENT_TIMESTAMP)
             WHERE
                 ID = \''.$device_id.'\';
         ');*/

        $app["session"]->set("new_access_token", $new_access_token);
        $app["session"]->set("id_list_mobile_devices", $device_id);


    }

    public function getAvailableTables(Request $request, Application $app)
    {
        $access_token = $request->get('access_token');
        $login = $request->get('login');
        //  $client_hash = $request->get('hs');


        if (null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }

        $db_bookmarks = $this->db->query("SELECT
            LIST_MOBILE_USER_BOOKMARKS.ID AS ID, 
            LIST_MOBILE_USER_BOOKMARKS.NAME AS NAME, 
            LIST_MOBILE_USER_BOOKMARKS.JSON_DESC AS JSON_DESC,
            LIST_MOBILE_USER_BOOKMARKS.FIELDS_ORDER AS FIELDS_ORDER
            FROM LIST_MOBILE_USER_BOOKMARKS
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_USERS.ID = LIST_MOBILE_USER_BOOKMARKS.USER_ID
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID           
            WHERE LIST_USERS.LOGIN = '" . $login . "' AND LIST_MOBILE_USER_BOOKMARKS.IS_DELETED = 0");


        $db_tables = $this->db->query("SELECT
            LIST_MOBILE_TABLES.ID as table_id, 
            LIST_MOBILE_TABLES.NAME, 
            LIST_MOBILE_TABLE_SUBTYPES.ID as subtype_id,
            LIST_MOBILE_TABLE_SUBTYPES.SUBTYPE_NAME
            FROM LIST_MOBILE_TABLES
            LEFT JOIN LIST_MOBILE_TABLE_SUBTYPES ON LIST_MOBILE_TABLE_SUBTYPES.ID_LIST_MOBILE_TABLES = LIST_MOBILE_TABLES.ID
            order by 1 asc, 3 asc
        ");

        $order = $this->db->query("SELECT
            LIST_MOBILE_USERS.BOOKMARK_ORDER as order
            FROM LIST_MOBILE_USERS
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID           
            WHERE LIST_USERS.LOGIN = '" . $login . "'");


        $bookmarks = array();

        $tables_array = array();
        foreach ($db_tables as $key => $arr) {
            $arr = array_change_key_case($arr, CASE_LOWER);

            if (!is_array($tables_array[$arr['table_id']])) {
                $tables_array[$arr['table_id']] = array('table_id' => $arr['table_id'], 'table_name' => $arr['name'], 'subtypes' => array());
            }
            $tables_array[$arr['table_id']]["subtypes"][] = array('subtype_id' => $arr['subtype_id'], 'subtype_name' => $arr['subtype_name'], 'subtypes' => array());
        }

        $tables_array = array_values($tables_array);

        $response = ["access_token" => $app["session"]->get("new_access_token"), "expires_in" => time() + 7 * 24 * 60 * 60, "tables" => $tables_array, "bookmarks" => $db_bookmarks, "bookmarks_order" => $order[0]["order"]];
        return $app->json($response);
    }

    public function getAvailableFields(Request $request, Application $app)
    {

        $access_token = $request->get('access_token');
        //  $client_hash = $request->get('hs');
        $login = $request->get('login');

        if (null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }

        $execTime = array();
        $timerOverAll = microtime(true);
        $timer = microtime(true);
        $db_custom_bookmarks = $this->db->query("SELECT
            LIST_MOBILE_CUSTOM_BOOKMARKS.ID as BOOKMARK_ID,
            LIST_MOBILE_CUSTOM_BOOKMARKS.STATUS_ID,
            LIST_MOBILE_CUSTOM_BOOKMARKS.FIELDS_ORDER
            FROM LIST_MOBILE_CUSTOM_BOOKMARKS
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_USERS.ID = LIST_MOBILE_CUSTOM_BOOKMARKS.USER_ID
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID
            WHERE LIST_USERS.LOGIN = '" . $login . "'");

        $execTime['BookMarksSelect'] = microtime(true) - $timer;
        $timer = microtime(true);

        $db_bookmarks = $this->db->query("SELECT
            LIST_MOBILE_USER_BOOKMARKS.ID, 
            LIST_MOBILE_USER_BOOKMARKS.NAME, 
            LIST_MOBILE_USER_BOOKMARKS.JSON_DESC,
            LIST_MOBILE_USER_BOOKMARKS.FIELDS_ORDER,
            LIST_MOBILE_USER_BOOKMARKS.RECORDS_COUNT
            FROM LIST_MOBILE_USER_BOOKMARKS
            LEFT JOIN LIST_MOBILE_USERS ON LIST_MOBILE_USERS.ID = LIST_MOBILE_USER_BOOKMARKS.USER_ID
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID           
            WHERE LIST_USERS.LOGIN = '" . $login . "' AND LIST_MOBILE_USER_BOOKMARKS.IS_DELETED = 0");

        $execTime['DBBookMarksSelect'] = microtime(true) - $timer;
        $timer = microtime(true);

        $db_tables = $this->db->query("SELECT
            LIST_MOBILE_TABLES.ID as table_id, 
            LIST_MOBILE_TABLES.NAME, 
            LIST_MOBILE_TABLE_SUBTYPES.ID as subtype_id,
            LIST_MOBILE_TABLE_SUBTYPES.SUBTYPE_NAME
            FROM LIST_MOBILE_TABLES
            LEFT JOIN LIST_MOBILE_TABLE_SUBTYPES ON LIST_MOBILE_TABLE_SUBTYPES.ID_LIST_MOBILE_TABLES = LIST_MOBILE_TABLES.ID
            order by 1 asc, 3 asc
        ");

        $execTime['DB_TABLES'] = microtime(true) - $timer;
        $timer = microtime(true);

        $order = $this->db->query("SELECT
            LIST_MOBILE_USERS.FIELD_ORDER as field_order,
            LIST_MOBILE_USERS.DEC_ORDER as dec_order,
            '' as rep_dec_order
            FROM LIST_MOBILE_USERS
            LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID           
            WHERE LIST_USERS.LOGIN = '" . $login . "'");

        $execTime['$order'] = microtime(true) - $timer;
        $timer = microtime(true);

        $db_status_tables = $this->db->query("SELECT
            LIST_MOBILE_STATUS_MAIN.ID as main_id,
            LIST_MOBILE_STATUS_MAIN.STATUS as main_name,
            LIST_MOBILE_STATUS.ID as status_id,
            LIST_MOBILE_STATUS.NAME as status_name
            FROM LIST_MOBILE_STATUS_MAIN
            LEFT JOIN LIST_MOBILE_STATUS ON LIST_MOBILE_STATUS_MAIN.ID = LIST_MOBILE_STATUS.ID_LIST_MOBILE_STATUS_MAIN
            WHERE LIST_MOBILE_STATUS.ID_LIST_MOBILE_TABLES = 1
            order by 1 asc, 3 asc
        ");

        $execTime['$db_status_tables'] = microtime(true) - $timer;
        $timer = microtime(true);

        $db_gtd_status_tables = $this->db->query("SELECT
            LIST_MOBILE_STATUS.ID as status_id,
            LIST_MOBILE_STATUS.NAME as status_name
            FROM LIST_MOBILE_STATUS
            WHERE LIST_MOBILE_STATUS.ID_LIST_MOBILE_TABLES = 2
            order by 1 asc
        ");

        $execTime['$db_gtd_status_tables'] = microtime(true) - $timer;
        $timer = microtime(true);

        $where_condition = $this->db->query("SELECT list_users.login, coalesce(list_mobile_groups_tables.where_condition, '1=1') as where_condition  FROM list_mobile_users
                                    LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
                                    left join list_mobile_groups on list_mobile_users.id_list_mobile_groups = list_mobile_groups.id
                                    left join list_mobile_groups_tables on list_mobile_groups_tables.id_list_mobile_groups = list_mobile_groups.id
                                    WHERE list_mobile_groups_tables.id_list_mobile_tables = 1 and list_users.login = '" . $login . "'");
        $execTime['$where_condition'] = microtime(true) - $timer;
        $timer = microtime(true);

        $where_condition = $where_condition[0]["WHERE_CONDITION"];
        if (empty($where_condition)) $where_condition = " 1 = 1 ";
        $where_condition = iconv('utf-8', 'cp1251', $where_condition);

        $where_condition2 = $this->db->query("SELECT list_users.login, coalesce(list_mobile_groups_tables.where_condition, '1=1') as where_condition  FROM list_mobile_users
                                    LEFT JOIN LIST_USERS ON LIST_USERS.ID = LIST_MOBILE_USERS.ID_LIST_USERS
                                    left join list_mobile_groups on list_mobile_users.id_list_mobile_groups = list_mobile_groups.id
                                    left join list_mobile_groups_tables on list_mobile_groups_tables.id_list_mobile_groups = list_mobile_groups.id
                                    WHERE list_mobile_groups_tables.id_list_mobile_tables = 2 and list_users.login = '" . $login . "'");

        $execTime['$where_condition2'] = microtime(true) - $timer;
        $timer = microtime(true);

        $where_condition2 = $where_condition2[0]["WHERE_CONDITION"];
        if (empty($where_condition2)) $where_condition2 = " 1 = 1 ";
        $where_condition2 = iconv('utf-8', 'cp1251', $where_condition2);

        if ($where_condition2 == "1=1"){
            $st_gtd_query = "select status_id as id_status_ex,
                             count(*) as cnt from (
                                SELECT LIST_GTD.ID, list_gtd.id_status_ex as status_id                         
                                FROM LIST_GTD 
                                WHERE  LIST_GTD.DELETED = 0  AND LIST_GTD.DT_CREATE > '" . date('d.m.y', strtotime('-8 month')) . "'
                                PLAN (LIST_GTD INDEX(LIST_GTD_IDX8))
                              ) 
                              group by status_id ";
        }
        else {
            $st_gtd_query = "select status_id as id_status_ex,
                             count(*) as cnt from (SELECT LIST_GTD.ID, list_gtd.id_status_ex as status_id,
                             list_customs.list_customs_number,
                             list_client.list_client_name,
                             list_gtd.declare_owner,
                             list_gtd.terminal,
                             LIST_INSPECTOR_OUT.list_inspector_name,
                             LIST_CLIENT.LIST_CLIENT_NAME as DEC_LIST_CLIENT_NAME,
                             LIST_INSPECTOR_OUT.LIST_INSPECTOR_NAME AS DEC_LIST_INSPECTOR_OUT_NAME,
                             LIST_FEACC_GTD.FEACC_TEXT as DEC_FEACC_TEXT
                             FROM LIST_GTD
                             left join list_customs on list_gtd.id_list_customs = list_customs.id
                             LEFT OUTER JOIN LIST_CLIENT ON (LIST_GTD.ID_LIST_CLIENT = LIST_CLIENT.ID)
                             LEFT OUTER JOIN LIST_INSPECTOR AS LIST_INSPECTOR_OUT ON (LIST_GTD.ID_LIST_INSPECTOR_OUT = LIST_INSPECTOR_OUT.ID)
                             LEFT OUTER JOIN LIST_FEACC_GTD ON ( LIST_GTD.ID_LIST_FEACC_GTD = LIST_FEACC_GTD.ID )
                             WHERE  LIST_GTD.DELETED = 0  AND LIST_GTD.DT_CREATE > '" . date('d.m.y', strtotime('-8 month')) . "') WHERE " . $where_condition2 . " group by status_id

        ";
        }

        if ($where_condition == "ID_DIRECTION=1 "){    // Упростим на один единственный случай, когда пользователь запрашивает записи без фильтров
            $st_query = "select status_id as id_status_ex,
                        count(*) as cnt from (select list_traffic.id,
                        list_traffic.id_status_ex as status_id,
                        list_traffic.id_direction
                        from LIST_TRAFFIC
                        WHERE LIST_TRAFFIC.deleted = 0 AND LIST_TRAFFIC.DT_CREATE > '" . date('d.m.y', strtotime('-8 month')) . "'
                        PLAN (LIST_TRAFFIC INDEX (LIST_TRAFFIC_IDX29))
                        ) WHERE " . $where_condition . " group by status_id";
        }
        else {
            $st_query = "select status_id as id_status_ex,
                        count(*) as cnt from (select list_traffic.id,
                        list_traffic.id_status_ex as status_id,
                        list_client.list_client_name,
                        list_expeditor.list_expeditor_name,
                        list_customer.list_customer_name,
                        list_terminal.list_terminal_name,
                        list_traffic.not_exportation,
                        list_traffic.id_direction,
                        list_payer.list_payer_name,
                        list_partner.list_client_name as list_partner_name,
                        list_mobile_status.name as id_status_ex,
                        list_branch.list_branch_name
                        from LIST_TRAFFIC
                        left join list_branch on list_traffic.id_list_branch = list_branch.id
                        left join list_client as list_partner on list_partner.id = list_traffic.id_list_partner
                        left join list_payer on list_traffic.id_list_payer = list_payer.id
                        left join list_client on list_client.id = list_traffic.id_list_client
                        left join list_customer on list_customer.id = list_traffic.id_list_customer
                        left join list_expeditor on list_expeditor.id = list_traffic.id_list_expeditor
                        left join list_terminal on list_terminal.id = list_traffic.id_list_terminal_dst
                        left join list_mobile_status on list_mobile_status.id = list_traffic.id_status_ex
                        WHERE LIST_TRAFFIC.deleted = 0 AND LIST_TRAFFIC.DT_CREATE > '" . date('d.m.y', strtotime('-8 month')) . "') WHERE " . $where_condition . " group by status_id";
        }

        $counts = $this->db->query($st_query);

        $execTime['$counts'] = microtime(true) - $timer;
        $timer = microtime(true);

        // var_dump($st_gtd_query);
        $gtd_counts = $this->db->query($st_gtd_query);

        $execTime['$gtd_counts'] = microtime(true) - $timer;
        $timer = microtime(true);


        // $gtd_counts = $this->db->query("select LIST_GTD.id_status_ex, count(*) as cnt from LIST_GTD WHERE LIST_GTD.deleted = 0 AND LIST_GTD.DT_CREATE > '31.12.2014' group by LIST_GTD.id_status_ex");
        // $counts =
        /*
         *SELECT
                    LIST_MOBILE_STATUS_MAIN.ID as main_id,
                    LIST_MOBILE_STATUS_MAIN.STATUS as main_name,
                    LIST_MOBILE_STATUS.ID as status_id,
                    LIST_MOBILE_STATUS.NAME as status_name
                    FROM LIST_MOBILE_STATUS_MAIN
                    LEFT JOIN LIST_MOBILE_STATUS ON LIST_MOBILE_STATUS_MAIN.ID = LIST_MOBILE_STATUS.ID_LIST_MOBILE_STATUS_MAIN

                    order by 1 asc, 3 asc




         *
         * */
        $statuses_array = array();
        $gtd_statuses_array = array();

        foreach ($db_gtd_status_tables as $key => $arr) {
            $arr = array_change_key_case($arr, CASE_LOWER);
//            if ($arr['status_id'] == 17) {

//            } else {
            $gtd_statuses_array[] = ["STATUS_ID" => $arr['status_id'], "STATUS_NAME" => $arr['status_name'], "CNT" => 0];
//            }
            foreach ($gtd_counts as $key3 => $val3) {
                if ($arr['status_id'] == $val3["ID_STATUS_EX"]) {
                    $gtd_statuses_array[$key]["CNT"] = $val3["CNT"];
                }
            }

        }
        foreach ($db_status_tables as $key => $arr) {
            $arr = array_change_key_case($arr, CASE_LOWER);

            if (!is_array($statuses_array[$arr['main_id']])) {
                $statuses_array[$arr['main_id']] = array('status_id' => $arr['main_id'], 'main_status_name' => $arr['main_name'], 'statuses' => array());
            }
            $statuses_array[$arr['main_id']]["statuses"][] = array('status_id' => $arr['status_id'], 'status_name' => $arr['status_name'], 'statuses' => array());
        }
        $statuses_array = array_values($statuses_array);
        $test = 0;
        foreach ($statuses_array as $key => $val) {
            $sub_statuses = $val["statuses"];
            $statuses_array[$key]["count"] = 0;
            foreach ($sub_statuses as $key2 => $val2) {
                $statuses_array[$key]["statuses"][$key2]["count"] = 0;
                foreach ($counts as $key3 => $val3) {
                    $test += 1;
                    if ($val2["status_id"] == $val3["ID_STATUS_EX"]) {
                        $statuses_array[$key]["statuses"][$key2]["count"] = $val3["CNT"];
                    }
                }
                $statuses_array[$key]["count"] += $statuses_array[$key]["statuses"][$key2]["count"];
            }
        }
        $tables_array = array();
        $bookmarks = array();
        foreach ($db_tables as $key => $arr) {
            $arr = array_change_key_case($arr, CASE_LOWER);

            if (!is_array($tables_array[$arr['table_id']])) {
                $tables_array[$arr['table_id']] = array('table_id' => $arr['table_id'], 'table_name' => $arr['name'], 'subtypes' => array());
            }
            $tables_array[$arr['table_id']]["subtypes"][] = array('subtype_id' => $arr['subtype_id'], 'subtype_name' => $arr['subtype_name'], 'subtypes' => array());
        }

        $tables_array = array_values($tables_array);


        $table_id = $request->get('table');
        $tab_id = $table_id;
        if (empty($table_id)) $tab_id = 1;

        $db_fields = $this->db->query("SELECT LIST_MOBILE_FIELDS.ID,
            LIST_MOBILE_FIELDS.TITLE,
            LIST_MOBILE_FIELDS.\"TYPE\",
            LIST_MOBILE_FIELDS.FIELD_TABLE,
            LIST_MOBILE_FIELDS.REAL_TABLE_NAME,
            LIST_MOBILE_FIELDS.DICT_ID_NAME,
            LIST_MOBILE_FIELDS.\"NAME\"
            FROM list_mobile_groups_fields
            LEFT JOIN LIST_MOBILE_FIELDS on LIST_MOBILE_FIELDS.id = list_mobile_groups_fields.id_list_mobile_fields
            where list_mobile_fields.id_list_mobile_tables = 1 and list_mobile_groups_fields.id_list_mobile_groups = (
            select list_mobile_users.id_list_mobile_groups from list_mobile_users LEFT JOIN LIST_USERS ON LIST_MOBILE_USERS.ID_LIST_USERS = LIST_USERS.ID
            WHERE LIST_USERS.LOGIN = '" . $login . "')
        ");

        $execTime['$db_fields'] = microtime(true) - $timer;
        $timer = microtime(true);

        $dec_fields = array();
        if (empty($table_id)) {
            $dec_fields = $this->db->query("SELECT
                ID,
                TITLE,
                TYPE,
                FIELD_TABLE,
                REAL_TABLE_NAME,
                DICT_ID_NAME,
                NAME
                FROM LIST_MOBILE_FIELDS
                WHERE ID_LIST_MOBILE_TABLES = 2
            ");
        }

        $doc_nomenclatures = $this->db->query("select LIST_DOC_NOMENCLATURE.id, LIST_DOC_NOMENCLATURE.LIST_DOC_NOMENCLATURE_NAME from LIST_DOC_NOMENCLATURE WHERE LIST_DOC_NOMENCLATURE.DELETED = 0");

        $execTime['$doc_nomenclatures'] = microtime(true) - $timer;

        $fields_array = array();
        foreach ($db_fields as $key => $arr) {
            $fields_array[$key] = array_change_key_case($arr, CASE_LOWER);
        }

        $execTime['overAll'] = microtime(true) - $timerOverAll;

        $response = ["test" => $test, "timings" => $execTime,  "orders" => $order[0], "statuses" => $statuses_array, "gtd_statuses" => $gtd_statuses_array, "doc_nomenclatures" => $doc_nomenclatures, "cnts" => $counts, "custom_bookmarks" => $db_custom_bookmarks, "access_token" => $app["session"]->get("new_access_token"), "expires_in" => time() + 7 * 24 * 60 * 60, "dec_fields" => $dec_fields, "fields" => $db_fields, "tables" => $tables_array, "bookmarks" => $db_bookmarks];
        return $app->json($response);
    }

    public function checkAuth(Request $request, Application $app)
    {

        $access_token = $request->get('access_token');
        //  $client_hash = $request->get('hs');
        if (null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }
        $response = ["access_token" => $app["session"]->get("new_access_token"), "expires_in" => time() + 7 * 24 * 60 * 60];
        return $app->json($response);
    }


    public function getPushes(Request $request, Application $app)
    {
        $type = $request->get('type');
        $type_c = '';
        $search_c = '';
        $search = $request->get('search');
        if (!(empty($type))) {
            $type_c = ' AND "TYPE" = ' . $type;
        }
        if (!(empty($search))) {

            $search_c = ' AND ( UPPER(CAST(TEXT AS varchar(1000) CHARACTER SET WIN1251) COLLATE PXW_CYRL) LIKE \'%' . iconv('utf-8', 'cp1251', $search) . '%\' OR UPPER(CAST(TITLE AS varchar(200) CHARACTER SET WIN1251) COLLATE PXW_CYRL) LIKE \'%' . iconv('utf-8', 'cp1251', $search) . '%\') ';
        }
        $user_id = $this->db->query('SELECT list_users.id as UID from list_users
            join list_mobile_users on list_users.id = list_mobile_users.id_list_users
            join list_mobile_devices on list_mobile_devices.id_list_mobile_users = list_mobile_users.id
            where list_mobile_devices.access_token = \'' . $request->get('access_token') . '\'');
        $user_id = $user_id[0]["UID"];
        $offset = $request->get('offset');
        $rows_offset = " ORDER BY DT_CREATE DESC ROWS " . ($offset + 1) . " TO " . (20 + $offset) . " ";
        $pushes = $this->db->query('SELECT * FROM list_mobile_pushes WHERE USER_ID = ' . $user_id . $search_c . ' ' . $type_c . $rows_offset);
        $response = ["access_token" => $app["session"]->get("new_access_token"), "expires_in" => time() + 7 * 24 * 60 * 60, "pushes" => $pushes];
        return $app->json($response);
    }

    public function requestFields(Request $request, Application $app)
    {
        if (null === $app['session']->get('new_access_token')) {
            return $this->generate_error($app, 401, "No new access_token");
        }
        $table_id = $request->get('table_id');
        $login = $request->get('login');
        $order = $request->get('order');
        if (empty($table_id) || !is_numeric($table_id)) $table_id = 1;

        $view_type = $request->get('type');
        if (empty($view_type) || !is_numeric($view_type)) $view_type = 1;

        $device_id = $app["session"]->get("id_list_mobile_devices");

        if (empty($device_id) || !is_numeric($device_id)) {
            //return $this->generate_error($app, 401, "Incorrect device id");
            $device_id = 1;
        }
        if (!empty($order)) {
            $update_field = "FIELD_ORDER";
            if ($table_id == 2) $update_field = "DEC_ORDER";
            $req_up = $this->db->query("UPDATE LIST_MOBILE_USERS SET " . $update_field . " = '" . $order . "'
            WHERE ID_LIST_USERS = (SELECT ID FROM LIST_USERS WHERE LIST_USERS.LOGIN = '" . $login . "')");
        }
        $db_fields = $this->db->query("SELECT
            TITLE,
            TYPE,
            NAME 
            FROM LIST_MOBILE_FIELDS
            WHERE ID_LIST_MOBILE_TABLES = " . $table_id . "
        ");

        $av_fields = array();
        foreach ($db_fields as $key => $arr) {
            $av_fields[$key] = array_change_key_case($arr, CASE_LOWER);
        }

        $fields_commas = $request->get('fields');
        if (empty($fields_commas)) {
            return $this->generate_error($app, 404, "Fields are empty");
        }
        $fields = explode(',', $fields_commas);

        if (empty($fields)) {
            return $this->generate_error($app, 404, "Fields are empty");
        }

        $granted_fields = array();
        foreach ($av_fields as $av_field) {
            $k = array_search($av_field['name'], $fields);
            if ($k !== false) {
                $granted_fields[] = $fields[$k];
                unset($fields[$k]);
            }
        }

        /*  if(!empty($fields)){
              return $this->generate_error($app, 404, "Unrecognized fields: ".implode(',', $fields));
          } //just ignore other fields
  */
        // all requested fields are granted

        ///types : 1 - search
        // 2 - push
        // 3 - wtf

        $q = "DELETE FROM LIST_MOBILE_DEVICES_FIELDS WHERE 
                    VIEW_TYPE = " . $view_type . " AND
                    ID_LIST_MOBILE_DEVICES = " . $device_id . " AND
                    ID_LIST_MOBILE_TABLES = " . $table_id . "";

        $this->db->query($q);

        foreach ($granted_fields as $field) {
            //1. remove all non-granted fields


            $ins = "INSERT INTO LIST_MOBILE_DEVICES_FIELDS(VIEW_TYPE, 
                ID_LIST_MOBILE_DEVICES,
                ID_LIST_MOBILE_TABLES,
                NAME
                ) VALUES (
                    " . $view_type . ",
                    " . $device_id . ",
                    " . $table_id . ",
                    '" . strtoupper($field) . "'
                )";
            $res = $this->db->query($ins);
        }

        $response = ["access_token" => $app["session"]->get("new_access_token"), "expires_in" => time() + 7 * 24 * 60 * 60, "success" => true, "granted_fields" => implode(',', $granted_fields)];
        return $app->json($response);
    }
}
