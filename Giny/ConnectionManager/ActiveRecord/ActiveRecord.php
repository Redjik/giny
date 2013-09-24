<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\ActiveRecord;

use Giny\ConnectionManager\Connection\ConnectionPool;
use Giny\ConnectionManager\Query\Query;
use Giny\ConnectionManager\Query\QueryBuilder;

class ActiveRecord extends \CModel
{

	const PARAM_PREFIX=':yp';

	private $_attributes;
	private $_alias='t';
	private $_new=false;						// whether this instance is new or not
	private $_forceMaster = false;
	private $_related=array();					// attribute name => related objects
	private $_c;								// query criteria (used by finder only)
	private $_pk;								// old primary key value

	public function __construct($scenario='insert')
	{

	}
	/*
	 * @TODO check if valid
	 * @TODO getter and setter
	 */
	public function getTableSchema()
	{
		return $this->getConnectionPool()->getTableSchema($this->tableName());
	}

	public function getDialect()
	{
		return $this->getConnectionPool()->getDialect();
	}



	public static function model()
	{

	}

	/**
	 * @return ConnectionPool
	 */
	protected static function getConnectionPool()
	{

	}

	/**
	 * PHP setter magic method.
	 * This method is overridden so that AR attributes can be accessed like properties.
	 * @param string $name property name
	 * @param mixed $value property value
	 * @return mixed|void
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
	 * Returns the table alias to be used by the find methods.
	 * In relational queries, the returned table alias may vary according to
	 * the corresponding relation declaration. Also, the default table alias
	 * set by {@link setTableAlias} may be overridden by the applied scopes.
	 * @param boolean $quote whether to quote the alias name
	 * @param boolean $checkScopes whether to check if a table alias is defined in the applied scopes so far.
	 * This parameter must be set false when calling this method in {@link defaultScope}.
	 * An infinite loop would be formed otherwise.
	 * @return string the default table alias
	 * @since 1.1.1
	 */
	public function getTableAlias($quote=false, $checkScopes=true)
	{
		if($checkScopes && ($criteria=$this->getDbCriteria(false))!==null && $criteria->alias!='')
			$alias=$criteria->alias;
		else
			$alias=$this->_alias;
		return $quote ? $this->getDialect()->quoteTableName($alias) : $alias;
	}

	/**
	 * Sets the table alias to be used in queries.
	 * @param string $alias the table alias to be used in queries. The alias should NOT be quoted.
	 * @since 1.1.3
	 */
	public function setTableAlias($alias)
	{
		$this->_alias=$alias;
	}

	/**
	 * Returns the query criteria associated with this model.
	 * @param boolean $createIfNull whether to create a criteria instance if it does not exist. Defaults to true.
	 * @return \CDbCriteria the query criteria that is associated with this model.
	 * This criteria is mainly used by {@link scopes named scope} feature to accumulate
	 * different criteria specifications.
	 */
	public function getDbCriteria($createIfNull=true)
	{
		if($this->_c===null)
		{
			if(($c=$this->defaultScope())!==array() || $createIfNull)
				$this->_c=new \CDbCriteria($c);
		}
		return $this->_c;
	}

	/**
	 * Finds a single active record with the specified condition.
	 * @param mixed $condition query condition or criteria.
	 * If a string, it is treated as query condition (the WHERE clause);
	 * If an array, it is treated as the initial values for constructing a {@link CDbCriteria} object;
	 * Otherwise, it should be an instance of {@link CDbCriteria}.
	 * @param array $params parameters to be bound to an SQL statement.
	 * This is only used when the first parameter is a string (query condition).
	 * In other cases, please use {@link CDbCriteria::params} to set parameters.
	 * @return ActiveRecord the record found. Null if no record is found.
	 */
	public function find($condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.find()','giny.db.ar.ActiveRecord');
		$criteria=$this->createCriteria($condition,$params);
		return $this->query($criteria);
	}

