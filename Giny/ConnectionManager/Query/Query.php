<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Query;

use Giny\ConnectionManager\Connection\ConnectionInterface;
use Giny\ConnectionManager\Connection\ConnectionPoolInterface;

class Query
{

	/**
	 * @var ConnectionPoolInterface|null
	 */
	protected  $_pool;
	protected $_forceMaster;

	/**
	 * @var \PDOStatement
	 */
	protected $_values=array();
	protected $_text;
	protected $_params=array();
	protected $_paramLog=array();

	protected $_fetchMode = array(\PDO::FETCH_ASSOC);

	/**
	 * @param $sql string
	 * @param array $params
	 * @param array $values
	 * @param ConnectionPoolInterface|null $pool
	 */
	public function __construct($sql, $params = array(), $values = array(), $pool = null)
	{
		$this->setText($sql);
		$this->bindValues($values);
		$this->bindParams($params);

		if ($pool instanceof ConnectionPoolInterface){
			$this->setConnectionPool($pool);
		}
	}

	/**
	 * Set the statement to null when serializing.
	 * @return array
	 */
	public function __sleep()
	{
		return array_keys(get_object_vars($this));
	}

	/**
	 * Set the default fetch mode for this statement
	 * @return Query
	 * @see http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
	 * @since 1.1.7
	 */
	public function setFetchMode()
	{
		$params=func_get_args();
		$this->_fetchMode = $params;
		return $this;
	}

	public function setConnectionPool(ConnectionPoolInterface $pool)
	{
		$this->_pool = $pool;
	}

	/**
	 * @return ConnectionPoolInterface
	 */
	protected function getConnectionPool()
	{
		if ($this->_pool === null){
			$this->_pool = \Yii::app()->db->getDefaultConnectionPool();
		}

		return $this->_pool;
	}

	/**
	 * Cleans up the command and prepares for building a new query.
	 * This method is mainly used when a command object is being reused
	 * multiple times for building different queries.
	 * Calling this method will clean up all internal states of the command object.
	 * @return Query this command instance
	 * @since 1.1.6
	 */
	public function reset()
	{
		$this->_text=null;
		$this->_params=array();
		$this->_values=array();
		$this->_paramLog=array();
		return $this;
	}

	/**
	 * @return string the SQL statement to be executed
	 */
	public function getText()
	{
		return $this->_text;
	}

	/**
	 * Specifies the SQL statement to be executed.
	 * Any previous execution will be terminated or cancel.
	 * @param string $value the SQL statement to be executed
	 * @return void
	 */
	protected function setText($value)
	{
		$this->_text=$value;
	}

	protected function checkTablePrefix()
	{
		if($this->getConnectionPool()->getTablePrefix()!==null)
			$this->_text=preg_replace('/{{(.*?)}}/',$this->getConnectionPool()->getTablePrefix().'\1',$this->_text);
	}

	/**
	 * Prepares the SQL statement to be executed.
	 * For complex SQL statement that is to be executed multiple times,
	 * this may improve performance.
	 * For SQL statement with binding parameters, this method is invoked
	 * automatically.
	 *
	 * @param ConnectionInterface $connection
	 * @return \PDOStatement
	 * @throws Exception if Query failed to prepare the SQL statement
	 */
	protected function prepare(ConnectionInterface $connection)
	{
		$statement = $this->createStatement($connection);
		$this->bindValuesInternal($statement);
		$this->bindParamsInternal($statement);
		return $statement;
	}

	/**
	 * @param ConnectionInterface $connection
	 * @return \PDOStatement
	 * @throws Exception
	 */
	protected function createStatement(ConnectionInterface $connection)
	{
		try
		{
			$statement=$connection->getPdoInstance()->prepare($this->getText());
			$this->_paramLog=array();
		}
		catch(Exception $e)
		{
			\Yii::log('Error in preparing SQL: '.$this->getText(),\CLogger::LEVEL_ERROR,'giny.db.Query');
			$errorInfo=$e instanceof \PDOException ? $e->errorInfo : null;
			throw new Exception(\Yii::t('yii','GDbCommand failed to prepare the SQL statement: {error}',
				array('{error}'=>$e->getMessage())),(int)$e->getCode(),$errorInfo);
		}

		return $statement;
	}


