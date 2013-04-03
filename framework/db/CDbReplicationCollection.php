<?php

class CDbReplicationCollection extends CComponentCollection implements IApplicationComponent
{

    public $defaultConnection;

    private $_currentConnection;

    private $_initialized=false;



    public function __call($name,$parameters)
    {
        $component = $this->getCurrentConnectionComponent();

        if (method_exists($component,$name))
            return call_user_func_array(array($component,$name),$parameters);

        return parent::__call($name,$parameters);
    }

    public function __get($name)
    {
        $component = $this->getCurrentConnectionComponent();

        if (isset($component->$name) || (is_object($component) && property_exists($component,$name)))
            return $component->$name;
        else
            return parent::__get($name);
    }

    public function __set($name,$value)
    {
        $component = $this->getCurrentConnectionComponent();

        if (isset($component->$name)|| (is_object($component) && property_exists($component,$name)))
            $component->$name = $value;
        else
            parent::__set($name,$value);
    }

    public function setConnection($name)
    {
        if ($this->_currentConnection === $name)
            return;

        if ($this->hasComponent($name))
            $this->_currentConnection = $name;
        else
            throw new CException(Yii::t('yii','DI collection "{class}" has no "{component}" in its storage',
        array('{class}'=>get_class($this), '{component}'=>$name)));

        Yii::trace('Connection was switched to '.$name,'system.db.CDbConnection');
    }

    public function getConnectionName()
    {
        return $this->_currentConnection;
    }

    protected function getCurrentConnectionComponent()
    {
        if ($this->_currentConnection!==null)
            $component = $this->getComponent($this->_currentConnection);
        else
            $component = $this->getComponent($this->defaultConnection);

        return $component;
    }


    /**
     * Initializes the application component.
     * This method is required by {@link IApplicationComponent} and is invoked by application.
     * If you override this method, make sure to call the parent implementation
     * so that the application component can be marked as initialized.
     */
    public function init()
    {
        $this->_initialized=true;
    }

    /**
     * Checks if this application component bas been initialized.
     * @return boolean whether this application component has been initialized (ie, {@link init()} is invoked).
     */
    public function getIsInitialized()
    {
        return $this->_initialized;
    }
}