	/**
	 * Finds all active records satisfying the specified condition.
	 * See {@link find()} for detailed explanation about $condition and $params.
	 * @param mixed $condition query condition or criteria.
	 * @param array $params parameters to be bound to an SQL statement.
	 * @return ActiveRecordCollection list of active records satisfying the specified condition. An empty array is returned if none is found.
	 */
	public function findAll($condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.findAll()','giny.db.ar.ActiveRecord');
		$criteria=$this->createCriteria($condition,$params);
		return $this->query($criteria,true);
	}

	/**
	 * Finds a single active record with the specified primary key.
	 * See {@link find()} for detailed explanation about $condition and $params.
	 * @param mixed $pk primary key value(s). Use array for multiple primary keys. For composite key, each key value must be an array (column name=>column value).
	 * @param mixed $condition query condition or criteria.
	 * @param array $params parameters to be bound to an SQL statement.
	 * @return ActiveRecord the record found. Null if none is found.
	 */
	public function findByPk($pk,$condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.findByPk()','giny.db.ar.ActiveRecord');
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
		return $this->query($criteria);
	}

	/**
	 * Finds all active records with the specified primary keys.
	 * See {@link find()} for detailed explanation about $condition and $params.
	 * @param mixed $pk primary key value(s). Use array for multiple primary keys. For composite key, each key value must be an array (column name=>column value).
	 * @param mixed $condition query condition or criteria.
	 * @param array $params parameters to be bound to an SQL statement.
	 * @return ActiveRecordCollection the records found. An empty array is returned if none is found.
	 */
	public function findAllByPk($pk,$condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.findAllByPk()','system.db.ar.CActiveRecord');
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
		return $this->query($criteria,true);
	}

	/**
	 * Finds a single active record that has the specified attribute values.
	 * See {@link find()} for detailed explanation about $condition and $params.
	 * @param array $attributes list of attribute values (indexed by attribute names) that the active records should match.
	 * An attribute value can be an array which will be used to generate an IN condition.
	 * @param mixed $condition query condition or criteria.
	 * @param array $params parameters to be bound to an SQL statement.
	 * @return ActiveRecord the record found. Null if none is found.
	 */
	public function findByAttributes($attributes,$condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.findByAttributes()','system.db.ar.CActiveRecord');
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
		return $this->query($criteria);
	}

	/**
	 * Finds all active records that have the specified attribute values.
	 * See {@link find()} for detailed explanation about $condition and $params.
	 * @param array $attributes list of attribute values (indexed by attribute names) that the active records should match.
	 * An attribute value can be an array which will be used to generate an IN condition.
	 * @param mixed $condition query condition or criteria.
	 * @param array $params parameters to be bound to an SQL statement.
	 * @return ActiveRecordCollection the records found. An empty array is returned if none is found.
	 */
	public function findAllByAttributes($attributes,$condition='',$params=array())
	{
		\Yii::trace(get_class($this).'.findAllByAttributes()','system.db.ar.CActiveRecord');
		$prefix=$this->getTableAlias(true).'.';
		$criteria=$this->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
		return $this->query($criteria,true);
	}



	/**
	 * Creates a query criteria.
	 * @param mixed $condition query condition or criteria.
	 * If a string, it is treated as query condition (the WHERE clause);
	 * If an array, it is treated as the initial values for constructing a {@link CDbCriteria} object;
	 * Otherwise, it should be an instance of {@link CDbCriteria}.
	 * @param array $params parameters to be bound to an SQL statement.
	 * This is only used when the first parameter is a string (query condition).
	 * In other cases, please use {@link CDbCriteria::params} to set parameters.
	 * @return \CDbCriteria the created query criteria
	 */
	public function createCriteria($condition='',$params=array())
	{
		if(is_array($condition))
			$criteria=new \CDbCriteria($condition);
		elseif($condition instanceof \CDbCriteria)
			$criteria=clone $condition;
		else
		{
			$criteria=new \CDbCriteria;
			$criteria->condition=$condition;
			$criteria->params=$params;
		}
		return $criteria;
	}

