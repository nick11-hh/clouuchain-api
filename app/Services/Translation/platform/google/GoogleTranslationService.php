<?php

namespace App\Services\Translation\platform\google;

use App\Services\Translation\TranslationAbstract;
use App\Services\Translation\TranslationInterface;
use Google\ApiCore\ApiException;
use Google\Cloud\Translate\V3\AdaptiveMtDataset;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use App\Services\Translation\Exceptions\TranslationException;
use Illuminate\Support\Arr;


class GoogleTranslationService extends TranslationAbstract implements TranslationInterface
{

    protected $client;
    protected $projectId;


    /**
     * 构造函数
     */
    public function __construct($config, $language)
    {
        parent::__construct($config, $language);
        $this->checkConfig($config);
        $this->client = new TranslationServiceClient();
        $this->projectId = $config['project_id'];

    }

    /**
     * 翻译
     * @param string|array $text
     * @param string $from
     * @param string $to
     * @return array
     */
    public function translation($text)
    {
        try {
            $contents = Arr::wrap($text);
            $formattedParent = $this->client->locationName($this->projectId, 'global');

            $request = (new TranslateTextRequest())
                ->setContents($contents)
                ->setTargetLanguageCode($this->language)
                ->setParent($formattedParent);

                
            /** @var AdaptiveMtDataset $response */
            $response = $this->client->translateText($request);

            $translations = [];
            foreach ($response->getTranslations() as $translation) {
                $translations[] = $translation->getTranslatedText();
            }
            return $translations;

        } catch (ApiException $ex) {

            throw new TranslationException($ex->getMessage());

        } finally {

            $this->client->close();

        }
    }


    /**
     * 检查配置
     * @param array $config
     * @return void
     */
    protected function checkConfig($config)
    {
        if (empty($config['project_id'])) {
            throw new TranslationException('Google Cloud Project ID is required');
        }
        if (empty($config['supported_languages'])) {
            throw new TranslationException('Supported languages are required');
        }
    }
}