<?php

namespace App\Jobs;

use App\Http\Traits\SqlLog;
use App\Models\Model;
use App\Models\SuperAdminStringTranslation;
use App\Services\C2TTranslate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;
use Stichoza\GoogleTranslate\GoogleTranslate;

class ConvertLanguage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SqlLog;

    protected $company_ids;
    protected $code;
    protected $originStr = '';
    protected $translatedArr = [];

    protected $ignoreTranslate = [];

    protected $timesCnt = 0;

    /**
     * @var array
     * @description 语言编码映射
     */
    protected $codeMap = [];

    /**
     * @var GoogleTranslate
     */
    protected $tr;

    protected $forceUpdate;

    protected $targetClass;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($companyIDs, $code = 'zh_TW', $forceUpdate = false, $targetClass = null)
    {
        $this->company_ids = $companyIDs;
        $this->code = $code;
        $this->tr = new GoogleTranslate();
        $this->codeMap = self::getLanguageMapFromJson();
        $this->targetClass = $targetClass;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->code === 'zh_TW') {
            $this->translate();
            return;
        }
        $this->translate(false);
        $this->translate(true, $this->forceUpdate);
    }

    /**
     * 字段意义为是否需要更新,是否强制更新
     */
    public function translate($shouldUpdate = true, $forceUpdate = false)
    {
        try {
            foreach ($this->getTranslateMap() as $shouldConvert) {
                try {
                    info('当前在查询' . $shouldConvert . '类下的所有翻译属性,当前传入的公司数组为:' . json_encode($this->company_ids));

                    /** @var Model $shouldConvert */
                    $shouldConvert = (new $shouldConvert());

                    if ($shouldConvert instanceof SuperAdminStringTranslation) {
                        //这里是超管端的全局表 -- 字符串翻译
                        app('log')->channel('single')->debug('当前更新超管端的表');
                        $data = $shouldConvert::query()->get();
                    } elseif (in_array('company_id', Schema::getColumnListing($shouldConvert->getTable()))
                        && ($this->company_ids && count($this->company_ids))
                    ) {
                        $data = $shouldConvert::query()->whereIn('company_id', $this->company_ids)->get();
                    } else {
                        continue;
                    }

                    info('当前需要更新的数据数量为:' . $data->count());

                    $this->batchTranslate($data, $this->code, $shouldUpdate, $forceUpdate);
                    //需要同步更新的情况
                    if ($shouldUpdate) {
                        DB::beginTransaction();
                        $res = $shouldConvert->updateBatch($data);
                        app('log')->debug('批量更新的结果为:' . $res);
                        DB::commit();
                    }
                } catch (\Exception $e) {
                    app('log')->debug('当前遍历出错,继续' . $shouldConvert::class . $e->getMessage());
                    continue;
                }
            }
            //对于翻译动作,最后再更新一次
            if (!$shouldUpdate) {
                $this->timesCnt++;
                $this->googleTranslate($this->codeMap['zh_CN'], $this->codeMap[$this->code]);
                $this->originStr = ''; // 清零
                app('log')->debug('本轮翻译进行了' . $this->timesCnt . '次');
            }
        } catch (\Exception $e) {
            app('log')->debug('批量更新语言出错,错误原因为:' . $e->getMessage());
            DB::rollBack();
        }
        return;
    }

    /**
     * @return array|string[]
     */
    public function getTranslateMap()
    {
        if ($this->targetClass) {
            return ["App\\Models\\$this->targetClass"];
        }

        return $this->analysisModels();
    }

    public function batchTranslate(Collection $datas, $code, $shouldUpdate = true, $forceUpdate = false)
    {
        foreach ($datas as $key => $data) {
            if (is_array($data->translatable) && count($data->translatable)) {
                if ($shouldUpdate) {
                    //更新动作下修改对象的对应值
                    foreach ($data->translatable as $key => $column) {
                        if (in_array($column, $this->ignoreTranslate)) {
                            continue;
                        }

                        if (!$forceUpdate && $data->hasTranslation($column, $code)) {
                            continue;
                        }
                        // if (get_class($data) === 'App\Models\SuperAdminStringTranslation') {
                        //     dd($this->getTranslate($data->getTranslation($column, 'zh_CN'), $code));
                        // } else {
                        //     continue;
                        // }
                        $data->setTranslation($column, $code, $this->getTranslate($data->getTranslation($column, 'zh_CN'), $code));
                    }
                } else {
                    //翻译动作下翻译结果并放入临时数组
                    //google 翻译不能超过 5000 字符
                    //html_entity_decode google 翻译 html 会存在问题. 所以 html 翻译不应该采用机器翻译
                    //https://github.com/Stichoza/google-translate-php/issues/119
                    foreach ($data->translatable as $key => $column) {
                        if (strlen($this->originStr) >= 4000) {
                            //翻译字符串并放入已翻译数组
                            $this->timesCnt++;
                            $this->googleTranslate($this->codeMap['zh_CN'], $this->codeMap[$code]);
                            //翻译过,清零,加上当前的字段
                            $this->originStr = html_entity_decode($data->getTranslation($column, 'zh_CN'));
                        } else {
                            $this->originStr .= "\n" . html_entity_decode($data->getTranslation($column, 'zh_CN'));
                        }
                    }
                }
            }
        }
    }

    /**
     * 调用googleapi 翻译字符串并
     */
    public function googleTranslate($source, $target, $str = null)
    {
        $this->tr->setSource($source);
        $this->tr->setTarget($target);
        $this->tr->setUrl('http://translate.google.cn/translate_a/single');

        try {
            $translatedStr = $this->tr->translate($str ?? $this->originStr);
            app('log')->debug('翻译前' . $this->originStr);
            app('log')->debug('翻译后' . $translatedStr);
            $originArr = explode("\n", trim($this->originStr, "\n"));
            $translatedArr = explode("\n", $translatedStr);
            foreach ($originArr as $key => $value) {
                $this->translatedArr[$value] = $translatedArr[$key];
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * 获取翻译结果
     */
    public function getTranslate(string $key, $languageCode = 'zh_TW'): string
    {
        if ($languageCode === 'zh_TW') {
            return $this->getTWTranslate($key);
        }

        //这里应该调用外部接口返回值
        return $this->translatedArr[$key] ?? $key;
    }

    /**
     * 繁体的翻译结果
     */
    public function getTWTranslate(string $key): string
    {
        return (new C2TTranslate())->c2t($key);
    }

    /**
     * 判断并取到所有定义了 translatable 属性的 model
     */
    public function analysisModels(): array
    {
        $path = app_path() . '/Models';
        $fqcns = [];

        $allFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        $phpFiles = new RegexIterator($allFiles, '/\.php$/');
        foreach ($phpFiles as $phpFile) {
            $content = file_get_contents($phpFile->getRealPath());
            $tokens = token_get_all($content);
            $namespace = '';
            for ($index = 0; isset($tokens[$index]); $index++) {
                if (!isset($tokens[$index][0])) {
                    continue;
                }
                if ($tokens[$index][0] === T_NAMESPACE) {
                    $index += 2; // Skip namespace keyword and whitespace
                    while (isset($tokens[$index]) && is_array($tokens[$index])) {
                        $namespace .= $tokens[$index][1];
                        $index++;
                    }
                }
                if ($tokens[$index][0] === T_CLASS && $tokens[$index + 1][0] === T_WHITESPACE && $tokens[$index + 2][0] === T_STRING) {
                    $index += 2; // Skip class keyword and whitespace
                    $class = $namespace . '\\' . $tokens[$index][1];

                    if (property_exists($class, 'translatable') && !$this->targetClass) {
                        $fqcns[] = $class;
                    } elseif ($this->targetClass === $class) {
                        $fqcns[] = $class;
                    }

                    # break if you have one class per file (psr-4 compliant)
                    # otherwise you'll need to handle class constants (Foo::class)
                    break;
                }
            }
        }
        return $fqcns;
    }

    /**
     * 从 json 中获取语言编码映射
     */
    public static function getLanguageMapFromJson()
    {
        $path = base_path() . '/public/language-code.json';

        // 从文件中读取数据到PHP变量
        $json_string = file_get_contents($path);

        // 用参数true把JSON字符串强制转成PHP数组
        $data = json_decode($json_string, true);
        $result = [];
        foreach ($data as $key => $value) {
            $result[$value['language_code']] = $value['language'];
        }

        return $result;
    }

    /**
     * @return array
     */
    public function getCodeMap(): array
    {
        return $this->codeMap;
    }
}
