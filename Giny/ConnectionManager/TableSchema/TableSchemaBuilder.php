<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\TableSchema;


use Giny\ConnectionManager\Connection\ConnectionInterface;
use Giny\ConnectionManager\SqlDialect\DialectInterface;

abstract class TableSchemaBuilder
{
	/**
	 * @var ConnectionInterface
	 */
	protected $_connection;
	/**
	 * @var DialectInterface
	 */
	protected $_dialect;

	public function __construct(ConnectionInterface $connection,DialectInterface $dialect)
	{
		$this->_connection = $connection;
		$this->_dialect = $dialect;
	}

	public static function factory($tableName,DialectInterface $dialect, ConnectionInterface $connection,$tablePrefix)
	{
		$className = __NAMESPACE__.get_class($dialect).'SchemaBuilder';
		/** @var $instance TableSchemaBuilder */
		$instance = new $className($connection, $dialect);
		return $instance->getTable($tableName, $tablePrefix);
	}

	/**
	 * Obtains the metadata for the named table.
	 * @param string $name table name
	 * Parameter available since 1.1.9
	 * @param $tablePrefix
	 * @return \CDbTableSchema table metadata. Null if the named table does not exist.
	 */
	public function getTable($name,$tablePrefix)
	{

			if($tablePrefix!==null && strpos($name,'{{')!==false)
				$realName=preg_replace('/\{\{(.*?)\}\}/',$tablePrefix.'$1',$name);
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