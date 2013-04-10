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
     * @param bool $forceMaster
     * @return CDbConnection
     */
    public function getReadConnection($forceMaster);

    /**
     * @return CDbConnection
     */
    public function getWriteConnection();

    /**
     * Returns pool with connections.
     * Overload getDb in AR, to get particular pool.
     * @param string $name Name of the pool
     * @return IDbPool
     */
    public function getPool($name);

}