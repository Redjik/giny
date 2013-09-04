<?php

class GDbCommand extends CComponent
{
    /**
     * @var GConnectionManager
     */
    protected  $_pool;
    protected $_forceMaster;

    /**
     * @var IDbConnection
     */
    protected $_connection;
    protected $_text;
    /**
     * @var PDOStatement
     */
    protected $_statement;
    protected $_bindValues=array();
    protected $_bindParams=array();
    protected $_paramLog=array();

    protected $_fetchMode = array(PDO::FETCH_ASSOC);

    /**
     * Constructor.
     * @param GConnectionManager $connectionPool the database connection
     * @param mixed $query the DB query to be executed. This can be either
     * a string representing a SQL statement, or an array whose name-value pairs
     * will be used to set the corresponding properties of the created command object.
     *
     * For example, you can pass in either <code>'SELECT * FROM tbl_user'</code>
     * or <code>array('select'=>'*', 'from'=>'tbl_user')</code>. They are equivalent
     * in terms of the final query result.
     *
     * When passing the query as an array, the following properties are commonly set:
     * {
     * @param bool $forceMaster
     * @link select}, {@link distinct}, {@link from}, {@link where}, {@link join},
     * {@link group}, {@link having}, {@link order}, {@link limit}, {@link offset} and
     * {@link union}. Please refer to the setter of each of these properties for details
     * about valid property values. This feature has been available since version 1.1.6.
     *
     * Since 1.1.7 it is possible to use a specific mode of data fetching by setting
     * {@link setFetchMode FetchMode}. See {@link http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php}
     * for more details.
     */
    public function __construct(GConnectionManager $connectionPool,$query,$forceMaster=false)
    {
        $this->_pool = $connectionPool;
        $this->_forceMaster = $forceMaster;
        $this->setText($query);
    }

    /**
     * Set the statement to null when serializing.
     * @return array
     */
    public function __sleep()
    {
        $this->_statement=null;
        return array_keys(get_object_vars($this));
    }

    /**
     * Set the default fetch mode for this statement
     * @return GDbCommand
     * @see http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
     * @since 1.1.7
     */
    public function setFetchMode()
    {
        $params=func_get_args();
        $this->_fetchMode = $params;
        return $this;
    }

