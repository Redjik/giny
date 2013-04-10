<?php
class GDbPool extends CApplicationComponent implements IDbPool
{
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

    /**
     * @var array Same as master key, but array of slave keys in the pool.
     */
    protected $slaveKeys = array();

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

}