	/**
	 * Creates a query criteria with the specified primary key.
	 * @param \CDbTableSchema $table the table schema ({@link CDbTableSchema}) or the table name (string).
	 * @param mixed $pk primary key value(s). Use array for multiple primary keys. For composite key, each key value must be an array (column name=>column value).
	 * @param mixed $condition query condition or criteria.
	 * If a string, it is treated as query condition;
	 * If an array, it is treated as the initial values for constructing a {@link CDbCriteria};
	 * Otherwise, it should be an instance of {@link CDbCriteria}.
	 * @param array $params parameters to be bound to an SQL statement.
	 * This is only used when the second parameter is a string (query condition).
	 * In other cases, please use {@link CDbCriteria::params} to set parameters.
	 * @param string $prefix column prefix (ended with dot). If null, it will be the table name
	 * @return \CDbCriteria the created query criteria
	 */
	public function createPkCriteria($table,$pk,$condition='',$params=array(),$prefix=null)
	{
		$criteria=$this->createCriteria($condition,$params);
		if($criteria->alias!='')
			$prefix=$this->getDialect()->quoteTableName($criteria->alias).'.';
		if(!is_array($pk)) // single key
		$pk=array($pk);
		if(is_array($table->primaryKey) && !isset($pk[0]) && $pk!==array()) // single composite key
		$pk=array($pk);
		$condition=$this->getDialect()->createInCondition($table,$table->primaryKey,$pk,$prefix);
		if($criteria->condition!='')
			$criteria->condition=$condition.' AND ('.$criteria->condition.')';
		else
			$criteria->condition=$condition;

		return $criteria;
	}

	/**
	 * Creates a query criteria with the specified column values.
	 * @param mixed $table the table schema ({@link CDbTableSchema}) or the table name (string).
	 * @param array $columns column values that should be matched in the query (name=>value)
	 * @param mixed $condition query condition or criteria.
	 * If a string, it is treated as query condition;
	 * If an array, it is treated as the initial values for constructing a {@link CDbCriteria};
	 * Otherwise, it should be an instance of {@link CDbCriteria}.
	 * @param array $params parameters to be bound to an SQL statement.
	 * This is only used when the third parameter is a string (query condition).
	 * In other cases, please use {@link CDbCriteria::params} to set parameters.
	 * @param string $prefix column prefix (ended with dot). If null, it will be the table name
	 * @throws Exception if specified column is not found in given table
	 * @return \CDbCriteria the created query criteria
	 */
	public function createColumnCriteria($table,$columns,$condition='',$params=array(),$prefix=null)
	{
		$criteria=$this->createCriteria($condition,$params);
		if($criteria->alias!='')
			$prefix=$this->getDialect()->quoteTableName($criteria->alias).'.';
		$bindByPosition=isset($criteria->params[0]);
		$conditions=array();
		$values=array();
		$i=0;
		if($prefix===null)
			$prefix=$table->rawName.'.';
		foreach($columns as $name=>$value)
		{
			if(($column=$table->getColumn($name))!==null)
			{
				if(is_array($value))
					$conditions[]=$this->getDialect()->createInCondition($table,$name,$value,$prefix);
				elseif($value!==null)
				{
					if($bindByPosition)
					{
						$conditions[]=$prefix.$column->rawName.'=?';
						$values[]=$value;
					}
					else
					{
						$conditions[]=$prefix.$column->rawName.'='.self::PARAM_PREFIX.$i;
						$values[self::PARAM_PREFIX.$i]=$value;
						$i++;
					}
				}
				else
					$conditions[]=$prefix.$column->rawName.' IS NULL';
			}
			else
				throw new Exception(\Yii::t('yii','Table "{table}" does not have a column named "{column}".',
					array('{table}'=>$table->name,'{column}'=>$name)));
		}
		$criteria->params=array_merge($values,$criteria->params);
		if(isset($conditions[0]))
		{
			if($criteria->condition!='')
				$criteria->condition=implode(' AND ',$conditions).' AND ('.$criteria->condition.')';
			else
				$criteria->condition=implode(' AND ',$conditions);
		}
		return $criteria;
	}

