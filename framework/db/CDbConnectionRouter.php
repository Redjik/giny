<?php

abstract class CDbConnectionRouter
{
    /**
     * @var CDbConnection
     */
    protected $currentConnection;

    /**
     * Tries to call connection object method
     * @param $name
     * @param $parameters
     * @return mixed
     */
    public function __call($name,$parameters)
    {
        $this->checkCurrentConnection();
        return call_user_func_array(array($this->currentConnection,$name),$parameters);
    }

    /**
     * Tries to get connection property
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $this->checkCurrentConnection();
        return $this->currentConnection->$name;
    }


    /**
     * Tries to set connection property
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name,$value)
    {
        $this->checkCurrentConnection();
        return $this->currentConnection->$name = $value;
    }

    /**
     * Checks if property isset in connection object
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        $this->checkCurrentConnection();
        return isset($this->currentConnection->$name);
    }

    /**
     * Unset connection object property
     * @param $name
     */
    public function __unset($name)
    {
        $this->checkCurrentConnection();
        unset($this->currentConnection->$name);
    }

    /**
     * Checks if current connection is specified
     * @throws CDbException
     */
    protected function checkCurrentConnection()
    {
        if (!$this->currentConnection || !$this->currentConnection instanceof CDbConnection)
            throw new CDbException('Connection was not established, you should specify currentConnection property in router');
    }
}
