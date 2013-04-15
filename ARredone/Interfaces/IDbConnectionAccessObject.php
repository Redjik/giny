<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ivan Matveev
 * Date: 09.04.13
 * Time: 19:50
 * To change this template use File | Settings | File Templates.
 */

interface IDbConnectionAccessObject
{

    /**
     * @param bool $forceMaster
     * @return IDbConnection
     */
    public function getReadConnection($forceMaster = false);

    /**
     * @return IDbConnection
     */
    public function getWriteConnection();

    /**
     * Returns pool with connections.
     * Overload getDb in AR, to get particular pool.
     * @param string $name Name of the pool
     * @return IDbConnectionAccessObject
     */
    public function getPool($name);

    /**
     * @param $query
     * @param bool $forceMaster
     * @return GDbCommand
     */
    public function createCommand($query,$forceMaster = false);

    /**
     * @return CDbSchema the database schema
     */
    function getSchema();

    /**
     * @return IDbConnection
     */
    function getSchemaConnection();
}