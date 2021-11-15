<?php

namespace RexShijaku\SQLToCIBuilder\extractors;

/**
 * This class provides common functionality for all Extractor classes.
 * Extractor classes are classes which help to pull out SQL query parts in a way which are more understandable and processable by Builder.
 *
 * @author Rexhep Shijaku <rexhepshijaku@gmail.com>
 *
 */
abstract class AbstractExtractor
{

    protected $options;

    function __construct($options)
    {
        $this->options = $options;
    }

    function getValue($val)
    {
        return strtolower(trim($val));
    }

    function isLogicalOperator($operator)
    {
        return in_array($this->getValue($operator), array('and', 'or'));
    }

    function isAlias($val)
    {
        return isset($val['alias']) && $val['alias'] !== false;
    }

    function getFnParams($val, &$params)
    {
        if ($val['sub_tree'] !== false) {
            foreach ($val['sub_tree'] as $k => $item) {
                $params .= $item['base_expr'];
                if ($k < count($val['sub_tree']) - 1)
                    $params .= ",";
                if ($item['expr_type'] !== 'bracket_expression')
                    $this->getFnParams($item, $params);
            }
        }
        return $params;
    }

    function isSingleTable($parsed) // duplicate?
    {
        return (isset($parsed['FROM']) && count($parsed['FROM']) == 1 && $parsed['FROM'][0]['expr_type'] == 'table');
    }

    function validJoin($join_type)
    {
        return $this->getValue($join_type) != 'natural';
    }

    function handledJoinTypes($join_type) // handled in FROM statement
    {
        return $this->getValue($join_type) == 'cross';
    }

    function isArithmeticOperator($op)
    {
        return in_array($this->getValue($op), array('+', '-', '*', '/', '%'));
    }

    function isComparisonOperator($operator, $append = array())
    {
        $simple_operators = array('>', '<', '=', '!=', '>=', '<=', '!<', '!>', '<>');
        if (!empty($append))
            $simple_operators = array_merge($simple_operators, $append);
        return in_array($this->getValue($operator), $simple_operators);
    }

    function getTableVal($val)
    {
        if ($val['expr_type'] == 'table')
            $return = $val['table'];
        else {
            if ($val['expr_type'] == 'subquery') {
                $return = '(' . $val['base_expr'] . ')';
            } else {
                unset($val['alias']); // when base expr, alias already is present
                $return = $val['base_expr'];
            }
        }
        if ($this->isAlias($val))
            $return .= ' ' . $val['alias']['base_expr'];
        return $return;
    }

    function getExpression($val)
    {
        $this->getExpressionParts(array($val), $parts);
        return $this->mergeExpressionParts($parts);
    }

    function getExpressionParts($value, &$parts, &$raw = false, $recursive = false)
    {
        $raw = false;
        $val_len = count($value);
        foreach ($value as $k => $val) {


            if (in_array($val['expr_type'], array('function', 'aggregate_function'))) { // base expressions are not enough in such cases
                $local_parts[] = $val['base_expr'];
                $local_parts[] = '('; // e.g function wrappers
                if ($val['sub_tree'] !== false) { // functions + agg fn and others
                    $this->getExpressionParts($val['sub_tree'], $local_parts, $raw, true);
                }
                $local_parts[] = ')';
                if ($this->isAlias($val))
                    $local_parts[] = ' ' . $val['alias']['base_expr'];
                $parts[] = implode('', $local_parts);
                $local_parts = array();
                $raw = true;
                continue;
            }

            $sub_local = array($val['base_expr']);

            if (!in_array($val['expr_type'], array('expression', 'subquery'))) // these already have aliases appended
                if ($this->isAlias($val))
                    $sub_local[] = ' ' . $val['alias']['base_expr'];

            if (!$raw && !in_array($val['expr_type'], array('colref', 'const')))
                $raw = true;

            if ($recursive) {
                if (isset($val['delim']) && $val['delim'] !== false)
                    $sub_local[] = $val['delim'];
                else if ($k != $val_len - 1)
                    $sub_local[] = ",";
                $parts = array_merge($parts, $sub_local);
            } else
                $parts[] = implode('', $sub_local);
        }
    }

    function mergeExpressionParts($parts)
    {
        return (implode('', $parts));
    }

}
