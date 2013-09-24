<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\SqlDialect;


use Giny\ConnectionManager\Query\Query;
use Giny\ConnectionManager\Query\QueryBuilder;

class Dialect implements DialectInterface
{
	/**
	 * @param $dialectDriverName
	 * @return Dialect
	 */
	public static function factory($dialectDriverName)
	{
		$dialectClass = __NAMESPACE__.$dialectDriverName;
		$dialect = new $dialectClass();
		return $dialect;
	}

	/**
	 * Quotes a table name for use in a query.
	 * If the table name contains schema prefix, the prefix will also be properly quoted.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 * @see quoteSimpleTableName
	 */
	public function quoteTableName($name)
	{
		if(strpos($name,'.')===false)
			return $this->quoteSimpleTableName($name);
		$parts=explode('.',$name);
		foreach($parts as $i=>$part)
			$parts[$i]=$this->quoteSimpleTableName($part);
		return implode('.',$parts);

	}

	/**
	 * Quotes a simple table name for use in a query.
	 * A simple table name does not schema prefix.
	 * @param string $name table name
	 * @return string the properly quoted table name
	 * @since 1.1.6
	 */
	public function quoteSimpleTableName($name)
	{
		return "'".$name."'";
	}

	/**
	 * Quotes a column name for use in a query.
	 * If the column name contains prefix, the prefix will also be properly quoted.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 * @see quoteSimpleColumnName
	 */
	public function quoteColumnName($name)
	{
		if(($pos=strrpos($name,'.'))!==false)
		{
			$prefix=$this->quoteTableName(substr($name,0,$pos)).'.';
			$name=substr($name,$pos+1);
		}
		else
			$prefix='';
		return $prefix . ($name==='*' ? $name : $this->quoteSimpleColumnName($name));
	}

	/**
	 * Quotes a simple column name for use in a query.
	 * A simple column name does not contain prefix.
	 * @param string $name column name
	 * @return string the properly quoted column name
	 * @since 1.1.6
	 */
	public function quoteSimpleColumnName($name)
	{
		return '"'.$name.'"';
	}

	/**
	 * Generates the expression for selecting rows of specified primary key values.
	 * @param \CDbTableSchema $table the table schema ({@link CDbTableSchema}) or the table name (string).
	 * @param mixed $columnName the column name(s). It can be either a string indicating a single column
	 * or an array of column names. If the latter, it stands for a composite key.
	 * @param array $values list of key values to be selected within
	 * @param string $prefix column prefix (ended with dot). If null, it will be the table name
	 * @throws Exception if specified column is not found in given table
	 * @return string the expression for selection
	 */
	public function createInCondition($table,$columnName,$values,$prefix=null)
	{
		if(($n=count($values))<1)
			return '0=1';

		if($prefix===null)
			$prefix=$table->rawName.'.';

		if(is_array($columnName) && count($columnName)===1)
			$columnName=reset($columnName);

		if(is_string($columnName)) // simple key
		{
			if(!isset($table->columns[$columnName]))
				throw new Exception(\Yii::t('yii','Table "{table}" does not have a column named "{column}".',
					array('{table}'=>$table->name, '{column}'=>$columnName)));
			/** @var $column \CDbColumnSchema */
			$column=$table->columns[$columnName];

			$values=array_values($values);
			foreach($values as &$value)
			{
				$value=$column->typecast($value);
				if(is_string($value))
					$value=$this->quoteValue($value);
			}
			if($n===1)
				return $prefix.$column->rawName.($values[0]===null?' IS NULL':'='.$values[0]);
			else
				return $prefix.$column->rawName.' IN ('.implode(', ',$values).')';
		}
		elseif(is_array($columnName)) // composite key: $values=array(array('pk1'=>'v1','pk2'=>'v2'),array(...))
		{
			foreach($columnName as $name)
			{
				if(!isset($table->columns[$name]))
					throw new Exception(\Yii::t('yii','Table "{table}" does not have a column named "{column}".',
						array('{table}'=>$table->name, '{column}'=>$name)));

				for($i=0;$i<$n;++$i)
				{
					if(isset($values[$i][$name]))
					{
						/** @var $column \CDbColumnSchema */
						$column=$table->columns[$name];
						$value=$column->typecast($values[$i][$name]);
						if(is_string($value))
							$values[$i][$name]=$this->quoteValue($value);
						else
							$values[$i][$name]=$value;
					}
					else
						throw new Exception(\Yii::t('yii','The value for the column "{column}" is not supplied when querying the table "{table}".',
							array('{table}'=>$table->name,'{column}'=>$name)));
				}
			}
			if(count($values)===1)
			{
				$entries=array();
				foreach($values[0] as $name=>$value)
					$entries[]=$prefix.$table->columns[$name]->rawName.($value===null?' IS NULL':'='.$value);
				return implode(' AND ',$entries);
			}

			return $this->createCompositeInCondition($table,$values,$prefix);
		}
		else
			throw new Exception(\Yii::t('yii','Column name must be either a string or an array.'));
	}