	/**
	 * Binds a parameter to the SQL statement to be executed.
	 * @param mixed $name Parameter identifier. For a prepared statement
	 * using named placeholders, this will be a parameter name of
	 * the form :name. For a prepared statement using question mark
	 * placeholders, this will be the 1-indexed position of the parameter.
	 * @param mixed $value Name of the PHP variable to bind to the SQL statement parameter
	 * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
	 * @param integer $length length of the data type
	 * @param mixed $driverOptions the driver-specific options (this is available since version 1.1.6)
	 * @return Query the current command being executed
	 * @see http://www.php.net/manual/en/function.PDOStatement-bindParam.php
	 */
	public function bindParam($name, &$value, $dataType=null, $length=null, $driverOptions=null)
	{
		if ($value instanceof Param){
			$this->_params[$name]=$value;
		}else{
			$this->_params[$name] = new Param($value,$dataType, $length, $driverOptions);
		}
		return $this;
	}

	/**
	 * @param \PDOStatement $statement
	 */
	protected function bindParamsInternal(\PDOStatement $statement)
	{
		/** @var $value Param */
		foreach ($this->_params as $name=>$value)
		{
			$statement->bindParam($name,$value->getValue(),$value->getDataType(),$value->getLength(),$value->getDriverOptions());
			$this->_paramLog[$name]=&$value->getValue();
		}
	}

	/**
	 * Binds a value to a parameter.
	 * @param mixed $name Parameter identifier. For a prepared statement
	 * using named placeholders, this will be a parameter name of
	 * the form :name. For a prepared statement using question mark
	 * placeholders, this will be the 1-indexed position of the parameter.
	 * @param mixed $value The value to bind to the parameter
	 * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
	 * @return Query the current command being executed
	 * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
	 */
	public function bindValue($name, $value, $dataType=null)
	{
		if ($value instanceof Value){
			$this->_values[$name]=$value;
		}else{
			$this->_values[$name] = new Value($value,$dataType);
		}

		return $this;
	}

	/**
	 * @param \PDOStatement $statement
	 */
	protected function bindValuesInternal(\PDOStatement $statement)
	{
		/** @var $value Value */
		foreach ($this->_values as $name=>$value)
		{
			$statement->bindValue($name,$value->getValue(),$value->getDataType());
			$this->_paramLog[$name]=$value->getValue();
		}
	}

	/**
	 * Binds a list of values to the corresponding parameters.
	 * This is similar to {@link bindValue} except that it binds multiple values.
	 * Note that the SQL data type of each value is determined by its PHP type.
	 * @param array $values the values to be bound. This must be given in terms of an associative
	 * array with array keys being the parameter names, and array values the corresponding parameter values.
	 * For example, <code>array(':name'=>'John', ':age'=>25)</code>.
	 * @return Query the current command being executed
	 */
	public function bindValues($values)
	{
		foreach($values as $name=>$value)
		{
			$this->bindValue($name,$value);
		}
		return $this;
	}

	/**
	 * Binds a list of values to the corresponding parameters.
	 * This is similar to {@link bindValue} except that it binds multiple values.
	 * Note that the SQL data type of each value is determined by its PHP type.
	 * @param array $values the values to be bound. This must be given in terms of an associative
	 * array with array keys being the parameter names, and array values the corresponding parameter values.
	 * For example, <code>array(':name'=>'John', ':age'=>25)</code>.
	 * @return Query the current command being executed
	 */
	public function bindParams($values)
	{
		foreach($values as $name=>$value)
		{
			$this->bindParam($name,$value);
		}
		return $this;
	}

