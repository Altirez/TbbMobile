<?php
/**
 * Created by PhpStorm.
 * User: asaelko
 * Date: 26.12.14
 * Time: 17:38
 */

class ApiError {
    public $error;

    public function __construct($text){
        $this->error = $text;
    }
}