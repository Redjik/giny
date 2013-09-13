<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager;

use Giny\ConnectionManager\Connection\ConnectionPool;

class ConnectionManager extends \CApplicationComponent
{
	/**
	 *
	 */
	public function getTableSchema($tableName,$poolName = null)
	{
		return $this->getPool($poolName)->getTableSchema($tableName);
	}

	/**
	 * @param null $poolName
	 * @return ConnectionPool
	 */
	public function getPool($poolName = null)
	{
		return new ConnectionPool();
	}
}