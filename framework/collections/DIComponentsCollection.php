<?php

abstract class DIComponentsCollection extends CDIContainer
{
    /**
     * @var string name of the default component to process the request
     */
    public $defaultComponent;

    /**
     * @var string name of the current component used for DI
     */
    private $_currentComponent;

    /**
     * Tries to init component action
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    public function __call($name,$parameters)
    {
        $component = $this->getCurrentComponentObject();

        if (method_exists($component,$name))
            return call_user_func_array(array($component,$name),$parameters);

        return parent::__call($name,$parameters);
    }

    /**
     * Tries to get component property
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        $component = $this->getCurrentComponentObject();

        if (isset($component->$name) || (is_object($component) && property_exists($component,$name)))
            return $component->$name;
        else
            return parent::__get($name);
    }


    /**
     * Tries to set component property
     * @param string $name
     * @param mixed $value
     * @return mixed
     */
    public function __set($name,$value)
    {
        $component = $this->getCurrentComponentObject();

        if (isset($component->$name)|| (is_object($component) && property_exists($component,$name)))
            return $component->$name = $value;
        else
            return parent::__set($name,$value);
    }

    /**
     * Switch current component to another
     * @param $name
     * @return $this
     * @throws CException
     */
    public function setCurrentComponent($name)
    {
        if ($this->_currentComponent === $name)
            return $this;

        if ($this->hasComponent($name))
            $this->_currentComponent = $name;
        else
            throw new CException(Yii::t('yii','DI collection "{class}" has no "{component}" in its storage',
                array('{class}'=>get_class($this), '{component}'=>$name)));

        $this->logComponentSwitch($name);

        return $this;
    }

    /**
     * @return string the name of the current component used by DI
     */
    public function getCurrentComponent()
    {
        return $this->_currentComponent;
    }

    /**
     * Returns object of the currently used component
     * or default one
     * or first one
     * @return CApplicationComponent
     */
    protected function getCurrentComponentObject()
    {
        if ($this->_currentComponent!==null)
            return $this->getComponent($this->_currentComponent);

        if ($this->_currentComponent===null && $this->defaultComponent!==null)
            $this->_currentComponent = $this->defaultComponent;
        else
            $this->_currentComponent = $this->getFirstComponentName();

        $this->logComponentSwitch($this->_currentComponent);

        return $this->getComponent($this->_currentComponent);
    }

    /**
     * used when there is no current component yet
     * and default component was not specified
     * @return mixed
     */
    protected function getFirstComponentName()
    {
        return key($this->getComponents(false));
    }


    /**
     * @param $name string name of the component onto which switch was made
     */
    protected function logComponentSwitch($name)
    {
    }
}
