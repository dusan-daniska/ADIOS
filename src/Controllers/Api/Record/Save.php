<?php

namespace ADIOS\Controllers\Api\Record;

class Save extends \ADIOS\Core\ApiController {
  protected ?\Illuminate\Database\Eloquent\Builder $query = null;

  public \ADIOS\Core\Model $model;

  function __construct(\ADIOS\Core\Loader $app, array $params = []) {
    parent::__construct($app, $params);
    $this->permission = $this->params['model'] . ':Create';
    $this->model = $this->app->getModel($this->params['model']);
  }

  public function recordSave(
    string $modelClass,
    array $data,
    int $idMasterRecord = 0
  ): array {
    $savedRecord = [];

    if (empty($modelClass)) throw new \Exception("Master model is not specified.");
    $model = $this->app->getModel($modelClass);
    if (!is_object($model)) throw new \Exception("Unable to create model {$model}.");

    if ($idMasterRecord == 0) $pdo = $model->eloquent->getConnection()->getPdo();
    else $pdo = null;

    if ($pdo) $pdo->beginTransaction();

    $dataToSave = $data;

    try {

      foreach ($dataToSave as $key => $value) {
        if ($value['_useMasterRecordId_'] ?? false) {
          $dataToSave[$key] = $idMasterRecord;
        }
      }

      if ($dataToSave['_toBeDeleted_']) {
        $model->recordDelete((int) $dataToSave['id']);
        $savedRecord = [];
      } else {
        $idMasterRecord = $model->recordSave($dataToSave);

        if ($idMasterRecord > 0) {
          $savedRecord = $model->recordGet(
            function($q) use ($model, $idMasterRecord) { $q->where($model->table . '.id', $idMasterRecord); },
            $this->app->params['includeRelations'] ?? null,
            (int) ($this->app->params['maxRelationLevel'] ?? 1)
          );
        }
      }

      foreach ($model->relations as $relName => $relDefinition) {
        if (is_array($data[$relName])) {
          list($relType, $relModel) = $relDefinition;
          switch ($relType) {
            case \ADIOS\Core\Model::HAS_MANY:
              foreach ($data[$relName] as $subKey => $subRecord) {
                $savedRecord[$relName][$subKey] = $this->recordSave(
                  $relModel,
                  $subRecord,
                  $idMasterRecord
                );
              }
            break;
            case \ADIOS\Core\Model::HAS_ONE:
              $savedRecord[$relName] = $this->recordSave(
                $relModel,
                $data[$relName],
                $idMasterRecord
              );
            break;
          }
        }
      }

      if ($pdo) $pdo->commit();
    } catch (\Exception $e) {
      $exceptionClass = get_class($e);
      if ($pdo) $pdo->rollBack();

      switch ($exceptionClass) {
        case 'Illuminate\\Database\\QueryException':
          throw new $exceptionClass($e->getConnectionName(), $e->getSql(), $e->getBindings(), $e);
        break;
        case 'Illuminate\\Database\\UniqueConstraintViolationException';
          throw new \ADIOS\Core\Exceptions\RecordSaveException(
            $e->errorInfo[2],
            $e->errorInfo[1]
          );
        break;
        default:
          throw new $exceptionClass($e->getMessage(), $e->getCode(), $e);
        break;
      }
    }

    return $savedRecord;
  }

  public function response(): array
  {
    $originalRecord = $this->params['record'] ?? [];
    $model = $this->params['model'] ?? '';

    $decryptedRecord = $this->model->recordDecryptIds($originalRecord);

    $savedRecord = $this->recordSave($model, $decryptedRecord);

    return [
      'status' => 'success',
      'originalRecord' => $originalRecord,
      'savedRecord' => $savedRecord,
    ];
  }

}
