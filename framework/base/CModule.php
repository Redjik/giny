<?php
/**
 * CModule class file.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @link http://www.yiiframework.com/
 * @copyright 2008-2013 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

/**
 * CModule is the base class for module and application classes.
 *
 * CModule mainly manages application components and sub-modules.
 *
 * @property string $id The module ID.
 * @property string $basePath The root directory of the module. Defaults to the directory containing the module class.
 * @property CAttributeCollection $params The list of user-defined parameters.
 * @property string $modulePath The directory that contains the application modules. Defaults to the 'modules' subdirectory of {@link basePath}.
 * @property CModule $parentModule The parent module. Null if this module does not have a parent.
 * @property array $modules The configuration of the currently installed modules (module ID => configuration).
 * @property array $components The application components (indexed by their IDs).
 * @property array $import List of aliases to be imported.
 * @property array $aliases List of aliases to be defined. The array keys are root aliases,
 * while the array values are paths or aliases corresponding to the root aliases.
 * For example,
 * <pre>
 * array(
 *    'models'=>'application.models',              // an existing alias
 *    'extensions'=>'application.extensions',      // an existing alias
 *    'backend'=>dirname(__FILE__).'/../backend',  // a directory
 * )
 * </pre>.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @package system.base
 */
abstract class CModule extends CComponentCollection
{
	/**
	 * @var array the IDs of the application components that should be preloaded.
	 */
	public $preload=array();
	/**
	 * @var array the behaviors that should be attached to the module.
	 * The behaviors will be attached to the module when {@link init} is called.
	 * Please refer to {@link CModel::behaviors} on how to specify the value of this property.
	 */
	public $behaviors=array();

	private $_id;
	private $_parentModule;
	private $_basePath;
	private $_modulePath;
	private $_params;
	private $_modules=array();
	private $_moduleConfig=array();


    /**
	 * Constructor.
	 * @param string $id the ID of this module
	 * @param CModule $parent the parent module (if any)
	 * @param mixed $config the module configuration. It can be either an array or
	 * the path of a PHP file returning the configuration array.
	 */
	public function __construct($id,$parent,$config=null)
	{
		$this->_id=$id;
		$this->_parentModule=$parent;

		// set basePath at early as possible to avoid trouble
		if(is_string($config))
			$config=require($config);
		if(isset($config['basePath']))
		{
			$this->setBasePath($config['basePath']);
			unset($config['basePath']);
		}
		Yii::setPathOfAlias($id,$this->getBasePath());

		$this->preinit();

		$this->configure($config);
		$this->attachBehaviors($this->behaviors);
		$this->preloadComponents();

		$this->init();
	}


	/**
	 * Returns the module ID.
	 * @return string the module ID.
	 */
	public function getId()
	{
		return $this->_id;
	}

	/**
	 * Sets the module ID.
	 * @param string $id the module ID
	 */
	public function setId($id)
	{
		$this->_id=$id;
	}

	/**
	 * Returns the root directory of the module.
	 * @return string the root directory of the module. Defaults to the directory containing the module class.
	 */
	public function getBasePath()
	{
		if($this->_basePath===null)
		{
			$class=new ReflectionClass(get_class($this));
			$this->_basePath=dirname($class->getFileName());
		}
		return $this->_basePath;
	}

	/**
	 * Sets the root directory of the module.
	 * This method can only be invoked at the beginning of the constructor.
	 * @param string $path the root directory of the module.
	 * @throws CException if the directory does not exist.
	 */
	public function setBasePath($path)
	{
		if(($this->_basePath=realpath($path))===false || !is_dir($this->_basePath))
			throw new CException(Yii::t('yii','Base path "{path}" is not a valid directory.',
				array('{path}'=>$path)));
	}

	/**
	 * Returns user-defined parameters.
	 * @return CAttributeCollection the list of user-defined parameters
	 */
	public function getParams()
	{
		if($this->_params!==null)
			return $this->_params;
		else
		{
			$this->_params=new CAttributeCollection;
			$this->_params->caseSensitive=true;
			return $this->_params;
		}
	}

	/**
	 * Sets user-defined parameters.
	 * @param array $value user-defined parameters. This should be in name-value pairs.
	 */
	public function setParams($value)
	{
		$params=$this->getParams();
		foreach($value as $k=>$v)
			$params->add($k,$v);
	}

	/**
	 * Returns the directory that contains the application modules.
	 * @return string the directory that contains the application modules. Defaults to the 'modules' subdirectory of {@link basePath}.
	 */
	public function getModulePath()
	{
		if($this->_modulePath!==null)
			return $this->_modulePath;
		else
			return $this->_modulePath=$this->getBasePath().DIRECTORY_SEPARATOR.'modules';
	}

	/**
	 * Sets the directory that contains the application modules.
	 * @param string $value the directory that contains the application modules.
	 * @throws CException if the directory is invalid
	 */
	public function setModulePath($value)
	{
		if(($this->_modulePath=realpath($value))===false || !is_dir($this->_modulePath))
			throw new CException(Yii::t('yii','The module path "{path}" is not a valid directory.',
				array('{path}'=>$value)));
	}

