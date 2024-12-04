<?php

namespace ADIOS\Controllers\Api\Record;

class Get extends \ADIOS\Core\ApiController {
  protected ?\Illuminate\Database\Eloquent\Builder $query = null;

  public \ADIOS\Core\Model $model;

  function __construct(\ADIOS\Core\Loader $app, array $params = [])
  {
    parent::__construct($app, $params);
    $this->permission = $this->app->params['model'] . ':Read';
    $this->model = $this->app->getModel($this->app->params['model']);
  }

  public function response(): array
  {
    $idEncrypted = $this->app->params['id'] ?? '';
    $id = (int) \ADIOS\Core\Helper::decrypt($idEncrypted);

    if ($id <= 0) {
      $record = $this->model->recordDefaultValues();
    } else {
      $record = $this->model->recordGet(
        function($q) use ($id) { $q->where($this->model->table . '.id', $id); },
        $this->app->params['includeRelations'] ?? null,
        (int) ($this->app->params['maxRelationLevel'] ?? 1)
      );
    }

    return $record;
  }

}
