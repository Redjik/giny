<?php
/**
 * @author Ivan Matveev <Redjiks@gmail.com>.
 */

namespace Giny\ConnectionManager\Query;


class Value
{
	protected $value;

	protected $dataType = \PDO::PARAM_STR;


	public function __construct($value, $dataType=null)
	{
		$this->value = $value;
		$this->dataType = $dataType;
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
	public function getDataType()
	{
		return $this->dataType;
	}

}