    /**
     * Cleans up the command and prepares for building a new query.
     * This method is mainly used when a command object is being reused
     * multiple times for building different queries.
     * Calling this method will clean up all internal states of the command object.
     * @return GDbCommand this command instance
     * @since 1.1.6
     */
    public function reset()
    {
        $this->_text=null;
        $this->_statement=null;
        $this->_bindParams=array();
        $this->_bindValues=array();
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
     * @return GDbCommand this command instance
     */
    public function setText($value)
    {
        $this->_text=$value;
        $this->cancel();
        return $this;
    }

    protected function checkTablePrefix()
    {
        if($this->_connection->getTablePrefix()!==null)
            $this->_text=preg_replace('/{{(.*?)}}/',$this->_connection->getTablePrefix().'\1',$this->_text);
    }

    /**
     * @return IDbConnection the connection associated with this command
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * @return PDOStatement the underlying PDOStatement for this command
     * It could be null if the statement is not prepared yet.
     */
    public function getPdoStatement()
    {
        return $this->_statement;
    }

    /**
     * Prepares the SQL statement to be executed.
     * For complex SQL statement that is to be executed multiple times,
     * this may improve performance.
     * For SQL statement with binding parameters, this method is invoked
     * automatically.
     * @throws CDbException if GDbCommand failed to prepare the SQL statement
     */
    public function prepare()
    {
        $this->createStatement();
        $this->bindValuesInternal();
        $this->bindParamsInternal();
    }

    protected function createStatement()
    {
        if($this->_statement==null)
        {
            try
            {
                $this->_statement=$this->_connection->getPdoInstance()->prepare($this->getText());
                $this->_paramLog=array();
            }
            catch(Exception $e)
            {
                Yii::log('Error in preparing SQL: '.$this->getText(),CLogger::LEVEL_ERROR,'system.db.GDbCommand');
                $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
                throw new CDbException(Yii::t('yii','GDbCommand failed to prepare the SQL statement: {error}',
                    array('{error}'=>$e->getMessage())),(int)$e->getCode(),$errorInfo);
            }
        }
    }

    /**
     * Cancels the execution of the SQL statement.
     */
    public function cancel()
    {
        $this->_statement=null;
        $this->_bindParams=array();
        $this->_bindValues=array();
        $this->_paramLog=array();
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
     * @return GDbCommand the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindParam.php
     */
    public function bindParam($name, &$value, $dataType=null, $length=null, $driverOptions=null)
    {
        $this->_bindParams[$name]=array('value'=>&$value,'dataType'=>$dataType,'driverOptions'=>$driverOptions);
        return $this;
    }

    protected function bindParamsInternal()
    {
        foreach ($this->_bindParams as $name=>$value)
        {
            if($value['dataType']===null)
                $this->_statement->bindParam($name,$value,GDbQueryHelper::getPdoType(gettype($value)));
            elseif($value['length']===null)
                $this->_statement->bindParam($name,$value['value'],$value['dataType']);
            elseif($value['driverOptions']===null)
                $this->_statement->bindParam($name,$value['value'],$value['dataType'],$value['length']);
            else
                $this->_statement->bindParam($name,$value['value'],$value['dataType'],$value['length'],$value['driverOptions']);
            $this->_paramLog[$name]=&$value['value'];
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
     * @return GDbCommand the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, $dataType=null)
    {
        $this->_bindValues[$name]=array('value'=>$value,'dataType'=>$dataType);
        return $this;
    }

    protected function bindValuesInternal()
    {
        foreach ($this->_bindValues as $name=>$value)
        {
            if($value['dataType']===null)
                $this->_statement->bindValue($name,$value['value'],GDbQueryHelper::getPdoType(gettype($value)));
            else
                $this->_statement->bindValue($name,$value['value'],$value['dataType']);
            $this->_paramLog[$name]=$value['value'];
        }
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to {@link bindValue} except that it binds multiple values.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values.
     * For example, <code>array(':name'=>'John', ':age'=>25)</code>.
     * @return GDbCommand the current command being executed
     * @since 1.1.5
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
     * @throws CDbException execution failed
     */
    public function execute($params=array())
    {
        $this->setConnectionType(true);

		if ($this->_pool->isOtherDbTransactionStarted()){
			throw new CDbException('You can not save to other DB during transaction');
		}

        $this->checkTablePrefix();

        if($this->_connection->getEnableParamLogging() && ($pars=array_merge($this->_paramLog,$params))!==array())
        {
            $p=array();
            foreach($pars as $name=>$value)
                $p[$name]=$name.'='.var_export($value,true);
            $par='. Bound with ' .implode(', ',$p);
        }
        else
            $par='';
        Yii::trace('Executing SQL: '.$this->getText().$par,'system.db.GDbCommand');
        try
        {
            if($this->_connection->getEnableProfiling())
                Yii::beginProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','system.db.GDbCommand.execute');

            $this->prepare();
            if($params===array())
                $this->_statement->execute();
            else
                $this->_statement->execute($params);
            $n=$this->_statement->rowCount();

            if($this->_connection->getEnableProfiling())
                Yii::endProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','system.db.GDbCommand.execute');

            return $n;
        }
        catch(Exception $e)
        {
            if($this->_connection->getEnableProfiling())
                Yii::endProfile('system.db.GDbCommand.execute('.$this->getText().$par.')','system.db.GDbCommand.execute');

            $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
            $message=$e->getMessage();
            Yii::log(Yii::t('yii','GDbCommand::execute() failed: {error}. The SQL statement executed was: {sql}.',
                array('{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.GDbCommand');

            if(YII_DEBUG)
                $message.='. The SQL statement executed was: '.$this->getText().$par;

            throw new CDbException(Yii::t('yii','GDbCommand failed to execute the SQL statement: {error}',
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
     * @return CDbDataReader the reader object for fetching the query result
     * @throws CException execution failed
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
     * @throws CException execution failed
     */
    public function queryAll($fetchAssociative=true,$params=array())
    {
        return $this->queryInternal('fetchAll',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
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
     * @throws CException execution failed
     */
    public function queryRow($fetchAssociative=true,$params=array())
    {
        return $this->queryInternal('fetch',$fetchAssociative ? $this->_fetchMode : PDO::FETCH_NUM, $params);
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
     * @throws CException execution failed
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
     * @throws CException execution failed
     */
    public function queryColumn($params=array())
    {
        return $this->queryInternal('fetchAll',array(PDO::FETCH_COLUMN, 0),$params);
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
     * @throws CDbException if GDbCommand failed to execute the SQL statement
     * @return mixed the method execution result
     */
    private function queryInternal($method,$mode,$params=array())
    {
        $this->setConnectionType();
        $this->checkTablePrefix();
        if($this->_connection->getEnableParamLogging() && ($pars=array_merge($this->_paramLog,$params))!==array())
        {
            $p=array();
            foreach($pars as $name=>$value)
                $p[$name]=$name.'='.var_export($value,true);
            $par='. Bound with '.implode(', ',$p);
        }
        else
            $par='';

        Yii::trace('Querying SQL: '.$this->getText().$par,'system.db.GDbCommand');

        if($this->_connection->getQueryCachingCount()>0 && $method!==''
           && $this->_connection->getQueryCachingDuration()>0
           && $this->_connection->getQueryCacheID()!==false
           && ($cache=Yii::app()->getComponent($this->_connection->getQueryCacheID()))!==null)
        {
            $this->_connection->setQueryCachingCount($this->_connection->getQueryCachingCount()-1);
            $cacheKey='yii:dbquery'.$this->_connection->getConnectionString().':'.$this->_connection->getUserName();
            $cacheKey.=':'.$this->getText().':'.serialize(array_merge($this->_paramLog,$params));
            /** @var $cache CCache */
            if(($result=$cache->get($cacheKey))!==false)
            {
                Yii::trace('Query result found in cache','system.db.GDbCommand');
                return $result[0];
            }
        }

        try
        {
            if($this->_connection->getEnableProfiling())
                Yii::beginProfile('system.db.GDbCommand.query('.$this->getText().$par.')','system.db.GDbCommand.query');

            $this->prepare();
            if($params===array())
                $this->_statement->execute();
            else
                $this->_statement->execute($params);

            if($method==='')
                $result=new CDbDataReader($this);
            else
            {
                $mode=(array)$mode;
                call_user_func_array(array($this->_statement, 'setFetchMode'), $mode);
                $result=$this->_statement->$method();
                $this->_statement->closeCursor();
            }

            if($this->_connection->getEnableProfiling())
                Yii::endProfile('system.db.GDbCommand.query('.$this->getText().$par.')','system.db.GDbCommand.query');

            if(isset($cache,$cacheKey))
                $cache->set($cacheKey, array($result), $this->_connection->getQueryCachingDuration(), $this->_connection->getQueryCachingDuration());

            return $result;
        }
        catch(Exception $e)
        {
            if($this->_connection->getEnableProfiling())
                Yii::endProfile('system.db.GDbCommand.query('.$this->getText().$par.')','system.db.GDbCommand.query');

            $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
            $message=$e->getMessage();
            Yii::log(Yii::t('yii','GDbCommand::{method}() failed: {error}. The SQL statement executed was: {sql}.',
                array('{method}'=>$method, '{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.GDbCommand');

            if(YII_DEBUG)
                $message.='. The SQL statement executed was: '.$this->getText().$par;

            throw new CDbException(Yii::t('yii','GDbCommand failed to execute the SQL statement: {error}',
                array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
        }
    }



    /**
     * @return GDbCommand
     */
    public function forceMaster()
    {
        $this->_forceMaster = true;
        return $this;
    }

    /**
     * @return GDbCommand
     */
    public function unsetForceMaster()
    {
        $this->_forceMaster = false;
        return $this;
    }

	/**
	 * Sets connection type
	 * @param bool $write true for write | false for read
	 */
	protected function setConnectionType($write = false)
	{
		if ($write){
			$this->_connection = $this->_pool->getWriteConnection();
		}else{
			$this->_connection = $this->_pool->getReadConnection($this->_forceMaster);
		}
	}

}
