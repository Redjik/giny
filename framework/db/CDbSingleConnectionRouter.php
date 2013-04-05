<?php

class CDbSingleConnectionRouter extends CDbConnectionRouter
{
    protected static $db;

    /**
     * @var array
     */
    public $connection;

    public function init()
    {
        if (self::$db === null)
            self::$db = Yii::createComponent($this->connection);

        $this->currentConnection = self::$db;
    }

}
