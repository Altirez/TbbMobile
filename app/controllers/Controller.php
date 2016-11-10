<?php
/**
 * Created by PhpStorm.
 * User: asaelko
 * Date: 26.12.14
 * Time: 16:41
 */

require_once __DIR__.'/../database/DB.php';
require_once __DIR__."/../models/ApiError.php";
class Controller {
    public  $db;

    public function __contruct(){
        $this->db = new DB();
    }

    protected static function generate_error($app, $code, $text){
        $api_error = new ApiError($text);
        return $app->json($api_error, $code);
    }
}