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

class ClientController extends Controller
{
    public function __construct(){
        parent::__contruct();
    }


    public function customerIndex(Request $request, Application $app){
        $clients = $this->db->query("SELECT
            LIST_CUSTOMER.ID,
            LIST_CUSTOMER.LIST_CUSTOMER_NAME
            FROM LIST_CUSTOMER
            WHERE LIST_CUSTOMER.DELETED = 0
            ORDER BY LIST_CUSTOMER_NAME ASC
        ");

        $cl = [];
        foreach($clients as $client){
            $cl[] = array("id"=>$client["ID"], "name" => $client["LIST_CUSTOMER_NAME"]);
        }

        $res = new stdClass;
        $res->results = $cl;


        return $app->json($res);
    }
    

    public function index(Request $request, Application $app){
        $clients = $this->db->query("SELECT
            LIST_CLIENT.ID,
            LIST_CLIENT.LIST_CLIENT_NAME
            FROM LIST_CLIENT
            WHERE LIST_CLIENT.DELETED = 0
            ORDER BY LIST_CLIENT_NAME ASC
        ");

        $cl = [];
        foreach($clients as $client){
            $cl[] = array("id"=>$client["ID"], "name" => $client["LIST_CLIENT_NAME"]);
        }

        $res = new stdClass;
        $res->results = $cl;


        return $app->json($res);
    }


}
