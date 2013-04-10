<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ivan Matveev
 * Date: 09.04.13
 * Time: 19:50
 * To change this template use File | Settings | File Templates.
 */

interface IDbPool
{

    /**
     * @param $forceMaster bool
     * @return CDbConnection
     */
    public function getReadConnection($forceMaster);

    /**
     * @return CDbConnection
     */
    public function getWriteConnection();

    /**
     * @param $sql string
     * @param $forceMaster bool
     * @return CDbConnection
     */
    public function getConnectionFromSql($sql,$forceMaster);

}