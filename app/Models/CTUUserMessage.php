<?php

namespace App\Models;

use App\Models\Traits\Basis;

class CTUUserMessage extends Model
{
    use Basis;

    protected $table = 'dsp_ctu_user_messages';

    protected $guarded = [];

    protected $hidden = [];

    protected $casts = [];

    protected $appends = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function ctuMessage()
    {
        return $this->belongsTo(CTUMessage::class, 'message_id', 'id');
    }

    public static function getMessagesNum()
    {
        return self::query()->where(['user_id' => getUserId(), 'is_read' => 0])->count();
    }
}
