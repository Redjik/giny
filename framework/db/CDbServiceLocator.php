<?php

class CDbServiceLocator extends CApplicationComponent
{

    private $_models;

    /**
     * @var CDbConnectionRouter
     */
    public $service_class;

    public function __call($name,$parameters)
    {
        $service = $this->locateFor('app');
        return call_user_func_array(array($service,$name),$parameters);
    }

    /**
     * Tries to get component property
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $service = $this->locateFor('app');
        return $service->$name;
    }


    /**
     * Tries to set component property
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name,$value)
    {
        $service = $this->locateFor('app');
        return $service->$name = $value;
    }

    public function __isset($name)
    {
        $service = $this->locateFor('app');
        return isset($service->$name);
    }

    public function __unset($name)
    {
        $service = $this->locateFor('app');
        unset($service->$name);
    }

    public function locateFor($class_name)
    {
        if (!isset($this->_models[$class_name]))
        {
            $this->_models[$class_name] = Yii::createComponent($this->service_class);
            $this->_models[$class_name]->init();
        }

        return $this->_models[$class_name];

    }

}