	/**
	 * Performs the actual DB query and populates the AR objects with the query result.
	 * This method is mainly internally used by other AR query methods.
	 * @param \CDbCriteria $criteria the query criteria
	 * @param boolean $all whether to return all data
	 * @return mixed the AR objects populated with the query result
	 * @since 1.1.7
	 */
	protected function query($criteria,$all=false)
	{
		$this->beforeFind();
		$this->applyScopes($criteria);

		if(empty($criteria->with))
		{
			if(!$all){
				$criteria->limit=1;
			}

			/** @var $query Query */
			$query = $this->getDialect()->buildQueryFromCriteria($criteria,$this->getTableSchema(),$this->getTableAlias());
			$query->setConnectionPool($this->getConnectionPool());
			if ($this->_forceMaster){
				$query->forceMaster();
			}
			return $all ? $this->populateRecords($query->queryAll(), true, $criteria->index) : $this->populateRecord($query->queryRow());
		}
		else
		{
			$finder=$this->getActiveFinder($criteria->with);
			return $finder->query($criteria,$all);
		}
	}

	/**
	 * Creates an active record with the given attributes.
	 * This method is internally used by the find methods.
	 * @param array $attributes attribute values (column name=>column value)
	 * @param boolean $callAfterFind whether to call {@link afterFind} after the record is populated.
	 * @return ActiveRecord the newly created active record. The class of the object is the same as the model class.
	 * Null is returned if the input data is false.
	 */
	public function populateRecord($attributes,$callAfterFind=true)
	{
		if($attributes!==false)
		{
			$record=$this->instantiate($attributes);
			$record->setScenario('update');
			foreach($attributes as $name=>$value)
			{
				if(property_exists($record,$name))
					$record->$name=$value;
				elseif(isset($md->columns[$name]))
					$record->_attributes[$name]=$value;
			}
			$record->_pk=$record->getPrimaryKey();
			$record->attachBehaviors($record->behaviors());
			if($callAfterFind)
				$record->afterFind();
			return $record;
		}
		else
			return null;
	}

	/**
	 * Creates a list of active records based on the input data.
	 * This method is internally used by the find methods.
	 * @param array $data list of attribute values for the active records.
	 * @param boolean $callAfterFind whether to call {@link afterFind} after each record is populated.
	 * @param string $index the name of the attribute whose value will be used as indexes of the query result array.
	 * If null, it means the array will be indexed by zero-based integers.
	 * @return ActiveRecord[] list of active records.
	 */
	public function populateRecords($data,$callAfterFind=true,$index=null)
	{
		$records=array();
		foreach($data as $attributes)
		{
			if(($record=$this->populateRecord($attributes,$callAfterFind))!==null)
			{
				if($index===null)
					$records[]=$record;
				else
					$records[$record->$index]=$record;
			}
		}
		return $records;
	}

	/**
	 * Creates an active record instance.
	 * This method is called by {@link populateRecord} and {@link populateRecords}.
	 * You may override this method if the instance being created
	 * depends the attributes that are to be populated to the record.
	 * For example, by creating a record based on the value of a column,
	 * you may implement the so-called single-table inheritance mapping.
	 * @return ActiveRecord the active record
	 */
	protected function instantiate()
	{
		$class=get_class($this);
		$model=new $class(null);
		return $model;
	}

