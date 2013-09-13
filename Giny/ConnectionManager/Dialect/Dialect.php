<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\SqlDialect;


class Dialect
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
	
	
}