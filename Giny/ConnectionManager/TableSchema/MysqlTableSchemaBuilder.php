<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\TableSchema;


use Giny\ConnectionManager\Helper\MysqlHelper;

class MysqlTableSchemaBuilder extends TableSchemaBuilder
{

	/**
	 * Loads the metadata for the specified table.
	 * @param string $name table name
	 * @return \CMysqlTableSchema driver dependent table metadata. Null if the table does not exist.
	 */
	protected function loadTable($name)
	{
		$table=new \CMysqlTableSchema;
		$this->resolveTableNames($table,$name);

		if($this->findColumns($table))
		{
			$this->findConstraints($table);
			return $table;
		}
		else
			return null;
	}

	/**
	 * Generates various kinds of table names.
	 * @param \CMysqlTableSchema $table the table instance
	 * @param string $name the unquoted table name
	 */
	protected function resolveTableNames($table,$name)
	{
		$parts=explode('.',str_replace(array('`','"'),'',$name));
		if(isset($parts[1]))
		{
			$table->schemaName=$parts[0];
			$table->name=$parts[1];
			$table->rawName=MysqlHelper::quoteTableName($table->schemaName).'.'.MysqlHelper::quoteTableName($table->name);
		}
		else
		{
			$table->name=$parts[0];
			$table->rawName=MysqlHelper::quoteTableName($table->name);
		}
	}

	/**
	 * Collects the table column metadata.
	 * @param \CMysqlTableSchema $table the table metadata
	 * @return boolean whether the table exists in the database
	 */
	protected function findColumns($table)
	{
		$sql='SHOW FULL COLUMNS FROM '.$table->rawName;
		try
		{
			$columns=$this->_connection->createCommand($sql)->queryAll();
		}
		catch(\Exception $e)
		{
			return false;
		}
		foreach($columns as $column)
		{
			$c=$this->createColumn($column);
			$table->columns[$c->name]=$c;
			if($c->isPrimaryKey)
			{
				if($table->primaryKey===null)
					$table->primaryKey=$c->name;
				elseif(is_string($table->primaryKey))
					$table->primaryKey=array($table->primaryKey,$c->name);
				else
					$table->primaryKey[]=$c->name;
				if($c->autoIncrement)
					$table->sequenceName='';
			}
		}
		return true;
	}

	/**
	 * Creates a table column.
	 * @param array $column column metadata
	 * @return \CDbColumnSchema normalized column metadata
	 */
	protected function createColumn($column)
	{
		$c=new \CMysqlColumnSchema;
		$c->name=$column['Field'];
		$c->rawName=MysqlHelper::quoteColumnName($c->name);
		$c->allowNull=$column['Null']==='YES';
		$c->isPrimaryKey=strpos($column['Key'],'PRI')!==false;
		$c->isForeignKey=false;
		$c->init($column['Type'],$column['Default']);
		$c->autoIncrement=strpos(strtolower($column['Extra']),'auto_increment')!==false;
		if(isset($column['Comment']))
			$c->comment=$column['Comment'];

		return $c;
	}

	/**
	 * Collects the foreign key column details for the given table.
	 * @param \CMysqlTableSchema $table the table metadata
	 */
	protected function findConstraints($table)
	{
		$row=$this->_connection->createCommand('SHOW CREATE TABLE '.$table->rawName)->queryRow();
		$matches=array();
		$regexp='/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
		foreach($row as $sql)
		{
			if(preg_match_all($regexp,$sql,$matches,PREG_SET_ORDER))
				break;
		}
		foreach($matches as $match)
		{
			$keys=array_map('trim',explode(',',str_replace(array('`','"'),'',$match[1])));
			$fks=array_map('trim',explode(',',str_replace(array('`','"'),'',$match[3])));
			foreach($keys as $k=>$name)
			{
				$table->foreignKeys[$name]=array(str_replace(array('`','"'),'',$match[2]),$fks[$k]);
				if(isset($table->columns[$name]))
					$table->columns[$name]->isForeignKey=true;
			}
		}
	}

}