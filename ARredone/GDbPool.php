<?php
class GDbPool extends CApplicationComponent implements IDbPool
{
    public $connections;

    public $defaultPoolMode;

    private $pool = array();

    private $masterKey;

    private $slaveKeys = array();

    public function init()
    {
        $this->analyzeConnections();
    }

    protected function analyzeConnections()
    {
        foreach ($this->connections as $key=>$connection)
        {

        }

    }


    public function getReadConnection($forceMaster = null)
    {

    }

    public function getWriteConnection()
    {

    }

    public function getConnectionFromSql($sql,$forceMaster = null)
    {
        $sql=substr(ltrim($sql),0,10);
        $sql=str_ireplace(array('SELECT','SHOW','DESCRIBE','PRAGMA'),'^O^',$sql);//^O^,magic smile
        return strpos($sql,'^O^')===0?$this->getReadConnection($forceMaster):$this->getWriteConnection();
    }

    /**
     * Creates a command for execution.
     * @param mixed $query the DB query to be executed. This can be either a string representing a SQL statement,
     * or an array representing different fragments of a SQL statement. Please refer to {@link CDbCommand::__construct}
     * for more details about how to pass an array as the query. If this parameter is not given,
     * you will have to call query builder methods of {@link CDbCommand} to build the DB query.
     * @return CDbCommand the DB command
     */
    public function createCommand($query=null)
    {
        return new CDbCommand($this,$query);
    }

    public function test()
    {

    }




}
