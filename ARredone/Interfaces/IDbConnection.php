<?php

interface IDbConnection
{

    /**
     * @return string
     */
    public function getConnectionString();

    public function getUserName();

    public function getPassword();

    public function getQueryCachingDuration();

    public function setQueryCachingDuration($duration);

    public function getQueryCachingCount();

    public function setQueryCachingCount($count);

    public function getQueryCacheID();

    public function getTablePrefix();

    public function getEnableParamLogging();

    public function getEnableProfiling();

    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID($sequenceName='');

    /**
     * @return PDO
     */
    public function getPdoInstance();

    /**
     * Obtains a specific DB connection attribute information.
     * @param integer $name the attribute to be queried
     * @return mixed the corresponding attribute information
     * @see http://www.php.net/manual/en/function.PDO-getAttribute.php
     */
    public function getAttribute($name);

    /**
     * Sets an attribute on the database connection.
     * @param integer $name the attribute to be set
     * @param mixed $value the attribute value
     * @see http://www.php.net/manual/en/function.PDO-setAttribute.php
     */
    public function setAttribute($name,$value);

	/**
	 * Quotes a string value for use in a query.
	 * @param string $str string to be quoted
	 * @return string the properly quoted string
	 * @see http://www.php.net/manual/en/function.PDO-quote.php
	 */
	public function quoteValue($str);
}
