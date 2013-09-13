<?php

/**
 * Class GDICComponent
 *
 * this class is still under construction
 * it works as usual component, but it is a Dependency Injection Container (DIC)
 *
 * So for now, we can set up component based on this class in app config in the usual way.
 *
 * 'DICBasedComponent'=>array(
 *     'class'=>'ext.DICBasedComponent'
 * }
 *
 * We add components to DIC in yii-fashion way
 *
 * 'DICBasedComponent'=>array(
 *     'class'=>'ext.DICBasedComponent'
 *     'components'=>array(
 *         'firstComponent'=>array(
 *             'class'=>'ext.firstComponent'
 *         )
 *     )
 * )
 *
 * We have access to these components via __get
 * So if we specify php-doc property in DICBasedComponent class - auto complete will work in IDE
 * ex. Yii::app()->DICBasedComponent->firstComponent
 *
 *
 */
abstract class GDICComponent extends CComponent implements IApplicationComponent
{
    private $_components = array();
    private $_componentConfig = array();


	public function __construct()
	{
		$this->registerCoreComponents();
	}

    /**
     * @var array the behaviors that should be attached to this component.
     * The behaviors will be attached to the component when {@link init} is called.
     * Please refer to {@link CModel::behaviors} on how to specify the value of this property.
     */
    public $behaviors=array();

    private $_initialized=false;

    /**
     * Initializes the application component.
     * This method is required by {@link IApplicationComponent} and is invoked by application.
     * If you override this method, make sure to call the parent implementation
     * so that the application component can be marked as initialized.
     */
    public function init()
    {
        $this->attachBehaviors($this->behaviors);
        $this->_initialized=true;
        $this->registerCoreComponents();
    }

    /**
     * Checks if this application component bas been initialized.
     * @return boolean whether this application component has been initialized (ie, {@link init()} is invoked).
     */
    public function getIsInitialized()
    {
        return $this->_initialized;
    }

    /**
     * Registers the core DIC components.
     * @see setComponents
     */
    protected function registerCoreComponents()
    {
        /*
         * implementation is similar to CApplication::registerCoreComponents
         */
    }

    /**
     * Getter magic method.
     * This method is overridden to support accessing application components
     * like reading module properties.
     * @param string $name application component or property name
     * @return mixed the named property value
     */
    public function __get($name)
    {
        if ($this->hasComponent($name))
            return $this->getComponent($name);
        else
            return parent::__get($name);
    }

    /**
     * Checks if a property value is null.
     * This method overrides the parent implementation by checking
     * if the named application component is loaded.
     * @param string $name the property name or the event name
     * @return boolean whether the property value is null
     */
    public function __isset($name)
    {
        if($this->hasComponent($name))
            return $this->getComponent($name)!==null;
        else
            return parent::__isset($name);
    }

    /**
     * Destroy component
     * @param string $name
     * @return mixed|void
     */
    public function __unset($name)
    {
        if ($this->hasComponent($name))
            unset($this->_components[$name]);
    }

    /**
     * Checks whether the named component exists.
     * @param string $id DIC component ID
     * @return boolean whether the named application component exists (including both loaded and disabled.)
     */
    public function hasComponent($id)
    {
        return isset($this->_components[$id]) || isset($this->_componentConfig[$id]);
    }

    /**
     * Retrieves the named DIC component.
     * Creates component if not initialized, adds custom autoloader if specified.
     * @param string $id DIC component ID (case-sensitive)
     * @param boolean $createIfNull whether to create the component if it doesn't exist yet.
     * @return Object the DIC component instance, null if does not exist.
     * @see hasComponent
     */
    public function getComponent($id, $createIfNull = true)
    {
        if (isset($this->_components[$id]))
            return $this->_components[$id];
        elseif (isset($this->_componentConfig[$id]) && $createIfNull)
        {
            $config = $this->_componentConfig[$id];
            if (!isset($config['enabled']) || $config['enabled'])
            {
                Yii::trace("Loading \"$id\" application component", 'system.CModule');
                unset($config['enabled']);

                $component = $this->createComponent($config);
                $this->_components[$id] = $component;
                return $this->_components[$id];
            }
        }

        return null;
    }


