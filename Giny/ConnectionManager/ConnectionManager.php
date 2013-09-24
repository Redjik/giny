<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager;

use Giny\ConnectionManager\Connection\ConnectionPool;
use Giny\ConnectionManager\TransactionManager\TransactionManager;

class ConnectionManager extends \CApplicationComponent
{

	public $connectionPools;

	protected $_pools;
	
	protected $_transaction;

	/**
	 * @param null $poolName
	 * @return ConnectionPool
	 */
	public function getPool($poolName = null)
	{
		if ($poolName === null){
			return $this->getDefaultPool();
		}

		if (empty($this->_pools[$poolName])){
			$this->_pools[$poolName] = $this->createConnectionPool($poolName);
		}

		return $this->_pools[$poolName];
	}

	protected function createConnectionPool($poolName)
	{
		if (isset($this->connectionPools[$poolName])){

			if (empty($this->connectionPools[$poolName]['class'])){
				$this->connectionPools[$poolName]['class'] = '\Giny\ConnectionManager\Connection\ConnectionPool';
				$this->connectionPools[$poolName]['name'] = $poolName;
			}
			$connectionPool = \Yii::createComponent($this->connectionPools[$poolName]);
			$connectionPool->init();
			return $connectionPool;
		}

		throw new Exception(\Yii::t('giny', 'There is no pool with index: {poolName}',array('{poolName}'=>$poolName)));
	}

	public function getDefaultConnectionPool()
	{
		if (empty($this->connectionPools))
			throw new Exception(\Yii::t('giny', 'No connection pool has been defined'));

		reset($this->connectionPools);

		$firstPool = key($this->connectionPools);

		return $this->getPool($firstPool);
	}

	/**
	 * Returns transaction manager if transaction is started.
	 * @return TransactionManager|null
	 */
	public function getCurrentTransaction()
	{
		return $this->getTransactionManager()->getCurrentTransaction();
	}

	/**
	 * Checks if transaction was started for some other DB
	 * @return bool
	 */
	public function isOtherDbTransactionStarted()
	{
		if ($this->_transaction !== null || $this->_transaction->isOtherDbTransactionStarted($this)){
			return true;
		}

		return false;
	}

	/**
	 * Starts a transaction.
	 * @return TransactionManager the transaction initiated
	 */
	public function beginTransaction()
	{
		return $this->getTransactionManager()->beginTransaction();
	}

	/**
	 * @return TransactionManager|mixed
	 */
	public function getTransactionManager()
	{
		if ($this->_transaction === null){
			$config['class'] = 'Giny\ConnectionManager\TransactionManager\TransactionManager';
			$this->_transaction = \Yii::createComponent($config);
		}

		$this->_transaction->setPool($this);

		return $this->_transaction;
	}
}