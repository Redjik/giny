<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Connection;

use Giny\ConnectionManager\SqlDialect\DialectInterface;
use Giny\ConnectionManager\TransactionManager\TransactionManager;

interface ConnectionPoolInterface
{
	public function getTablePrefix();

	public function getEnableParamLogging();

	public function getEnableProfiling();

	public function getName();

	/**
	 * @return ConnectionInterface
	 */
	public function getReadConnection();

	/**
	 * @return ConnectionInterface
	 */
	public function getWriteConnection();

	/**
	 * @return ConnectionInterface
	 */
	public function getSchemaConnection();

	/**
	 * @param $tableName
	 * @return \CDbTableSchema
	 */
	public function getTableSchema($tableName);

	/**
	 * @return DialectInterface
	 */
	public function getDialect();

}