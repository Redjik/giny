<?php

class GDbCommand extends CComponent
{
    /**
     * @var array the parameters (name=>value) to be bound to the current query.
     * @since 1.1.6
     */
    public $params=array();

    /**
     * @var IDbPool
     */
    protected  $_pool;
    protected $_forceMaster;

    /**
     * @var CDbConnection
     */
    protected $_connection;
    protected $_text;
    /**
     * @var PDOStatement
     */
    protected $_statement;
    protected $_paramLog=array();
    protected $_query;
    protected $_fetchMode = array(PDO::FETCH_ASSOC);

    /**
     * Constructor.
     * @param IDbPool $connectionPool the database connection
     * @param mixed $query the DB query to be executed. This can be either
     * a string representing a SQL statement, or an array whose name-value pairs
     * will be used to set the corresponding properties of the created command object.
     *
     * For example, you can pass in either <code>'SELECT * FROM tbl_user'</code>
     * or <code>array('select'=>'*', 'from'=>'tbl_user')</code>. They are equivalent
     * in terms of the final query result.
     *
     * When passing the query as an array, the following properties are commonly set:
     * {@link select}, {@link distinct}, {@link from}, {@link where}, {@link join},
     * {@link group}, {@link having}, {@link order}, {@link limit}, {@link offset} and
     * {@link union}. Please refer to the setter of each of these properties for details
     * about valid property values. This feature has been available since version 1.1.6.
     *
     * Since 1.1.7 it is possible to use a specific mode of data fetching by setting
     * {@link setFetchMode FetchMode}. See {@link http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php}
     * for more details.
     */
    public function __construct(IDbPool $connectionPool,$query=null)
    {
        $this->_pool = $connectionPool;
        if(is_array($query))
        {
            foreach($query as $name=>$value)
                $this->$name=$value;
        }
        else
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
     * @param mixed $mode fetch mode
     * @return CDbCommand
     * @see http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php
     * @since 1.1.7
     */
    public function setFetchMode($mode)
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
     * @return CDbCommand this command instance
     * @since 1.1.6
     */
    public function reset()
    {
        $this->_text=null;
        $this->_query=null;
        $this->_statement=null;
        $this->_paramLog=array();
        $this->params=array();
        return $this;
    }

    /**
     * @return string the SQL statement to be executed
     */
    public function getText()
    {
        if($this->_text=='' && !empty($this->_query))
            $this->setText($this->buildQuery($this->_query));
        return $this->_text;
    }

    /**
     * Specifies the SQL statement to be executed.
     * Any previous execution will be terminated or cancel.
     * @param string $value the SQL statement to be executed
     * @param bool $forInner for inner queries
     * @return CDbCommand this command instance
     */
    public function setText($value,$forInner = false)
    {
        if (!$forInner)
            $this->_connection = $this->_pool->getConnectionFromSql($value,$this->_forceMaster);

        if($this->_connection->tablePrefix!==null && $value!='')
            $this->_text=preg_replace('/{{(.*?)}}/',$this->_connection->tablePrefix.'\1',$value);
        else
            $this->_text=$value;
        $this->cancel();
        return $this;
    }

    /**
     * @return CDbConnection the connection associated with this command
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
     * @throws CDbException if CDbCommand failed to prepare the SQL statement
     */
    public function prepare()
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
                Yii::log('Error in preparing SQL: '.$this->getText(),CLogger::LEVEL_ERROR,'system.db.CDbCommand');
                $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
                throw new CDbException(Yii::t('yii','CDbCommand failed to prepare the SQL statement: {error}',
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
     * @return CDbCommand the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindParam.php
     */
    public function bindParam($name, &$value, $dataType=null, $length=null, $driverOptions=null)
    {
        $this->prepare();
        if($dataType===null)
            $this->_statement->bindParam($name,$value,GDbQueryHelper::getPdoType(gettype($value)));
        elseif($length===null)
            $this->_statement->bindParam($name,$value,$dataType);
        elseif($driverOptions===null)
            $this->_statement->bindParam($name,$value,$dataType,$length);
        else
            $this->_statement->bindParam($name,$value,$dataType,$length,$driverOptions);
        $this->_paramLog[$name]=&$value;
        return $this;
    }

    /**
     * Binds a value to a parameter.
     * @param mixed $name Parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form :name. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @return CDbCommand the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, $dataType=null)
    {
        $this->prepare();
        if($dataType===null)
            $this->_statement->bindValue($name,$value,GDbQueryHelper::getPdoType(gettype($value)));
        else
            $this->_statement->bindValue($name,$value,$dataType);
        $this->_paramLog[$name]=$value;
        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to {@link bindValue} except that it binds multiple values.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values.
     * For example, <code>array(':name'=>'John', ':age'=>25)</code>.
     * @return CDbCommand the current command being executed
     * @since 1.1.5
     */
    public function bindValues($values)
    {
        $this->prepare();
        foreach($values as $name=>$value)
        {
            $this->_statement->bindValue($name,$value,GDbQueryHelper::getPdoType(gettype($value)));
            $this->_paramLog[$name]=$value;
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
        $this->getWriteConnection();

        if($this->_connection->enableParamLogging && ($pars=array_merge($this->_paramLog,$params))!==array())
        {
            $p=array();
            foreach($pars as $name=>$value)
                $p[$name]=$name.'='.var_export($value,true);
            $par='. Bound with ' .implode(', ',$p);
        }
        else
            $par='';
        Yii::trace('Executing SQL: '.$this->getText().$par,'system.db.CDbCommand');
        try
        {
            if($this->_connection->enableProfiling)
                Yii::beginProfile('system.db.CDbCommand.execute('.$this->getText().$par.')','system.db.CDbCommand.execute');

            $this->prepare();
            if($params===array())
                $this->_statement->execute();
            else
                $this->_statement->execute($params);
            $n=$this->_statement->rowCount();

            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.execute('.$this->getText().$par.')','system.db.CDbCommand.execute');

            return $n;
        }
        catch(Exception $e)
        {
            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.execute('.$this->getText().$par.')','system.db.CDbCommand.execute');

            $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
            $message=$e->getMessage();
            Yii::log(Yii::t('yii','CDbCommand::execute() failed: {error}. The SQL statement executed was: {sql}.',
                array('{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.CDbCommand');

            if(YII_DEBUG)
                $message.='. The SQL statement executed was: '.$this->getText().$par;

            throw new CDbException(Yii::t('yii','CDbCommand failed to execute the SQL statement: {error}',
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
     * @throws CDbException if CDbCommand failed to execute the SQL statement
     * @return mixed the method execution result
     */
    private function queryInternal($method,$mode,$params=array())
    {
        $this->getReadConnection();

        $params=array_merge($this->params,$params);

        if($this->_connection->enableParamLogging && ($pars=array_merge($this->_paramLog,$params))!==array())
        {
            $p=array();
            foreach($pars as $name=>$value)
                $p[$name]=$name.'='.var_export($value,true);
            $par='. Bound with '.implode(', ',$p);
        }
        else
            $par='';

        Yii::trace('Querying SQL: '.$this->getText().$par,'system.db.CDbCommand');

        if($this->_connection->queryCachingCount>0 && $method!==''
           && $this->_connection->queryCachingDuration>0
           && $this->_connection->queryCacheID!==false
           && ($cache=Yii::app()->getComponent($this->_connection->queryCacheID))!==null)
        {
            $this->_connection->queryCachingCount--;
            $cacheKey='yii:dbquery'.$this->_connection->connectionString.':'.$this->_connection->username;
            $cacheKey.=':'.$this->getText().':'.serialize(array_merge($this->_paramLog,$params));
            /** @var $cache CCache */
            if(($result=$cache->get($cacheKey))!==false)
            {
                Yii::trace('Query result found in cache','system.db.CDbCommand');
                return $result[0];
            }
        }

        try
        {
            if($this->_connection->enableProfiling)
                Yii::beginProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

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

            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

            if(isset($cache,$cacheKey))
                $cache->set($cacheKey, array($result), $this->_connection->queryCachingDuration, $this->_connection->queryCachingDependency);

            return $result;
        }
        catch(Exception $e)
        {
            if($this->_connection->enableProfiling)
                Yii::endProfile('system.db.CDbCommand.query('.$this->getText().$par.')','system.db.CDbCommand.query');

            $errorInfo=$e instanceof PDOException ? $e->errorInfo : null;
            $message=$e->getMessage();
            Yii::log(Yii::t('yii','CDbCommand::{method}() failed: {error}. The SQL statement executed was: {sql}.',
                array('{method}'=>$method, '{error}'=>$message, '{sql}'=>$this->getText().$par)),CLogger::LEVEL_ERROR,'system.db.CDbCommand');

            if(YII_DEBUG)
                $message.='. The SQL statement executed was: '.$this->getText().$par;

            throw new CDbException(Yii::t('yii','CDbCommand failed to execute the SQL statement: {error}',
                array('{error}'=>$message)),(int)$e->getCode(),$errorInfo);
        }
    }



    /**
     * @return CDbCommand
     */
    public function forceMaster()
    {
        $this->_forceMaster = true;
        return $this;
    }

    /**
     * @return CDbCommand
     */
    public function unsetForceMaster()
    {
        $this->_forceMaster = null;
        return $this;
    }

    protected function getReadConnection()
    {
        $this->_connection = $this->_pool->getReadConnection($this->_forceMaster);
    }

    protected function getWriteConnection()
    {
        $this->getWriteConnection();
    }
}
