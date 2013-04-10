<?php
class GDbPool extends CApplicationComponent implements IDbPool
{
    /**
     * @var integer number of seconds that table metadata can remain valid in cache.
     * Use 0 or negative value to indicate not caching schema.
     * If greater than 0 and the primary cache is enabled, the table metadata will be cached.
     * @see schemaCachingExclude
     */
    public $schemaCachingDuration=0;
    /**
     * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
     * @see schemaCachingDuration
     */
    public $schemaCachingExclude=array();
    /**
     * @var string the ID of the cache application component that is used to cache the table metadata.
     * Defaults to 'cache' which refers to the primary cache application component.
     * Set this property to false if you want to disable caching table metadata.
     */
    public $schemaCacheID='cache';

    public $schemaConnectionName;

    public $connections;

    public $pools;

    /**
     * @var CDbConnection[]
     */
    protected $poolConnections = array();

    /**
     * @var array IDbPool[]
     */
    protected $poolPools = array();

    /**
     * @var string $pool[$masterKey]=>CDbConnection
     */
    protected $masterKey;

    protected $activeConnections;

    /**
     * @var array Same as master key, but array of slave keys in the pool.
     */
    protected $slaveKeys = array();

    private $_schema;

    public function init()
    {
        $this->analyzeConnections();

        if (empty($this->connections))
            $this->analyzePools();
    }

    /**
     * Sets master/slave keys for connections
     */
    protected function analyzeConnections()
    {
        foreach ($this->connections as $key=>$connection)
        {

        }

    }

    protected function analyzePools()
    {
        foreach ($this->pools as $key=>$pool)
        {

        }
    }


    public function getReadConnection($forceMaster = false)
    {

    }

    public function getWriteConnection()
    {

    }

    /**
     * @return CDbConnection
     */
    public function getSchemaConnection()
    {

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

    public function getPool_exapmle()
    {
        // for instance we have separate Replication pools
        $users = $this->createCommand('SELECT * FROM users')->queryAll();
        // but products are working with another replication ... so we get another pool
        $products = $this->getPool('products')->createCommand('SELECT * FROM products')->queryAll();
    }

    /**
     * Creates a command for execution.
     * @param mixed $query the DB query to be executed. This can be a string representing a SQL statement,
     * @param bool $forceMaster use master for read commands
     * @return CDbCommand the DB command
     */
    public function createCommand($query,$forceMaster = false)
    {
        return new CDbCommand($this,$query, $forceMaster);
    }

    public function createQueryBuilder()
    {

    }

    /**
     * Returns the database schema for the current connection
     * @throws CDbException if CDbConnection does not support reading schema for specified database driver
     * @return GPoolSchema the database schema for the current connection
     */
    public function getSchema()
    {
        if($this->_schema!==null)
            return $this->_schema;
        else
        {
            GPoolSchema::factory($this->getSchemaConnection());
        }
    }

}
