<?php

class GTransactionManager extends CComponent
{
	/**
	 * @var GConnectionManager
	 */
	private $_pool;

	/**
	 * Database drivers that support SAVEPOINTs.
	 * @var array
	 */
	private $savepointTransactions = array("Pgsql", "Mysql");

	/**
	 * To save partial transactions or not.
	 * On false - single exception will rollback all changes.
	 * @var bool
	 */
	private $partialSave = false;

	private $rollbackInAnyCase = false;

	/**
	 * The current transaction level.
	 * @var int
	 */
	protected $transLevel = 0;

	/**
	 * The current transaction level.
	 * @return int
	 */
	public function getTransLevel()
	{
		return $this->transLevel;
	}


	/**
	 * @return GTransactionManager|null
	 */
	public function getCurrentTransaction()
	{
		if($this->transLevel!==0)
		{
			return $this;
		}
		return null;
	}


	/**
	 * @return GTransactionManager
	 * @throws CDbException
	 */
	public function beginTransaction()
	{

		Yii::trace('Starting transaction','system.db.GDbConnection');

		if($this->transLevel === 0 || !$this->nestable())
		{
			$this->transLevel++;
			if ($this->getConnection()->getPdoInstance()->beginTransaction()){
				return $this;
			}
		}

		if ($this->transLevel > 0 && !$this->partialSave)
		{
			$this->transLevel++;
			return $this;
		}

		if ($this->transLevel > 0 && $this->partialSave && $this->nestable())
		{
			$result = (bool)$this->getConnection()->getPdoInstance()->exec("SAVEPOINT LEVEL{$this->transLevel}");
			$this->transLevel++;
			if ($result){
				return $this;
			}
		}

		throw new CDbException('Can not start transaction');
	}

	/**
	 * Commit transaction
	 * @return bool
	 */
	public function commit()
	{
		$this->transLevel--;

		if ($this->transLevel === 0 && $this->rollbackInAnyCase)
		{
			$this->rollbackInAnyCase = false;
			return $this->getConnection()->getPdoInstance()->rollBack();
		}

		if($this->transLevel === 0 || !$this->nestable())
			return $this->getConnection()->getPdoInstance()->commit();

		if ($this->transLevel > 0 && !$this->partialSave)
			return true;

		if ($this->transLevel > 0 && $this->partialSave && $this->nestable())
			return (bool)$this->getConnection()->getPdoInstance()->exec("RELEASE SAVEPOINT LEVEL{$this->transLevel}");

		return false;
	}

	/**
	 * Rollback transaction
	 * @return bool
	 */
	public function rollBack()
	{
		$this->transLevel--;

		if($this->transLevel === 0 || !$this->nestable())
		{
			$this->rollbackInAnyCase = false;
			return $this->getConnection()->getPdoInstance()->rollBack();
		}

		if ($this->transLevel > 0 && !$this->partialSave)
		{
			$this->rollbackInAnyCase = true;
			return true;
		}

		if ($this->transLevel > 0 && $this->partialSave && $this->nestable())
			return (bool)$this->getConnection()->getPdoInstance()->exec("ROLLBACK TO SAVEPOINT LEVEL{$this->transLevel}");

		return false;
	}

	/**
	 * @param boolean $partialSave
	 */
	public function setPartialSave($partialSave)
	{
		$this->partialSave = $partialSave;
	}

	/**
	 * Sets connection for current transaction.
	 * @param GConnectionManager $pool
	 * @throws CDbException
	 */
	public function setPool($pool)
	{
		if ($this->isOtherDbTransactionStarted($pool)){
			throw new CDbException('You can not change DB during transaction');
		}
		$this->_pool = $pool;
	}

	public function isOtherDbTransactionStarted($pool)
	{
		return $this->getTransLevel() !== 0 && $pool !== $this->_pool;
	}

	/**
	 * @return IDbConnection
	 */
	protected function getConnection()
	{
		return $this->_pool->getWriteConnection();
	}

	/**
	 * Checks if DB driver supports nested transactions
	 * @internal param array $savepointTransactions
	 * @return bool
	 */
	protected function nestable() {
		return in_array(GDbQueryHelper::getDriverName($this->_pool->getWriteConnection()), $this->savepointTransactions);
	}

}
