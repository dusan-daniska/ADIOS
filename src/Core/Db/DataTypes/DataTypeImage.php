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
class DataTypeImage extends \ADIOS\Core\Db\DataType {
  public function sqlCreateString($table_name, $col_name, $params = []) {
    return "`$col_name` varchar(255) " . $this->getSqlDefinitions($params);
  }

  public function sqlValueString($table_name, $col_name, $value, $params = []) {
    $params = \ADIOS\Core\Helper::arrayMergeRecursively($params, [
      'null_value' => false,
      'dumping_data' => false,
      'supported_extensions' => $this->app->getConfig('m_datapub/columns/image/supported_extensions', ['jpg', 'gif', 'png', 'jpeg', 'webp']),
      'escape_string' => $this->app->getConfig('m_datapub/escape_string', true),
    ]);

    if ($params['dumping_data']) {
      $sql = "$col_name='$value'";
    } else {
      if ($value == 'delete_image') {
        $sql = "$col_name=''";
      } else {
        $sql = "$col_name='".($params['escape_string'] ? $this->app->db->escape($value) : $value)."'";
      }
    }

    return $sql;
  }

  public function toHtml($value, $params = []) {
    $html = '';

    $value = htmlspecialchars($value);

    if ('' != $value && file_exists($this->app->configAsString('uploadDir')."/{$value}")) {
      $img_url = "{$this->app->configAsString('images_url')}/{$value}";
      $img_style = "style='height:30px;border:none'";

      $img_url = "{$this->app->configAsString('accountUrl')}/Image?f=".urlencode($value).'&cfg=wa_list&rand='.rand(1, 999999);
      $img_style = "style='border:none'";

      $pathinfo = pathinfo($value);
      $html = "<a href='{$this->app->configAsString('accountUrl')}/Image?f=".urlencode($value)."' target='_blank' onclick='event.cancelBubble=true;'><img src='{$img_url}' {$img_style} class='list_image'></a>";
      if ($params['display_basename']) {
        $html .= "<br/>{$pathinfo['basename']}";
      }
    }

    $html = "<div style='text-align:center'>{$html}</div>";

    return $html;
  }

  public function toCsv($value, $params = []) {
    return "{$this->app->configAsString('images_url')}/{$value}";
  }

  public function normalize(\ADIOS\Core\Model $model, string $colName, $value, $colDefinition)
  {
    if (!is_array($value) || empty($value['fileData']) || empty($value['fileName'])) return $value;

    $fileName = $value['fileName'];
    $fileData = preg_replace('/data:.*?,/', '', $value['fileData']);
    $fileData = @base64_decode($fileData);
    $folderPath = $colDefinition['folderPath'] ?? "";

    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (empty($model->app->configAsString('uploadDir'))) throw new \Exception("{$colDefinition['title']}: Upload folder is not configured.");
    if (!is_dir($model->app->configAsString('uploadDir'))) throw new \Exception("{$colDefinition['title']}: Upload folder does not exist.");
    if (in_array($fileExtension, ['php', 'sh', 'exe', 'bat', 'htm', 'html', 'htaccess'])) {
      throw new \Exception("{$colDefinition['title']}: This file type cannot be uploaded.");
    }

    if (strpos($folderPath, "..") !== false) throw new \Exception("{$colDefinition['title']}: Invalid upload folder path.");

    if (empty($colDefinition['renamePattern'])) {
      $tmpParts = pathinfo($fileName);
      $fileName = \ADIOS\Core\Helper::str2url($tmpParts['filename']) . '.' . $tmpParts['extension'];
    } else {
      $tmpParts = pathinfo($fileName);

      $fileName = $colDefinition['renamePattern'];
      $fileName = str_replace("{%Y%}", date("Y"), $fileName);
      $fileName = str_replace("{%M%}", date("m"), $fileName);
      $fileName = str_replace("{%D%}", date("d"), $fileName);
      $fileName = str_replace("{%H%}", date("H"), $fileName);
      $fileName = str_replace("{%I%}", date("i"), $fileName);
      $fileName = str_replace("{%S%}", date("s"), $fileName);
      $fileName = str_replace("{%TS%}", strtotime("now"), $fileName);
      $fileName = str_replace("{%RAND%}", rand(1000, 9999), $fileName);
      $fileName = str_replace("{%BASENAME%}", $tmpParts['basename'], $fileName);
      $fileName = str_replace("{%BASENAME_ASCII%}", \ADIOS\Core\Helper::str2url($tmpParts['basename']), $fileName);
      $fileName = str_replace("{%FILENAME%}", $tmpParts['filename'], $fileName);
      $fileName = str_replace("{%FILENAME_ASCII%}", \ADIOS\Core\Helper::str2url($tmpParts['filename']), $fileName);
      $fileName = str_replace("{%EXT%}", $tmpParts['extension'], $fileName);
    }


    if (empty($folderPath)) $folderPath = ".";

    $uploadDir = $model->app->configAsString('uploadDir');

    if (!is_dir("{$uploadDir}/{$folderPath}")) {
      mkdir("{$uploadDir}/{$folderPath}", 0775, TRUE);
    }

    $fileNameNoVersion = $fileName;

    $destinationFileNoVersion = "{$uploadDir}/{$folderPath}/{$fileName}";
    $destinationFile = $destinationFileNoVersion;

    $verCnt = 1;
    while (is_file($destinationFile)) {
      $tmpParts = pathinfo($destinationFileNoVersion);
      $destinationFile = $tmpParts['dirname'] . '/' . $tmpParts['filename'] . ' (' . $verCnt .').' . $tmpParts['extension'];

      $tmpParts = pathinfo($fileNameNoVersion);
      $fileName = $tmpParts['filename'] . ' (' . $verCnt .').' . $tmpParts['extension'];

      $verCnt++;
    }

    \file_put_contents($destinationFile, $fileData);

    return "{$folderPath}/{$fileName}";
  }
  
}