	/**
	 * Generates the expression for selecting rows with specified composite key values.
	 * @param \CDbTableSchema $table the table schema
	 * @param array $values list of primary key values to be selected within
	 * @param string $prefix column prefix (ended with dot)
	 * @return string the expression for selection
	 */
	protected function createCompositeInCondition($table,$values,$prefix)
	{
		$keyNames=array();
		foreach(array_keys($values[0]) as $name)
			$keyNames[]=$prefix.$table->columns[$name]->rawName;
		$vs=array();
		foreach($values as $value)
			$vs[]='('.implode(', ',$value).')';
		return '('.implode(', ',$keyNames).') IN ('.implode(', ',$vs).')';
	}


	/**
	 * Builds a SQL statement for creating a new DB table.
	 *
	 * The columns in the new  table should be specified as name-definition pairs (e.g. 'name'=>'string'),
	 * where name stands for a column name which will be properly quoted by the method, and definition
	 * stands for the column type which can contain an abstract DB type.
	 * The {@link getColumnType} method will be invoked to convert any abstract type into a physical one.
	 *
	 * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
	 * inserted into the generated SQL.
	 *
	 * @param string $table the name of the table to be created. The name will be properly quoted by the method.
	 * @param array $columns the columns (name=>definition) in the new table.
	 * @param string $options additional SQL fragment that will be appended to the generated SQL.
	 * @return string the SQL statement for creating a new DB table.
	 * @since 1.1.6
	 */
	public function createTable($table,$columns,$options=null)
	{
		$cols=array();
		foreach($columns as $name=>$type)
		{
			if(is_string($name))
				$cols[]="\t".$this->quoteColumnName($name).' '.$this->getColumnType($type);
			else
				$cols[]="\t".$type;
		}
		$sql="CREATE TABLE ".$this->quoteTableName($table)." (\n".implode(",\n",$cols)."\n)";
		return $options===null ? $sql : $sql.' '.$options;
	}

	/**
	 * Builds a SQL statement for renaming a DB table.
	 * @param string $table the table to be renamed. The name will be properly quoted by the method.
	 * @param string $newName the new table name. The name will be properly quoted by the method.
	 * @return string the SQL statement for renaming a DB table.
	 * @since 1.1.6
	 */
	public function renameTable($table,$newName)
	{
		return 'RENAME TABLE ' . $this->quoteTableName($table) . ' TO ' . $this->quoteTableName($newName);
	}

	/**
	 * Builds a SQL statement for dropping a DB table.
	 * @param string $table the table to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping a DB table.
	 * @since 1.1.6
	 */
	public function dropTable($table)
	{
		return "DROP TABLE ".$this->quoteTableName($table);
	}

	/**
	 * Builds a SQL statement for truncating a DB table.
	 * @param string $table the table to be truncated. The name will be properly quoted by the method.
	 * @return string the SQL statement for truncating a DB table.
	 * @since 1.1.6
	 */
	public function truncateTable($table)
	{
		return "TRUNCATE TABLE ".$this->quoteTableName($table);
	}

