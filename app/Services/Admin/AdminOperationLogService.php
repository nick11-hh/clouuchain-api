<?php

namespace App\Services\Admin;

use App\Models\AdminOperationLog;

class AdminOperationLogService extends BaseService
{
    public $filterRules = [
        'type'          => ['=', 'type'],
        'custom_id'     => ['=', 'custom_id'],
        'admin_id'      => ['=', 'admin_id'],
        'opt_type'      => ['=', 'opt_type'],
        'created_at'    => ['between', ['begin_date', 'end_date']],
    ];

    protected $orderBy = ['id' => 'desc'];

    public function __construct(AdminOperationLog $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

}
