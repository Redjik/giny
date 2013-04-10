<?php

class GDbQueryHelper
{
    /**
     * Determines the PDO type for the specified PHP type.
     * @param string $type The PHP type (obtained by gettype() call).
     * @return integer the corresponding PDO type
     */
    public static function getPdoType($type)
    {
        static $map=array
        (
            'boolean'=>PDO::PARAM_BOOL,
            'integer'=>PDO::PARAM_INT,
            'string'=>PDO::PARAM_STR,
            'resource'=>PDO::PARAM_LOB,
            'NULL'=>PDO::PARAM_NULL,
        );
        return isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;
    }

    /**
     * Alters the SQL to apply LIMIT and OFFSET.
     * Default implementation is applicable for PostgreSQL, MySQL and SQLite.
     * @param string $sql SQL query string without LIMIT and OFFSET.
     * @param integer $limit maximum number of rows, -1 to ignore limit.
     * @param integer $offset row offset, -1 to ignore offset.
     * @return string SQL with LIMIT and OFFSET
     */
    public static function applyLimit($sql,$limit,$offset)
    {
        if($limit>=0)
            $sql.=' LIMIT '.(int)$limit;
        if($offset>0)
            $sql.=' OFFSET '.(int)$offset;
        return $sql;
    }

    /**
     * Alters the SQL to apply JOIN clause.
     * @param string $sql the SQL statement to be altered
     * @param string $join the JOIN clause (starting with join type, such as INNER JOIN)
     * @return string the altered SQL statement
     */
    public static function applyJoin($sql,$join)
    {
        if($join!='')
            return $sql.' '.$join;
        else
            return $sql;
    }

    /**
     * Alters the SQL to apply WHERE clause.
     * @param string $sql the SQL statement without WHERE clause
     * @param string $condition the WHERE clause (without WHERE keyword)
     * @return string the altered SQL statement
     */
    public static function applyCondition($sql,$condition)
    {
        if($condition!='')
            return $sql.' WHERE '.$condition;
        else
            return $sql;
    }

    /**
     * Alters the SQL to apply ORDER BY.
     * @param string $sql SQL statement without ORDER BY.
     * @param string $orderBy column ordering
     * @return string modified SQL applied with ORDER BY.
     */
    public static function applyOrder($sql,$orderBy)
    {
        if($orderBy!='')
            return $sql.' ORDER BY '.$orderBy;
        else
            return $sql;
    }

    /**
     * Alters the SQL to apply GROUP BY.
     * @param string $sql SQL query string without GROUP BY.
     * @param string $group GROUP BY
     * @return string SQL with GROUP BY.
     */
    public static function applyGroup($sql,$group)
    {
        if($group!='')
            return $sql.' GROUP BY '.$group;
        else
            return $sql;
    }

    /**
     * Alters the SQL to apply HAVING.
     * @param string $sql SQL query string without HAVING
     * @param string $having HAVING
     * @return string SQL with HAVING
     */
    public static function applyHaving($sql,$having)
    {
        if($having!='')
            return $sql.' HAVING '.$having;
        else
            return $sql;
    }
}