	/**
	 * Applies the query scopes to the given criteria.
	 * This method merges {@link dbCriteria} with the given criteria parameter.
	 * It then resets {@link dbCriteria} to be null.
	 * @param \CDbCriteria $criteria the query criteria. This parameter may be modified by merging {@link dbCriteria}.
	 */
	public function applyScopes(&$criteria)
	{
		if(!empty($criteria->scopes))
		{
			$scs=$this->scopes();
			$c=$this->getDbCriteria();
			foreach((array)$criteria->scopes as $k=>$v)
			{
				if(is_integer($k))
				{
					if(is_string($v))
					{
						if(isset($scs[$v]))
						{
							$c->mergeWith($scs[$v],true);
							continue;
						}
						$scope=$v;
						$params=array();
					}
					elseif(is_array($v))
					{
						$scope=key($v);
						$params=current($v);
					}
				}
				elseif(is_string($k))
				{
					$scope=$k;
					$params=$v;
				}

				call_user_func_array(array($this,$scope),(array)$params);
			}
		}

		if(isset($c) || ($c=$this->getDbCriteria(false))!==null)
		{
			$c->mergeWith($criteria);
			$criteria=$c;
			$this->resetScope(false);
		}
	}

	/**
	 * Returns the default named scope that should be implicitly applied to all queries for this model.
	 * Note, default scope only applies to SELECT queries. It is ignored for INSERT, UPDATE and DELETE queries.
	 * The default implementation simply returns an empty array. You may override this method
	 * if the model needs to be queried with some default criteria (e.g. only active records should be returned).
	 * @return array the query criteria. This will be used as the parameter to the constructor
	 * of {@link CDbCriteria}.
	 */
	public function defaultScope()
	{
		return array();
	}

	/**
	 * Resets all scopes and criterias applied.
	 *
	 * @param boolean $resetDefault including default scope. This parameter available since 1.1.12
	 * @return ActiveRecord
	 * @since 1.1.2
	 */
	public function resetScope($resetDefault=true)
	{
		if($resetDefault)
			$this->_c=new \CDbCriteria();
		else
			$this->_c=null;

		return $this;
	}


	/**
	 * Saves the current record.
	 *
	 * The record is inserted as a row into the database table if its {@link isNewRecord}
	 * property is true (usually the case when the record is created using the 'new'
	 * operator). Otherwise, it will be used to update the corresponding row in the table
	 * (usually the case if the record is obtained using one of those 'find' methods.)
	 *
	 * Validation will be performed before saving the record. If the validation fails,
	 * the record will not be saved. You can call {@link getErrors()} to retrieve the
	 * validation errors.
	 *
	 * If the record is saved via insertion, its {@link isNewRecord} property will be
	 * set false, and its {@link scenario} property will be set to be 'update'.
	 * And if its primary key is auto-incremental and is not set before insertion,
	 * the primary key will be populated with the automatically generated key value.
	 *
	 * @param boolean $runValidation whether to perform validation before saving the record.
	 * If the validation fails, the record will not be saved to database.
	 * @param array $attributes list of attributes that need to be saved. Defaults to null,
	 * meaning all attributes that are loaded from DB will be saved.
	 * @return boolean whether the saving succeeds
	 */
	public function save($runValidation=true,$attributes=null)
	{
		if(!$runValidation || $this->validate($attributes))
			return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
		else
			return false;
	}

	/**
	 * Performs the validation.
	 *
	 * This method executes the validation rules as declared in {@link rules}.
	 * Only the rules applicable to the current {@link scenario} will be executed.
	 * A rule is considered applicable to a scenario if its 'on' option is not set
	 * or contains the scenario.
	 *
	 * Errors found during the validation can be retrieved via {@link getErrors}.
	 *
	 * @param array $attributes list of attributes that should be validated. Defaults to null,
	 * meaning any attribute listed in the applicable validation rules should be
	 * validated. If this parameter is given as a list of attributes, only
	 * the listed attributes will be validated.
	 * @param boolean $clearErrors whether to call {@link clearErrors} before performing validation
	 * @return boolean whether the validation is successful without any error.
	 * @see beforeValidate
	 * @see afterValidate
	 */
	public function validate($attributes=null, $clearErrors=true)
	{
		if($clearErrors)
			$this->clearErrors();
		if($this->beforeValidate())
		{
			foreach($this->getValidators() as $validator)
				$validator->validate($this,$attributes);
			$this->afterValidate();
			return !$this->hasErrors();
		}
		else
			return false;
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
		return isset($this->_attributes[$name]) || isset($this->getTableSchema()->columns[$name]);
	}



