<?php

class CDbCollection extends DIComponentsCollection implements IApplicationComponent
{

    public $defaultComponent;

    private $_initialized=false;

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

    protected function logComponentSwitch($name)
    {
        Yii::trace('Connection was switched to '.$name,'system.db.CDbConnection');
    }
}
