<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Connection;
use Giny\ConnectionManager\CommandBuilder\CommandBuilder;
use Giny\ConnectionManager\Helper\Helper;
use Giny\ConnectionManager\SqlDialect\Dialect;
use Giny\ConnectionManager\TableSchema\TableSchemaBuilder;


class ConnectionPool
{
	public $schemaCachingDuration;
	public $schemaCachingExclude;
	public $schemaCacheID;
	public $schemaConnectionName;

	protected $_tableSchemas = array();
	protected $_commandBuilder;
	protected $_dialect;

	/**
	 * @param $tableName
	 * @return \CDbTableSchema
	 */
	public function getTableSchema($tableName)
	{
		if (!isset($this->_tableSchemas[$tableName]))		{
			$this->_tableSchemas[$tableName] = TableSchemaBuilder::factory($tableName,$this->getDialect(),$this->getConnection());
		}

		return $this->_tableSchemas[$tableName];
	}

	public function getCommandBuilder()
	{
		return CommandBuilder::factory($this->getDialect());
	}

	public function getDialect()
	{
		if ($this->_dialect === null){
			$driverName = Helper::getDriverName($this->getConnectionString());
			$this->_dialect = Dialect::factory($driverName);
		}

		return $this->_dialect;
	}

	public function getCache()
	{
		try{
			$cache = \Yii::app()->cache;
		}catch (\CException $e){
			$cache = null;
		}

		return $cache;
	}

	/**
	 * @return string
	 */
	protected function getConnectionString()
	{
		return '';
	}
}