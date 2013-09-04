<?php
/**
 * Created by JetBrains PhpStorm.
 * User: Ivan Matveev
 * Date: 28.08.13
 * Time: 18:25
 * To change this template use File | Settings | File Templates.
 */

interface IDbSchema
{
	/**
	 * @param GConnectionManager $pool
	 * @param array $config
	 * @return IDbSchema
	 */
	public static function factory(GConnectionManager $pool,array $config);

	/**
	 * Quotes a table name for use in a query.
	 * If the table name contains schema prefix, the prefix will also be properly quoted.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 * @see quoteSimpleTableName
	 */
	public function quoteTableName($name);

	/**
	 * Quotes a column name for use in a query.
	 * If the column name contains prefix, the prefix will also be properly quoted.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 * @see quoteSimpleColumnName
	 */
	public function quoteColumnName($name);

	/**
	 * Obtains the metadata for the named table.
	 * @param string $name table name
	 * @param boolean $refresh if we need to refresh schema cache for a table.
	 * Parameter available since 1.1.9
	 * @return CDbTableSchema table metadata. Null if the named table does not exist.
	 */
	public function getTable($name,$refresh=false);

	/**
	 * Refreshes the schema.
	 * This method resets the loaded table metadata and command builder
	 * so that they can be recreated to reflect the change of schema.
	 */
	public function refresh();

}