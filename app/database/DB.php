<?php
/**
 * Created by PhpStorm.
 * User: asaelko
 * Date: 26.12.14
 * Time: 16:35
 */

require_once __DIR__ . '/../config/Config.php';

class DB
{
    private $connection;

    public function __construct()
    {

        $db = Config::$db;
        $this->connection = ibase_connect(
            $db['host'] . "/" . $db['port'] . ":" . $db['database'],
            $db['user'],
            $db['password']
        );

    }


    /**
     * @return bool
     */
    public function isConnected()
    {
        return (bool)$this->connection;
    }

    public function query($query, $assoc = true, $isEncoding = true){
        $res =  ibase_query($query);

        if(!$res){
            return ["success"=>false, "error"=>ibase_errmsg(), "query"=>$query];
        }

        $rows = [];

        $fetch_func = ($assoc ? 'ibase_fetch_assoc':'ibase_fetch_row');

        while($row = @call_user_func($fetch_func, $res, IBASE_TEXT | IBASE_UNIXTIME)){

            foreach($row as $k=>$v){
                if ($isEncoding) {
                    $row[$k] = iconv("CP1251", "UTF-8", $v);
                }
            }
            $rows[] = $row;
        }

        return $rows;
    }


}