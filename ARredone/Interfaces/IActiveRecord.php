<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ivan Matveev
 * Date: 11.04.13
 * Time: 19:40
 * To change this template use File | Settings | File Templates.
 */

interface IActiveRecord
{
    /**
     * Can be overridden for the sake of switching pool
     * @return IDbConnectionAccessObject
     */
    public function getPool();

    /**
     * @return IDbSchemaAccessObject
     */
    public function getSchema();

    /**
     * Returns the command builder used by this AR.
     * @return CDbCommandBuilder the command builder
     */
    public function getCommandBuilder();
}