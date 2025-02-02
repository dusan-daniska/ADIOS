<?php

/*
  This file is part of ADIOS Framework.

  This file is published under the terms of the license described
  in the license.md file which is located in the root folder of
  ADIOS Framework package.
*/

namespace ADIOS\Core\Db\DataTypes;

/**
 * @package DataTypes
 */
class DataTypeDate extends \ADIOS\Core\Db\DataType
{

  public function sqlCreateString($table_name, $col_name, $params = []) {
    return "`{$col_name}` date " . $this->getSqlDefinitions($params);
  }

  public function sqlValueString($table_name, $col_name, $value, $params = []) {
    $dateStr = str_replace(' ', '', $value);

    if (empty($dateStr) || strtotime((string) $dateStr) == 0) {
      $sql = "`{$col_name}` = NULL";
    } else {
      $dateStr = date('Y-m-d', strtotime((string) $dateStr));

      if (!preg_match('/^\d\d\d\d-\d\d-\d\d$/', $dateStr)) {
        $sql = "`{$col_name}` = NULL";
      } else {
        $sql = "`{$col_name}` = '{$dateStr}'";
      }
    }

    return $sql;
  }

  private function _toHtmlOrCsv($value, $params = []) {
    if (!empty($params['col_definition']['format'])) {
      $format = $params['col_definition']['format'];
    } else {
      $format = $this->app->locale->dateFormat();
    }

    $ts = strtotime((string) $value);
    $html = (0 == $ts ? '' : date($format, $ts));

    return $html;
  }

  public function toHtml($value, $params = []) {
    return $this->_toHtmlOrCsv($value, $params);
  }

  public function toCsv($value, $params = []) {
    return $this->_toHtmlOrCsv($value, $params);
  }

  public function normalize(\ADIOS\Core\Model $model, string $colName, $value, array $columnDescription)
  {
    return strtotime((string) $value) < 1000 ? null : $value;
  }
  
}
