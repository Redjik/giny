<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\ActiveRecord;

use Giny\ConnectionManager\ConnectionManager;

class ActiveRecord
{

	protected $_attributes;

	/**
	 * @var \CDbTableSchema
	 */
	protected $_tableSchema;

	public function __construct($scenario='insert')
	{
		$this->setTableSchema($this->getConnectionPoolName());
	}

	public function setTableSchema($poolName = null)
	{
		/** @var $container ConnectionManager */
		$this->_tableSchema = $container->getTableSchema($this->tableName(),$poolName);
	}

	/**
	 * PHP setter magic method.
	 * This method is overridden so that AR attributes can be accessed like properties.
	 * @param string $name property name
	 * @param mixed $value property value
	 */
	public function __set($name, $value)
	{
		if ($this->hasAttribute($name)) {
			$this->_attributes[$name] = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	/**
	 * Returns the name of the associated database table.
	 * By default this method returns the class name as the table name.
	 * You may override this method if the table is not named after this convention.
	 * @return string the table name
	 */
	public function tableName()
	{
		return get_class($this);
	}

	/**
	 * Returns a value indicating whether the model has an attribute with the specified name.
	 * @param string $name the name of the attribute
	 * @return boolean whether the model has an attribute with the specified name.
	 */
	public function hasAttribute($name)
	{
		return isset($this->_attributes[$name]) || isset($this->_tableSchema->columns[$name]);
	}


	public static function model()
	{

	}

	/**
	 * @return string|null
	 */
	protected static function getConnectionPoolName()
	{

	}
}