	/**
	 * Sets the aliases that are used in the module.
	 * @param array $aliases list of aliases to be imported
	 */
	public function setImport($aliases)
	{
		foreach($aliases as $alias)
			Yii::import($alias);
	}

	/**
	 * Defines the root aliases.
	 * @param array $mappings list of aliases to be defined. The array keys are root aliases,
	 * while the array values are paths or aliases corresponding to the root aliases.
	 * For example,
	 * <pre>
	 * array(
	 *    'models'=>'application.models',              // an existing alias
	 *    'extensions'=>'application.extensions',      // an existing alias
	 *    'backend'=>dirname(__FILE__).'/../backend',  // a directory
	 * )
	 * </pre>
	 */
	public function setAliases($mappings)
	{
		foreach($mappings as $name=>$alias)
		{
			if(($path=Yii::getPathOfAlias($alias))!==false)
				Yii::setPathOfAlias($name,$path);
			else
				Yii::setPathOfAlias($name,$alias);
		}
	}

	/**
	 * Returns the parent module.
	 * @return CModule the parent module. Null if this module does not have a parent.
	 */
	public function getParentModule()
	{
		return $this->_parentModule;
	}

	/**
	 * Retrieves the named application module.
	 * The module has to be declared in {@link modules}. A new instance will be created
	 * when calling this method with the given ID for the first time.
	 * @param string $id application module ID (case-sensitive)
	 * @return CModule the module instance, null if the module is disabled or does not exist.
	 */
	public function getModule($id)
	{
		if(isset($this->_modules[$id]) || array_key_exists($id,$this->_modules))
			return $this->_modules[$id];
		elseif(isset($this->_moduleConfig[$id]))
		{
			$config=$this->_moduleConfig[$id];
			if(!isset($config['enabled']) || $config['enabled'])
			{
				Yii::trace("Loading \"$id\" module",'system.base.CModule');
				$class=$config['class'];
				unset($config['class'], $config['enabled']);
				if($this===Yii::app())
					$module=Yii::createComponent($class,$id,null,$config);
				else
					$module=Yii::createComponent($class,$this->getId().'/'.$id,$this,$config);
				return $this->_modules[$id]=$module;
			}
		}
	}

	/**
	 * Returns a value indicating whether the specified module is installed.
	 * @param string $id the module ID
	 * @return boolean whether the specified module is installed.
	 * @since 1.1.2
	 */
	public function hasModule($id)
	{
		return isset($this->_moduleConfig[$id]) || isset($this->_modules[$id]);
	}

	/**
	 * Returns the configuration of the currently installed modules.
	 * @return array the configuration of the currently installed modules (module ID => configuration)
	 */
	public function getModules()
	{
		return $this->_moduleConfig;
	}

	/**
	 * Configures the sub-modules of this module.
	 *
	 * Call this method to declare sub-modules and configure them with their initial property values.
	 * The parameter should be an array of module configurations. Each array element represents a single module,
	 * which can be either a string representing the module ID or an ID-configuration pair representing
	 * a module with the specified ID and the initial property values.
	 *
	 * For example, the following array declares two modules:
	 * <pre>
	 * array(
	 *     'admin',                // a single module ID
	 *     'payment'=>array(       // ID-configuration pair
	 *         'server'=>'paymentserver.com',
	 *     ),
	 * )
	 * </pre>
	 *
	 * By default, the module class is determined using the expression <code>ucfirst($moduleID).'Module'</code>.
	 * And the class file is located under <code>modules/$moduleID</code>.
	 * You may override this default by explicitly specifying the 'class' option in the configuration.
	 *
	 * You may also enable or disable a module by specifying the 'enabled' option in the configuration.
	 *
	 * @param array $modules module configurations.
	 */
	public function setModules($modules)
	{
		foreach($modules as $id=>$module)
		{
			if(is_int($id))
			{
				$id=$module;
				$module=array();
			}
			if(!isset($module['class']))
			{
				Yii::setPathOfAlias($id,$this->getModulePath().DIRECTORY_SEPARATOR.$id);
				$module['class']=$id.'.'.ucfirst($id).'Module';
			}

			if(isset($this->_moduleConfig[$id]))
				$this->_moduleConfig[$id]=CMap::mergeArray($this->_moduleConfig[$id],$module);
			else
				$this->_moduleConfig[$id]=$module;
		}
	}

    /**
     * Configures the module with the specified configuration.
     * @param array $config the configuration array
     */
    public function configure($config)
    {
        if (is_array($config))
        {
            foreach ($config as $key => $value)
                $this->$key = $value;
        }
    }

	/**
	 * Preinitializes the module.
	 * This method is called at the beginning of the module constructor.
	 * You may override this method to do some customized preinitialization work.
	 * Note that at this moment, the module is not configured yet.
	 * @see init
	 */
	protected function preinit()
	{
	}

	/**
	 * Initializes the module.
	 * This method is called at the end of the module constructor.
	 * Note that at this moment, the module has been configured, the behaviors
	 * have been attached and the application components have been registered.
	 * @see preinit
	 */
	protected function init()
	{
	}
}