	/**
	 * Returns if the current record is new.
	 * @return boolean whether the record is new and should be inserted when calling {@link save}.
	 * This property is automatically set in constructor and {@link populateRecord}.
	 * Defaults to false, but it will be set to true if the instance is created using
	 * the new operator.
	 */
	public function getIsNewRecord()
	{
		return $this->_new;
	}

	/**
	 * Sets if the record is new.
	 * @param boolean $value whether the record is new and should be inserted when calling {@link save}.
	 * @see getIsNewRecord
	 */
	public function setIsNewRecord($value)
	{
		$this->_new=$value;
	}

	/**
	 * This event is raised before the record is saved.
	 * By setting {@link CModelEvent::isValid} to be false, the normal {@link save()} process will be stopped.
	 * @param \CModelEvent $event the event parameter
	 */
	public function onBeforeSave($event)
	{
		$this->raiseEvent('onBeforeSave',$event);
	}

	/**
	 * This event is raised after the record is saved.
	 * @param \CEvent $event the event parameter
	 */
	public function onAfterSave($event)
	{
		$this->raiseEvent('onAfterSave',$event);
	}

	/**
	 * This event is raised before the record is deleted.
	 * By setting {@link CModelEvent::isValid} to be false, the normal {@link delete()} process will be stopped.
	 * @param \CModelEvent $event the event parameter
	 */
	public function onBeforeDelete($event)
	{
		$this->raiseEvent('onBeforeDelete',$event);
	}

	/**
	 * This event is raised after the record is deleted.
	 * @param \CEvent $event the event parameter
	 */
	public function onAfterDelete($event)
	{
		$this->raiseEvent('onAfterDelete',$event);
	}

	/**
	 * This event is raised before an AR finder performs a find call.
	 * This can be either a call to CActiveRecords find methods or a find call
	 * when model is loaded in relational context via lazy or eager loading.
	 * If you want to access or modify the query criteria used for the
	 * find call, you can use {@link getDbCriteria()} to customize it based on your needs.
	 * When modifying criteria in beforeFind you have to make sure you are using the right
	 * table alias which is different on normal find and relational call.
	 * You can use {@link getTableAlias()} to get the alias used for the upcoming find call.
	 * Please note that modification of criteria is fully supported as of version 1.1.13.
	 * Earlier versions had some problems with relational context and applying changes correctly.
	 * @param \CModelEvent $event the event parameter
	 * @see beforeFind
	 */
	public function onBeforeFind($event)
	{
		$this->raiseEvent('onBeforeFind',$event);
	}

	/**
	 * This event is raised after the record is instantiated by a find method.
	 * @param \CEvent $event the event parameter
	 */
	public function onAfterFind($event)
	{
		$this->raiseEvent('onAfterFind',$event);
	}

	/**
	 * Given 'with' options returns a new active finder instance.
	 *
	 * @param mixed $with the relation names to be actively looked for
	 * @return \CActiveFinder active finder for the operation
	 *
	 * @since 1.1.14
	 */
	public function getActiveFinder($with)
	{
		return new \CActiveFinder($this,$with);
	}

	/**
	 * This event is raised before an AR finder performs a count call.
	 * If you want to access or modify the query criteria used for the
	 * count call, you can use {@link getDbCriteria()} to customize it based on your needs.
	 * When modifying criteria in beforeCount you have to make sure you are using the right
	 * table alias which is different on normal count and relational call.
	 * You can use {@link getTableAlias()} to get the alias used for the upcoming count call.
	 * @param \CModelEvent $event the event parameter
	 * @see beforeCount
	 * @since 1.1.14
	 */
	public function onBeforeCount($event)
	{
		$this->raiseEvent('onBeforeCount',$event);
	}

