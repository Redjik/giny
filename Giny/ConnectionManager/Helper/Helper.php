<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Helper;
use Giny\ConnectionManager\Exception;

class Helper
{

	/**
	 * @var array mapping between PDO driver and schema class name.
	 * A schema class can be specified using path alias.
	 * @since 1.1.6
	 */
	protected static $driverMap=array(
		'pgsql'=>'Pgsql',    // PostgreSQL
		'mysqli'=>'Mysql',   // MySQL
		'mysql'=>'Mysql',    // MySQL
		'sqlite'=>'Sqlite',  // sqlite 3
		'sqlite2'=>'Sqlite', // sqlite 2
		'mssql'=>'Mssql',    // Mssql driver on windows hosts
		'dblib'=>'Mssql',    // dblib drivers on linux (and maybe others os) hosts
		'sqlsrv'=>'Mssql',   // Mssql
		'oci'=>'Oci',        // Oracle driver
	);


	/**
	 * Returns the name of the DB driver
	 * @param  string $connectionString
	 * @throws Exception
	 * @return string name of the DB driver
	 */
	public static function getDriverName($connectionString)
	{

		if(($pos=strpos($connectionString, ':'))!==false) {
			$driver = strtolower(substr($connectionString, 0, $pos));
			if(isset(self::$driverMap[$driver])){
				return self::$driverMap[$driver];
			}
		}

		throw new Exception(\Yii::t('yii','Driver for {connection_string} can not be found',array('connection_string'=>$connectionString)));
	}

}