	/**
	 * Builds a SQL statement for adding a new DB column.
	 * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
	 * @param string $column the name of the new column. The name will be properly quoted by the method.
	 * @param string $type the column type. The {@link getColumnType} method will be invoked to convert abstract column type (if any)
	 * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
	 * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
	 * @return string the SQL statement for adding a new column.
	 * @since 1.1.6
	 */
	public function addColumn($table,$column,$type)
	{
		return 'ALTER TABLE ' . $this->quoteTableName($table)
			   . ' ADD ' . $this->quoteColumnName($column) . ' '
			   . $this->getColumnType($type);
	}

	/**
	 * Builds a SQL statement for dropping a DB column.
	 * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
	 * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping a DB column.
	 * @since 1.1.6
	 */
	public function dropColumn($table,$column)
	{
		return "ALTER TABLE ".$this->quoteTableName($table)
			   ." DROP COLUMN ".$this->quoteColumnName($column);
	}

	/**
	 * Builds a SQL statement for renaming a column.
	 * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
	 * @param string $name the old name of the column. The name will be properly quoted by the method.
	 * @param string $newName the new name of the column. The name will be properly quoted by the method.
	 * @return string the SQL statement for renaming a DB column.
	 * @since 1.1.6
	 */
	public function renameColumn($table,$name,$newName)
	{
		return "ALTER TABLE ".$this->quoteTableName($table)
			   . " RENAME COLUMN ".$this->quoteColumnName($name)
			   . " TO ".$this->quoteColumnName($newName);
	}

	/**
	 * Builds a SQL statement for changing the definition of a column.
	 * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
	 * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
	 * @param string $type the new column type. The {@link getColumnType} method will be invoked to convert abstract column type (if any)
	 * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
	 * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
	 * @return string the SQL statement for changing the definition of a column.
	 * @since 1.1.6
	 */
	public function alterColumn($table,$column,$type)
	{
		return 'ALTER TABLE ' . $this->quoteTableName($table) . ' CHANGE '
			   . $this->quoteColumnName($column) . ' '
			   . $this->quoteColumnName($column) . ' '
			   . $this->getColumnType($type);
	}

	/**
	 * Builds a SQL statement for adding a foreign key constraint to an existing table.
	 * The method will properly quote the table and column names.
	 * @param string $name the name of the foreign key constraint.
	 * @param string $table the table that the foreign key constraint will be added to.
	 * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas or pass as an array of column names.
	 * @param string $refTable the table that the foreign key references to.
	 * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas or pass as an array of column names.
	 * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
	 * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
	 * @return string the SQL statement for adding a foreign key constraint to an existing table.
	 * @since 1.1.6
	 */
	public function addForeignKey($name,$table,$columns,$refTable,$refColumns,$delete=null,$update=null)
	{
		if(is_string($columns))
			$columns=preg_split('/\s*,\s*/',$columns,-1,PREG_SPLIT_NO_EMPTY);
		foreach($columns as $i=>$col)
			$columns[$i]=$this->quoteColumnName($col);
		if(is_string($refColumns))
			$refColumns=preg_split('/\s*,\s*/',$refColumns,-1,PREG_SPLIT_NO_EMPTY);
		foreach($refColumns as $i=>$col)
			$refColumns[$i]=$this->quoteColumnName($col);
		$sql='ALTER TABLE '.$this->quoteTableName($table)
			 .' ADD CONSTRAINT '.$this->quoteColumnName($name)
			 .' FOREIGN KEY ('.implode(', ',$columns).')'
			 .' REFERENCES '.$this->quoteTableName($refTable)
			 .' ('.implode(', ',$refColumns).')';
		if($delete!==null)
			$sql.=' ON DELETE '.$delete;
		if($update!==null)
			$sql.=' ON UPDATE '.$update;
		return $sql;
	}

	/**
	 * Builds a SQL statement for dropping a foreign key constraint.
	 * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
	 * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping a foreign key constraint.
	 * @since 1.1.6
	 */
	public function dropForeignKey($name,$table)
	{
		return 'ALTER TABLE '.$this->quoteTableName($table)
			   .' DROP CONSTRAINT '.$this->quoteColumnName($name);
	}

