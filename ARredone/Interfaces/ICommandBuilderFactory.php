<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ivan Matveev
 * Date: 11.04.13
 * Time: 19:58
 * To change this template use File | Settings | File Templates.
 */

interface ICommandBuilderFactory
{
    /**
     * @param CDbSchema $schema
     * @return CDbCommandBuilder
     */
    public static function factory(CDbSchema $schema);
}