	/**
	 * This method is invoked before saving a record (after validation, if any).
	 * The default implementation raises the {@link onBeforeSave} event.
	 * You may override this method to do any preparation work for record saving.
	 * Use {@link isNewRecord} to determine whether the saving is
	 * for inserting or updating record.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the saving should be executed. Defaults to true.
	 */
	protected function beforeSave()
	{
		if($this->hasEventHandler('onBeforeSave'))
		{
			$event=new \CModelEvent($this);
			$this->onBeforeSave($event);
			return $event->isValid;
		}
		else
			return true;
	}

	/**
	 * This method is invoked after saving a record successfully.
	 * The default implementation raises the {@link onAfterSave} event.
	 * You may override this method to do postprocessing after record saving.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterSave()
	{
		if($this->hasEventHandler('onAfterSave'))
			$this->onAfterSave(new \CEvent($this));
	}

	/**
	 * This method is invoked before deleting a record.
	 * The default implementation raises the {@link onBeforeDelete} event.
	 * You may override this method to do any preparation work for record deletion.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 * @return boolean whether the record should be deleted. Defaults to true.
	 */
	protected function beforeDelete()
	{
		if($this->hasEventHandler('onBeforeDelete'))
		{
			$event=new \CModelEvent($this);
			$this->onBeforeDelete($event);
			return $event->isValid;
		}
		else
			return true;
	}

	/**
	 * This method is invoked after deleting a record.
	 * The default implementation raises the {@link onAfterDelete} event.
	 * You may override this method to do postprocessing after the record is deleted.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterDelete()
	{
		if($this->hasEventHandler('onAfterDelete'))
			$this->onAfterDelete(new \CEvent($this));
	}

	/**
	 * This method is invoked before an AR finder executes a find call.
	 * The find calls include {@link find}, {@link findAll}, {@link findByPk},
	 * {@link findAllByPk}, {@link findByAttributes}, {@link findAllByAttributes},
	 * {@link findBySql} and {@link findAllBySql}.
	 * The default implementation raises the {@link onBeforeFind} event.
	 * If you override this method, make sure you call the parent implementation
	 * so that the event is raised properly.
	 * For details on modifying query criteria see {@link onBeforeFind} event.
	 */
	protected function beforeFind()
	{
		if($this->hasEventHandler('onBeforeFind'))
		{
			$event=new \CModelEvent($this);
			$this->onBeforeFind($event);
		}
	}

	/**
	 * This method is invoked before an AR finder executes a count call.
	 * The count calls include {@link count} and {@link countByAttributes}
	 * The default implementation raises the {@link onBeforeCount} event.
	 * If you override this method, make sure you call the parent implementation
	 * so that the event is raised properly.
	 * @since 1.1.14
	 */
	protected function beforeCount()
	{
		if($this->hasEventHandler('onBeforeCount'))
			$this->onBeforeCount(new \CModelEvent($this));
	}

	/**
	 * This method is invoked after each record is instantiated by a find method.
	 * The default implementation raises the {@link onAfterFind} event.
	 * You may override this method to do postprocessing after each newly found record is instantiated.
	 * Make sure you call the parent implementation so that the event is raised properly.
	 */
	protected function afterFind()
	{
		if($this->hasEventHandler('onAfterFind'))
			$this->onAfterFind(new \CEvent($this));
	}

	/**
	 * Calls {@link beforeFind}.
	 * This method is internally used.
	 */
	public function beforeFindInternal()
	{
		$this->beforeFind();
	}

	/**
	 * Calls {@link afterFind}.
	 * This method is internally used.
	 */
	public function afterFindInternal()
	{
		$this->afterFind();
	}


	/**
	 * Returns the list of attribute names of the model.
	 * @return array list of attribute names.
	 */
	public function attributeNames()
	{
		return array_keys($this->getTableSchema()->columns);
	}

	/**
	 * @return ActiveRecord
	 */
	public function forceMaster()
	{
		$this->_forceMaster = true;
		return $this;
	}

	/**
	 * @return ActiveRecord
	 */
	public function unsetForceMaster()
	{
		$this->_forceMaster = false;
		return $this;
	}
}