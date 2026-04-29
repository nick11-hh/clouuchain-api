<?php

namespace App\Models;

use App\Models\Traits\AdminLanguageCache;
use App\Models\Traits\Basis;
use App\Models\Traits\CustomHasTranslations;
use setasign\Fpdi\PdfParser\Type\PdfStream;

class AdminLanguages extends Model
{
    use Basis,
        AdminLanguageCache,
        CustomHasTranslations;

    public $translatable = ['name'];

    protected $table = 'dsp_admin_languages';

    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if ($model->company_id ?? null) {
                self::buildLanguageCache($model->company_id);
            }
        });
    }

    public static function excelCountryCodes()
    {
        return [
            'country（optional）' => 'en_US',
            '국가.（선택 사항）' => 'ko_KR',
            '国（オプション）' => 'ja',
            'País（Opcional）' => 'es_MX',
            'страны（Дополнительно）' => 'ru_RU',
            'ประเทศชาติ（ทางเลือก）' => 'th',
            'Paese（Facoltativo）' => 'it_IT',
            'ұлт（Қосымша）' => 'kz',
        ];
    }

    public static function excelAreaCountryCodes()
    {
        return [
            'region（optional）' => 'en_US',
            '구역（선택 사항）' => 'ko_KR',
            '領域（オプション）' => 'ja',
            'Región（Opcional）' => 'es_MX',
            'область（Дополнительно）' => 'ru_RU',
            'แว่นแคว้น（ภูมิภาคย่อย）' => 'th',
            'regione（Facoltativo）' => 'it_IT',
            'аумақ（Қосымша）' => 'kz',
        ];
    }

    public static function excelSubAreaCountryCodes()
    {
        return [
            'sub region（optional）' => 'en_US',
            '하위 영역（선택 사항）' => 'ko_KR',
            'サブエリア（オプション）'=> 'ja',
            'Subregión（Opcional）' => 'es_MX',
            'подпространство（Дополнительно）' => 'ru_RU',
            'ภูมิภาคย่อย（ทางเลือก）' => 'th',
            'Regione subregionale（Facoltativo）'=> 'it_IT',
            'Қосалқы аймақ（Қосымша）' => 'kz',
        ];
    }

    public static function excelLowAreaCountryCodes()
    {
        return [
            'low region（optional）' => 'en_US',
            '낮은 지역(선택 사항)' => 'ko_KR',
            '低域（オプション）'=> 'ja',
            'región baja (opcional)' => 'es_MX',
            'низкий регион (опционально)' => 'ru_RU',
            'ภูมิภาคต่ำ (ไม่บังคับ)' => 'th',
            'regione bassa (opzionale)'=> 'it_IT',
            'төмен аймақ (міндетті емес)' => 'kz',
        ];
    }
}