    /**
     * Puts a component under the management of the DIC.
     *
     * @param string $id component ID
     *
     * @param array|object $component DIC component
     * (either configuration array or instance).
     *
     * @param boolean $merge whether to merge the new component configuration
     * with the existing one. Defaults to true, meaning the previously registered
     * component configuration with the same ID will be merged with the new configuration.
     * If set to false, the existing configuration will be replaced completely.
     * This parameter is available since 1.1.13.
     */
    public function setComponent($id, $component, $merge = true)
    {
        if (is_object($component))
        {
            $this->_components[$id] = $component;
        }

        if (isset($this->_components[$id]))
        {
            if (isset($component['class']) && get_class($this->_components[$id]) !== $component['class'])
            {
                unset($this->_components[$id]);
                $this->_componentConfig[$id] = $component; //we should ignore merge here
                return;
            }

            foreach ($component as $key => $value)
            {
                if ($key !== 'class')
                    $this->_components[$id]->$key = $value;
            }
        }
        elseif (isset($this->_componentConfig[$id]['class'], $component['class'])
                && $this->_componentConfig[$id]['class'] !== $component['class']
        )
        {
            $this->_componentConfig[$id] = $component; //we should ignore merge here
            return;
        }

        if (isset($this->_componentConfig[$id]) && $merge)
            $this->_componentConfig[$id] = CMap::mergeArray($this->_componentConfig[$id], $component);
        else
            $this->_componentConfig[$id] = $component;
    }

    /**
     * Returns the DIC components.
     * @param boolean $loadedOnly whether to return the loaded components only. If this is set false,
     * then all components specified in the configuration will be returned, whether they are loaded or not.
     * Loaded components will be returned as objects, while unloaded components as configuration arrays.
     * This parameter has been available since version 1.1.3.
     * @return array the application components (indexed by their IDs)
     */
    public function getComponents($loadedOnly = true)
    {
        if ($loadedOnly)
            return $this->_components;
        else
            return array_merge($this->_componentConfig, $this->_components);
    }



    /**
     * Sets the DIC components.
     *
     * When a configuration is used to specify a component, it should consist of
     * the component's initial property values (name-value pairs). Additionally,
     * a component can be enabled (default) or disabled by specifying the 'enabled' value
     * in the configuration.
     *
     * If a configuration is specified with an ID that is the same as an existing
     * component or configuration, the existing one will be replaced silently.
     *
     * The following is the configuration for two components:
     * <pre>
     * array(
     *     'db'=>array(
     *         'class'=>'CDbConnection',
     *         'connectionString'=>'sqlite:path/to/file.db',
     *     ),
     *     'cache'=>array(
     *         'class'=>'CDbCache',
     *         'connectionID'=>'db',
     *         'enabled'=>!YII_DEBUG,  // enable caching in non-debug mode
     *     ),
     * )
     * </pre>
     *
     * @param array $components application components(id=>component configuration or instances)
     * @param boolean $merge whether to merge the new component configuration with the existing one.
     * Defaults to true, meaning the previously registered component configuration of the same ID
     * will be merged with the new configuration. If false, the existing configuration will be replaced completely.
     */
    public function setComponents(array $components, $merge = true)
    {
        foreach ($components as $id => $component)
            $this->setComponent($id, $component, $merge);
    }

    /**
     * Creates an object and initializes it based on the given configuration.
     *
     * The specified configuration can be either a string or an array.
     * If the former, the string is treated as the object type which can
     * be either the class name or {@link YiiBase::getPathOfAlias class path alias}.
     * If the latter, the 'class' element is treated as the object type,
     * and the rest of the name-value pairs in the array are used to initialize
     * the corresponding object properties.
     *
     * configuration array can have these keys:
     * class -> component's class,
     * arguments -> arguments used in component`s __construct method,
     * methods -> methods which will be fired after component initialization.
     *
     * @param mixed $config the configuration. It can be either a string or an array.
     * @throws CException
     * @internal param array $args
     * @return mixed the created object
     */
    public function createComponent($config)
    {
        if(is_string($config))
        {
            $type=$config;
            $config=array();
        }
        elseif(isset($config['class']))
        {
            $type=$config['class'];
            unset($config['class']);
        }
        else
            throw new CException(Yii::t('yii','Object configuration must be an array containing a "class" element.'));

        if(!class_exists($type,false))
            $type=Yii::import($type,true);

        if(($n=func_num_args())>1)
        {
            $args=func_get_args();
            if($n===2)
                $object=new $type($args[1]);
            elseif($n===3)
                $object=new $type($args[1],$args[2]);
            elseif($n===4)
                $object=new $type($args[1],$args[2],$args[3]);
            else
            {
                unset($args[0]);
                $class=new ReflectionClass($type);
                // Note: ReflectionClass::newInstanceArgs() is available for PHP 5.1.3+
                // $object=$class->newInstanceArgs($args);
                $object=call_user_func_array(array($class,'newInstance'),$args);
            }
        }
        else
            $object=new $type;

        foreach($config as $key=>$value)
            $object->$key=$value;

        if ($object instanceof IApplicationComponent)
            $object->init();

        return $object;
    }

}
