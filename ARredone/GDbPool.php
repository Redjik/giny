<?php
class GDbPool extends CApplicationComponent implements IDbConnectionAccessObject
{

    public $schemaCachingDuration;

    public $schemaCachingExclude;

    public $schemaCacheID;

    public $schemaConnectionName;

    public $connections;

    /**
     * @var IDbConnection[]
     */
    protected $poolConnections = array();

    /**
     * @var array IDbPool[]
     */
    protected $poolPools = array();

    /**
     * @var string $poolConnections[$masterKey]=>IDbConnection
     */
    protected $masterKey;

    protected $activeConnections = array();

    /**
     * @var array Same as master key, but array of slave keys in the pool.
     */
    protected $slaveKeys = array();

    private $_schema;

    public function init()
    {
        $this->analyzeConnections();

        $this->analyzePools();
    }

    /**
     * Sets master/slave keys for connections
     */
    protected function analyzeConnections()
    {
        if ($this->connections === null)
            return false;

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
                if ($connection['type'] === CDbConnection::TYPE_MASTER)
                {
                    if ($this->masterKey !== null)
                        throw new CDbException('Pool can not have two master connections');
                    else
                        $this->masterKey = $key;
                }
            }

            if (!isset($connection['type']) || (isset($connection['type']) && $connection['type']===CDbConnection::TYPE_SLAVE))
            {
                $this->slaveKeys[]=$key;
            }
        }

        if ($this->masterKey === null)
            throw new CDbException('You should have at least one master connection');

        return true;
    }

    protected function analyzePools()
    {
        foreach ($this->poolPools as $key=>$pool)
        {

        }
    }


    /**
     * @param bool $forceMaster
     * @return IDbConnection
     */
    public function getReadConnection($forceMaster = false)
    {
        if ($forceMaster)
            return $this->getWriteConnection();

        $connections = array_intersect($this->slaveKeys,$this->activeConnections);
        if (!empty($connections))
        {
            return $this->poolConnections[$connections[0]];
        }
        else
        {
            shuffle($this->slaveKeys);
            return $this->createConnection($this->slaveKeys[0]);
        }

    }

    /**
     * @return IDbConnection
     */
    public function getWriteConnection()
    {
        if (in_array($this->masterKey,$this->activeConnections))
            return $this->poolConnections[$this->masterKey];
        else
            return $this->createConnection($this->masterKey);
    }

    /**
     * @param string $name
     * @throws CDbException
     * @return IDbConnection
     */
    protected function createConnection($name)
    {
        $connection = $this->connections[$name];
        if (!isset($connection['class']))
            $connection['class'] = 'CDbConnection';

        $connection = Yii::createComponent($connection);
        $connection->init();

        if ($connection instanceof IDbConnection)
        {
            $this->activeConnections[]=$name;
            $this->poolConnections[$name] = $connection;
            return $connection;
        }

        throw new CDbException('Connection class doesn\'t inherit IDbConnection interface');
    }

    /**
     * @return IDbConnection
     */
    public function getSchemaConnection()
    {
        if ($this->schemaConnectionName!==null)
        {
            if (in_array($this->schemaConnectionName,$this->activeConnections))
                return $this->poolConnections[$this->schemaConnectionName];
            else
                return $this->createConnection($this->schemaConnectionName);
        }

        return $this->getReadConnection();
    }

    /**
     * Returns pool with connections.
     * Overload getDb in AR, to get particular pool.
     * @param string $name Name of the pool
     * @return GDbPool
     */
    public function getPool($name)
    {

    }

    /**
     * Creates a command for execution.
     * @param mixed $query the DB query to be executed. This can be a string representing a SQL statement,
     * @param bool $forceMaster use master for read commands
     * @return GDbCommand the DB command
     */
    public function createCommand($query,$forceMaster = false)
    {
        return new GDbCommand($this,$query, $forceMaster);
    }

    public function createQueryBuilder()
    {

    }


    /**
     * Returns the database schema for the current connection
     * @throws CDbException if CDbConnection does not support reading schema for specified database driver
     * @return CDbSchema the database schema for the current connection
     */
    public function getSchema()
    {
        if($this->_schema === null)
        {
            $config = array();

            if (!empty($this->schemaCachingDuration))
                $config['schemaCachingDuration'] = $this->schemaCachingDuration;

            if (!empty($this->schemaCachingExclude))
                $config['schemaCachingExclude'] = $this->schemaCachingExclude;

            if (!empty($this->schemaCacheID))
                $config['schemaCacheID'] = $this->schemaCacheID;

            $this->_schema = CDbSchema::factory($this,$config);

        }

        return $this->_schema;
    }

}