	/**
	 * Builds a SQL statement for creating a new index.
	 * @param string $name the name of the index. The name will be properly quoted by the method.
	 * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
	 * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
	 * by commas or pass as an array of column names. Each column name will be properly quoted by the method, unless a parenthesis is found in the name.
	 * @param boolean $unique whether to add UNIQUE constraint on the created index.
	 * @return string the SQL statement for creating a new index.
	 * @since 1.1.6
	 */
	public function createIndex($name,$table,$columns,$unique=false)
	{
		$cols=array();
		if(is_string($columns))
			$columns=preg_split('/\s*,\s*/',$columns,-1,PREG_SPLIT_NO_EMPTY);
		foreach($columns as $col)
		{
			if(strpos($col,'(')!==false)
				$cols[]=$col;
			else
				$cols[]=$this->quoteColumnName($col);
		}
		return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
			   . $this->quoteTableName($name).' ON '
			   . $this->quoteTableName($table).' ('.implode(', ',$cols).')';
	}

	/**
	 * Builds a SQL statement for dropping an index.
	 * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
	 * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
	 * @return string the SQL statement for dropping an index.
	 * @since 1.1.6
	 */
	public function dropIndex($name,$table)
	{
		return 'DROP INDEX '.$this->quoteTableName($name).' ON '.$this->quoteTableName($table);
	}

	/**
	 * Builds a SQL statement for adding a primary key constraint to an existing table.
	 * @param string $name the name of the primary key constraint.
	 * @param string $table the table that the primary key constraint will be added to.
	 * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
	 * Array value can be passed since 1.1.14.
	 * @return string the SQL statement for adding a primary key constraint to an existing table.
	 * @since 1.1.13
	 */
	public function addPrimaryKey($name,$table,$columns)
	{
		if(is_string($columns))
			$columns=preg_split('/\s*,\s*/',$columns,-1,PREG_SPLIT_NO_EMPTY);
		foreach($columns as $i=>$col)
			$columns[$i]=$this->quoteColumnName($col);
		return 'ALTER TABLE ' . $this->quoteTableName($table) . ' ADD CONSTRAINT '
			   . $this->quoteColumnName($name) . '  PRIMARY KEY ('
			   . implode(', ',$columns). ' )';
	}

	/**
	 * Builds a SQL statement for removing a primary key constraint to an existing table.
	 * @param string $name the name of the primary key constraint to be removed.
	 * @param string $table the table that the primary key constraint will be removed from.
	 * @return string the SQL statement for removing a primary key constraint from an existing table.
	 * @since 1.1.13
	 */
	public function dropPrimaryKey($name,$table)
	{
		return 'ALTER TABLE ' . $this->quoteTableName($table) . ' DROP CONSTRAINT '
			   . $this->quoteColumnName($name);
	}

	/**
	 * @param \CDbCriteria $criteria
	 * @param \CDbTableSchema $table
	 * @param string $alias
	 * @return Query
	 */
	public function buildQueryFromCriteria($criteria,$table, $alias = 't')
	{

		$select=is_array($criteria->select) ? implode(', ',$criteria->select) : $criteria->select;
		if($criteria->alias!='')
			$alias=$criteria->alias;
		$alias=$this->quoteTableName($alias);

		// issue 1432: need to expand * when SQL has JOIN
		if($select==='*' && !empty($criteria->join))
		{
			$prefix=$alias.'.';
			$select=array();
			foreach($table->getColumnNames() as $name)
				$select[]=$prefix.$this->quoteColumnName($name);
			$select=implode(', ',$select);
		}

		$builder = new QueryBuilder($this);
		$builder->setDistinct($criteria->distinct);
		$builder->select($select);
		$builder->from($table->rawName.' '.$alias);
		$builder->setJoin($criteria->join);
		$builder->where($criteria->condition,$criteria->params);
		$builder->group($criteria->group);
		$builder->having($criteria->having);
		$builder->order($criteria->order);
		$builder->limit($criteria->limit, $criteria->offset);

		return $builder->buildQuery();

	}
	
}