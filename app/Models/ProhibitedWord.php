<?php

namespace App\Models;

use App\Models\Traits\Basis;
use Illuminate\Support\Str;

class ProhibitedWord extends Model
{
    use Basis;

    public const MATCH_TYPE_ALL = 0; // 全匹配
    public const MATCH_TYPE_PART = 1; // 部分匹配

    protected $table = 'dsp_prohibited_words';

    protected $guarded = [];

    protected $hidden = [];

    protected $appends = [];

    protected $fillable = [];

    /**
     * @param string $content
     * @return array|true
     */
    public static function check(string $content)
    {
        /** @var self|null $config */
        $config = self::query()->first();

        if (! $config) {
            return true;
        }

        $words = Str::of($config->prohibited_words)->explode(',');

        if ($words->isEmpty()) {
            return true;
        }

        $pWords = [];
        if ($config->match_type === self::MATCH_TYPE_ALL) {
            foreach ($words as $word) {
                if ($content === $word) {
                    $pWords[] = $word;
                }
            }
        } else {
            foreach ($words as $word) {
                if (Str::contains($content, $word)) {
                    $pWords[] = $word;
                }
            }
        }

        return $pWords;
    }
}
