<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseAccountModel extends Model
{
    use HasFactory;

    protected $table = 'dsp_purchase_account';

    public static function init($data): array
    {
        return [
            'account_name' => $data['account_name'],
            'platform'     => $data['platform'],
            'name'         => $data['name'],
            'remark'       => $data['remark'] ?? '',
        ];
    }

}
