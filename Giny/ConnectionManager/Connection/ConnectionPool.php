<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Connection;

use Giny\ConnectionManager\Helper\Helper;
use Giny\ConnectionManager\Query\QueryBuilder;
use Giny\ConnectionManager\SqlDialect\Dialect;
use Giny\ConnectionManager\SqlDialect\DialectInterface;
use Giny\ConnectionManager\TableSchema\TableSchemaBuilder;
use Giny\ConnectionManager\TransactionManager\Exception;


class ConnectionPool implements ConnectionPoolInterface
{
	public $schemaCachingDuration;
	public $schemaCachingExclude;
	public $schemaCacheID = 'cache';
	public $schemaConnectionName;
	public $tablePrefix;

	/**
	 * @var ConnectionInterface[]
	 */
	public $connections;

	/**
	 * @var string ConnectionPool name
	 */
	public $name;

	/**
	 * @var string $poolConnections[$masterKey]=>IDbConnection
	 */
	protected $masterKey;

	/**
	 * @var array Same as master key, but array of slave keys in the pool.
	 */
	protected $slaveKeys = array();

	protected $activeConnections = array();

	/**
	 * @var boolean whether to log the values that are bound to a prepare SQL statement.
	 * Defaults to false. During development, you may consider setting this property to true
	 * so that parameter values bound to SQL statements are logged for debugging purpose.
	 * You should be aware that logging parameter values could be expensive and have significant
	 * impact on the performance of your application.
	 */
	public $enableParamLogging=false;

	/**
	 * @var boolean whether to enable profiling the SQL statements being executed.
	 * Defaults to false. This should be mainly enabled and used during development
	 * to find out the bottleneck of SQL executions.
	 */
	public $enableProfiling=false;


	protected $_tableSchemas = array();
	protected $_queryBuilder;
	protected $_dialect;


	public function init()
	{
		$this->analyzeConnections();
	}

	/**
	 * Sets master/slave keys for connections
	 */
	protected function analyzeConnections()
	{
		if ($this->connections === null)
			throw new Exception(\Yii::t('giny','No connection has been defined'));

		if (count($this->connections) === 1)
		{
			$key = key($this->connections);
			$this->masterKey = $key;
			$this->slaveKeys[] = $key;
			return true;
		}

		foreach ($this->connections as $key=>$connection)
		{
			if (isset($connection['type']))
			{
				if ($connection['type'] === Connection::TYPE_MASTER)
				{
					if ($this->masterKey !== null)
						throw new Exception(\Yii::t('giny','There can be no two master connections in one connection pool'));
					else
						$this->masterKey = $key;
				}
			}

			if (!isset($connection['type']) || (isset($connection['type']) && $connection['type']===Connection::TYPE_SLAVE))
			{
				$this->slaveKeys[]=$key;
			}
		}

		if ($this->masterKey === null)
			throw new Exception(\Yii::t('giny','You should have at least one master connection'));

		return true;
	}

	/**
	 * @return ConnectionInterface
	 */
	public function getWriteConnection()
	{
		$this->getConnection($this->masterKey);
	}


	/**
	 * @return ConnectionInterface
	 */
	public function getSchemaConnection()
	{
		if ($this->schemaConnectionName!==null)
		{
			return $this->getConnection($this->schemaConnectionName);
		}

		return $this->getReadConnection();
	}

	/**
	 * @param bool $forceMaster
	 * @return ConnectionInterface
	 */
	public function getReadConnection($forceMaster = false)
	{
		if ($forceMaster){
			return $this->getWriteConnection();
		}

//		if ($this->getCurrentTransaction() instanceof CDbTransaction)
//			return $this->getWriteConnection();

		if (!$connection = $this->getActiveSlaveConnection()){
			shuffle($this->slaveKeys);
			$connection = $this->getConnection($this->slaveKeys[0]);
		}

		return $connection;
	}

	protected function getActiveSlaveConnection()
	{
		foreach($this->slaveKeys as $key)
		{
			if (isset($this->activeConnections[$key]))
				return $this->activeConnections[$key];
		}

		return false;
	}

	/**
	 * @param string $name
	 * @throws Exception
	 * @return ConnectionInterface
	 */
	protected function getConnection($name)
	{
		if (isset($this->activeConnections[$name])){
			return $this->activeConnections[$name];
		}

		$connection = $this->connections[$name];
		if (!isset($connection['class']))
			$connection['class'] = 'Giny\ConnectionManager\Connection\Connection';

		$connection = \Yii::createComponent($connection);

		if ($connection instanceof ConnectionInterface)
		{
			$this->activeConnections[$name]=$connection;
			return $connection;
		}

		throw new Exception(\Yii::t('giny','Connection class doesn\'t inherit IDbConnection interface'));
	}



	/**
	 * @param $tableName
	 * @return \CDbTableSchema
	 */
	public function getTableSchema($tableName)
	{
		if (!isset($this->_tableSchemas[$tableName])){


			/** @var $cache \ICache */
			$cache=\Yii::app()->getComponent($this->schemaCacheID);
			if($this->cacheable($tableName) && ($cache!==null)){
				$key = $this->generateCacheKey($tableName);
				$table=$cache->get($key);
				if ($table === false){
					$table = TableSchemaBuilder::factory($tableName,$this->getDialect(),$this->getSchemaConnection(),$this->tablePrefix);
					$cache->set($key,$table,$this->schemaCachingDuration);
				}
				$this->_tableSchemas[$tableName] = $table;
			}else{
				$this->_tableSchemas[$tableName] = TableSchemaBuilder::factory($tableName,$this->getDialect(),$this->getSchemaConnection(),$this->tablePrefix);
			}

		}
		return $this->_tableSchemas[$tableName];
	}

	protected function cacheable($tableName)
	{
		return (!isset($this->schemaCachingExclude[$tableName]) && ($this->schemaCachingDuration)>0 && $this->schemaCacheID!==false);
	}

	protected function generateCacheKey($tableName)
	{
		return 'yii:dbschema'.$this->getSchemaConnection()->getConnectionString().':'.$this->getSchemaConnection()->getUserName().':'.$tableName;
	}

	/**
	 * @return QueryBuilder
	 */
	public function getQueryBuilder()
	{
		return new QueryBuilder($this->getDialect());
	}

	/**
	 * @return DialectInterface
	 */
	public function getDialect()
	{
		if ($this->_dialect === null){
			$driverName = Helper::getDriverName($this->getConnectionStringFromConfig());
			$this->_dialect = Dialect::factory($driverName);
		}

		return $this->_dialect;
	}

	protected function getConnectionStringFromConfig()
	{
		return $this->connections[$this->masterKey]['connectionString'];
	}

	public function getTablePrefix()
	{
		return $this->tablePrefix;
	}

	public function getEnableParamLogging()
	{
		return $this->enableParamLogging;
	}

	public function getEnableProfiling()
	{
		return $this->enableProfiling;
	}

	/**
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


}