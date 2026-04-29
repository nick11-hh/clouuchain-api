<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CreditCardTypes extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;


    public const STATUS_NORMAL = 1;
    public const STATUS_DISABLE = 2;

    //  40Seas平台
    const TYPE_40SEAS = '40Seas';


    protected $table = 'credit_card_types';

}
