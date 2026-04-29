<?php

/**
 * Created by PhpStorm.
 * User: lin
 * Date: 2019-05-13
 * Time: 18:36
 */

namespace App\Models\Traits;

use App\Models\AdminLanguages;

trait AdminLanguageCache
{
    public static function buildLanguageCache($companyID)
    {
        $languages = AdminLanguages::where('company_id', $companyID)->get();
        app('reis')->set('adminlanguagecache'.$companyID, $languages);

        return $languages;
    }

    public static function getLanguageCache($companyID)
    {
        $res = app('reis')->get('adminlanguagecache'.$companyID);
        if ($res) {
            return $res;
        }

        return self::buildLanguageCache($companyID);
    }
}
