<?php

namespace App\Services\Admin;


use App\Exceptions\AccidentException;
use App\Lib\Code;
use App\Models\Custom;
use App\Models\MailSmtpConfig;
use App\Services\ApiResponseService;
use App\Services\ExpressCompanies\KuaiDi\Exceptions\Exception;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\throwException;

class EmailService extends BaseService
{

    public function __construct(MailSmtpConfig $model)
    {
        $this->model = $model;
        $this->query = $model->newQuery();
        $this->formData = request()->all();
        $this->setFilterRules();
    }

    public function getEmailSmtpConfig()
    {
        return $this->model->first();

    }

    /**
     * @param array $data
     * @return bool
     */
    public function updateSmtpConfig(array $data)
    {
        return DB::transaction(function () use ($data) {
            $emailData = $this->model->first();
            MailSmtpConfig::query()->updateOrCreate(
                ['id' => $emailData->id ?? 0],
                [
                    'host' => $data['host'] ?? '',
                    'port' => $data['port'] ?? '',
                    'encryption' => $this->setEncryption($data['encryption'] ?? null),
                    'username' => $data['username'] ?? '',
                    'password' => $data['password'] ?? '',
                    'from_address' => $data['from_address'] ?? '',
                    'from_name' => $data['from_name'] ?? '',
                ]
            );

            return true;
        });
    }

    /**
     * @param $encryption
     * @return string|null
     */
    protected function setEncryption($encryption)
    {
        if ($encryption) {
            if ((int)$encryption === 1) {
                return 'tls';
            }
            if ((int)$encryption === 2) {
                return 'ssl';
            }
        }
        return null;
    }

    /**
     * @param array $data
     * @return mixed
     * @throws AccidentException
     */
    public function verifySmtpConfig(array $data)
    {
        validator($data, $this->verifyRules())->validate();

        $transport = new MailManager(app());
        $data['encryption'] = $this->setEncryption($data['encryption'] ?? null);
        /** @var \Symfony\Component\Mailer\Transport\Smtp\SmtpTransport $driver */
        $data['transport'] = 'smtp';
        unset($data['uuid']);
        $driver = $transport->createSymfonyTransport($data);
        try {
            $driver->start();

            $driver->executeCommand("NOOP\r\n", [250]);
        } catch (\Throwable $th) {
            app('log')->debug('验证配置失败,原因为:' . $th->getMessage());
            throw new AccidentException($th->getMessage());
        }

        return true;
    }

    private function verifyRules()
    {
        return [
            'host' => 'required|nullable|string|max:100',
            'port' => 'required|nullable|integer',
            'encryption' => 'nullable',
            'username' => 'required|nullable',
            'password' => 'required|nullable',
            'from_address' => 'required|nullable',
            'from_name' => 'required|nullable',
        ];
    }

}
