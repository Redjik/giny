<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\TableSchema;


abstract class TableSchemaBuilder
{
	protected $_connection;

	public function __construct($connection)
	{
		$this->_connection = $connection;
	}

	public static function factory($tableName,$driverType,$connection)
	{
		$className = __NAMESPACE__.$driverType.'SchemaBuilder';
		/** @var $builder TableSchemaBuilder */
		$builder = new $className($connection);
		return $builder->getTable($tableName);
	}

	/**
	 * Obtains the metadata for the named table.
	 * @param string $name table name
	 * Parameter available since 1.1.9
	 * @return \CDbTableSchema table metadata. Null if the named table does not exist.
	 */
	public function getTable($name)
	{

			if($this->_connection->tablePrefix!==null && strpos($name,'{{')!==false)
				$realName=preg_replace('/\{\{(.*?)\}\}/',$this->_connection->tablePrefix.'$1',$name);
			else
				$realName=$name;


			return $this->loadTable($realName);
	}


	/**
	 * Loads the metadata for the specified table.
	 * @param string $name table name
	 * @return \CDbTableSchema driver dependent table metadata, null if the table does not exist.
	 */
	abstract protected function loadTable($name);
}