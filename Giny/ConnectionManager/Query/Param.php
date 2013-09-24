<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Query;


class Param
{
	protected $value;

	protected $length;

	protected $dataType = \PDO::PARAM_STR;

	protected $driverOptions;

	public function __construct(&$value, $dataType=null, $length=null, $driverOptions=null)
	{
		$this->value = &$value;
		$this->dataType = $dataType;
		$this->length = $length;
		$this->driverOptions = $driverOptions;
	}

	/**
	 * @return mixed
	 */
	public function getValue()
	{
		return $this->value;
	}

	/**
	 * @return null
	 */
	public function getLength()
	{
		return $this->length;
	}

	/**
	 * @return null
	 */
	public function getDataType()
	{
		return $this->dataType;
	}

	/**
	 * @return null
	 */
	public function getDriverOptions()
	{
		return $this->driverOptions;
	}
}