	/**
	 * Executes the SQL statement.
	 * This method is meant only for executing non-query SQL statement.
	 * No result set will be returned.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return integer number of rows affected by the execution.
	 * @throws Exception execution failed
	 */
	public function execute($params=array())
	{
		$connection = $this->getWriteConnection();

		if ($this->getConnectionPool()->isOtherDbTransactionStarted()){
			throw new Exception('You can not save to other DB during transaction');
		}

		$this->checkTablePrefix();

		if($this->getConnectionPool()->getEnableParamLogging() && ($pars=array_merge($this->_paramLog,$params))!==array())
		{
			$p=array();
			foreach($pars as $name=>$value)
				$p[$name]=$name.'='.var_export($value,true);
			$par='. Bound with ' .implode(', ',$p);
		}
		else
			$par='';
		\Yii::trace('Executing SQL: '.$this->getText().$par,'giny.db.Query');
		try
		{
			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::beginProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','giny.db.Query.execute');

			$statement = $this->prepare($connection);
			if($params===array())
				$statement->execute();
			else
				$statement->execute($params);
			$n=$statement->rowCount();

			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::endProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','giny.db.Query.execute');

			return $n;
		}
		catch(\Exception $e)
		{
			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::endProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','giny.db.Query.execute');

			$errorInfo=$e instanceof \PDOException ? $e->errorInfo : null;
			$message=$e->getMessage();
			\Yii::log(\Yii::t('yii','GDbCommand::execute() failed: {error}. The SQL statement executed was: {sql}.',
				array('{error}'=>$message, '{sql}'=>$this->getText().$par)),\CLogger::LEVEL_ERROR,'giny.db.Query');

			if(YII_DEBUG)
				$message.='. The SQL statement executed was: '.$this->getText().$par;

			throw new Exception(\Yii::t('yii','GDbCommand failed to execute the SQL statement: {error}',
				array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
		}
	}

	/**
	 * Executes the SQL statement and returns query result.
	 * This method is for executing an SQL query that returns result set.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return \CDbDataReader the reader object for fetching the query result
	 * @throws Exception execution failed
	 */
	public function query($params=array())
	{
		return $this->queryInternal('',0,$params);
	}

	/**
	 * Executes the SQL statement and returns all rows.
	 * @param boolean $fetchAssociative whether each row should be returned as an associated array with
	 * column names as the keys or the array keys are column indexes (0-based).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return array all rows of the query result. Each array element is an array representing a row.
	 * An empty array is returned if the query results in nothing.
	 * @throws Exception execution failed
	 */
	public function queryAll($fetchAssociative=true,$params=array())
	{
		return $this->queryInternal('fetchAll',$fetchAssociative ? $this->_fetchMode : \PDO::FETCH_NUM, $params);
	}

	/**
	 * Executes the SQL statement and returns the first row of the result.
	 * This is a convenient method of {@link query} when only the first row of data is needed.
	 * @param boolean $fetchAssociative whether the row should be returned as an associated array with
	 * column names as the keys or the array keys are column indexes (0-based).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return mixed the first row (in terms of an array) of the query result, false if no result.
	 * @throws Exception execution failed
	 */
	public function queryRow($fetchAssociative=true,$params=array())
	{
		return $this->queryInternal('fetch',$fetchAssociative ? $this->_fetchMode : \PDO::FETCH_NUM, $params);
	}

	/**
	 * Executes the SQL statement and returns the value of the first column in the first row of data.
	 * This is a convenient method of {@link query} when only a single scalar
	 * value is needed (e.g. obtaining the count of the records).
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return mixed the value of the first column in the first row of the query result. False is returned if there is no value.
	 * @throws Exception execution failed
	 */
	public function queryScalar($params=array())
	{
		$result=$this->queryInternal('fetchColumn',0,$params);
		if(is_resource($result) && get_resource_type($result)==='stream')
			return stream_get_contents($result);
		else
			return $result;
	}

	/**
	 * Executes the SQL statement and returns the first column of the result.
	 * This is a convenient method of {@link query} when only the first column of data is needed.
	 * Note, the column returned will contain the first element in each row of result.
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @return array the first column of the query result. Empty array if no result.
	 * @throws Exception execution failed
	 */
	public function queryColumn($params=array())
	{
		return $this->queryInternal('fetchAll',array(\PDO::FETCH_COLUMN, 0),$params);
	}

	/**
	 * @param string $method method of PDOStatement to be called
	 * @param mixed $mode parameters to be passed to the method
	 * @param array $params input parameters (name=>value) for the SQL execution. This is an alternative
	 * to {@link bindParam} and {@link bindValue}. If you have multiple input parameters, passing
	 * them in this way can improve the performance. Note that if you pass parameters in this way,
	 * you cannot bind parameters or values using {@link bindParam} or {@link bindValue}, and vice versa.
	 * Please also note that all values are treated as strings in this case, if you need them to be handled as
	 * their real data types, you have to use {@link bindParam} or {@link bindValue} instead.
	 * @throws Exception if GDbCommand failed to execute the SQL statement
	 * @return mixed the method execution result
	 */
	private function queryInternal($method,$mode,$params=array())
	{
		$connection = $this->getReadConnection();
		$this->checkTablePrefix();
		if($this->getConnectionPool()->getEnableParamLogging() && ($pars=array_merge($this->_paramLog,$params))!==array())
		{
			$p=array();
			foreach($pars as $name=>$value)
				$p[$name]=$name.'='.var_export($value,true);
			$par='. Bound with '.implode(', ',$p);
		}
		else
			$par='';

		\Yii::trace('Querying SQL: '.$this->getText().$par,'giny.db.Query');

		if($connection->getQueryCachingCount()>0 && $method!==''
		   && $connection->getQueryCachingDuration()>0
		   && $connection->getQueryCacheID()!==false
		   && ($cache=\Yii::app()->getComponent($connection->getQueryCacheID()))!==null)
		{
			$connection->setQueryCachingCount($connection->getQueryCachingCount()-1);
			$cacheKey='yii:dbquery'.$connection->getConnectionString().':'.$connection->getUserName();
			$cacheKey.=':'.$this->getText().':'.serialize(array_merge($this->_paramLog,$params));
			/** @var $cache \ICache */
			if(($result=$cache->get($cacheKey))!==false)
			{
				\Yii::trace('Query result found in cache','giny.db.Query');
				return $result[0];
			}
		}

		try
		{
			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::beginProfile('giny.db.Query.query('.$this->getText().$par.')','giny.db.Query.query');

			$statement = $this->prepare($connection);
			if($params===array())
				$statement->execute();
			else
				$statement->execute($params);

			if($method==='')
				$result=new \CDbDataReader($this);
			else
			{
				$mode=(array)$mode;
				call_user_func_array(array($statement, 'setFetchMode'), $mode);
				$result=$statement->$method();
				$statement->closeCursor();
			}

			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::endProfile('giny.db.Query.query('.$this->getText().$par.')','giny.db.Query.query');

			/** @var $cache \ICache */
			if(isset($cache,$cacheKey))
				$cache->set($cacheKey, array($result), $connection->getQueryCachingDuration(), $connection->getQueryCachingDuration());

			return $result;
		}
		catch(\Exception $e)
		{
			if($this->getConnectionPool()->getEnableProfiling())
				\Yii::endProfile('giny.db.Query.query('.$this->getText().$par.')','giny.db.Query.query');

			$errorInfo=$e instanceof \PDOException ? $e->errorInfo : null;
			$message=$e->getMessage();
			\Yii::log(\Yii::t('yii','Query::{method}() failed: {error}. The SQL statement executed was: {sql}.',
				array('{method}'=>$method, '{error}'=>$message, '{sql}'=>$this->getText().$par)),\CLogger::LEVEL_ERROR,'giny.db.Query');

			if(YII_DEBUG)
				$message.='. The SQL statement executed was: '.$this->getText().$par;

			throw new Exception(\Yii::t('yii','Query failed to execute the SQL statement: {error}',
				array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
		}
	}



	/**
	 * @return Query
	 */
	public function forceMaster()
	{
		$this->_forceMaster = true;
		return $this;
	}

	/**
	 * @return Query
	 */
	public function unsetForceMaster()
	{
		$this->_forceMaster = false;
		return $this;
	}

	/**
	 * @return ConnectionInterface
	 */
	public function getReadConnection()
	{
		return $this->getConnectionPool()->getReadConnection($this->_forceMaster);
	}

	/**
	 * @return ConnectionInterface
	 */
	public function getWriteConnection()
	{
		return $this->getConnectionPool()->getWriteConnection();
	}

}