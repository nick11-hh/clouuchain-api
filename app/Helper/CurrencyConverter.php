<?php

namespace App\Helper;

use App\Models\ExchangeRateModel;
use Carbon\Traits\Rounding;
use Illuminate\Support\Facades\Cache;
use Money\Converter;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\CurrencyPair;
use Money\Exchange\FixedExchange;
use Money\Exchange\ReversedCurrenciesExchange;
use Money\Money;
use Exception;

class CurrencyConverter
{
    public FixedExchange $exchangeRates;
    protected string $fromCurrency;
    protected string $toCurrency;
    public $rate = 0;

    public function __construct(string $toCurrency = 'CNY', $fromCurrency = 'USD', $exchangeRate = 0)
    {
        $this->fromCurrency = strtoupper($fromCurrency);
        $this->toCurrency = strtoupper($toCurrency);

        $this->toCurrency === 'USD' && $exchangeRate = 1;
        if(!$exchangeRate) {
            $exchangeRate = ExchangeRateModel::where('currency_code', $this->toCurrency)->value('custom_exchange_rate');
        }

        $this->rate = empty($exchangeRate) ? 1 : $exchangeRate;
        logger($toCurrency.'汇率：'.$exchangeRate);
        $this->exchangeRates = new FixedExchange([
            $fromCurrency => [
                $toCurrency => (string)$this->rate
            ]
        ]);
    }

    /**
     * 货币汇率转换 ps: USD => CNY
     * @param $amount
     * @return
     */
    public function convert($amount)
    {
        try {
            $converter = new Converter(new ISOCurrencies(), $this->exchangeRates);
            $amount *= 100;
            $fromCurrencyAmount = new Money($amount, new Currency($this->fromCurrency));
            $toCurrencyAmount = $converter->convert($fromCurrencyAmount, new Currency($this->toCurrency));

            // return number_format($toCurrencyAmount->getAmount() / 100, 2, '.', '');
            return sprintf("%.2f", $toCurrencyAmount->getAmount() / 100);
        } catch (Exception $e) {
            info('convert', [$e->getMessage(), $e->getFile(), $e->getLine()]);

            return 0;
        }
    }

    /**
     * 反向货币汇率转换 ps: CNY => USD
     * @param $amount
     * @return float|int
     */
    public function reversedCurrenciesExchange($amount)
    {
        try {
            $exchange = new ReversedCurrenciesExchange($this->exchangeRates);
            $converter = new Converter(new ISOCurrencies(), $exchange);

            $amount *= 100;
            $fromCurrencyAmount = new Money($amount, new Currency($this->toCurrency));
            $toCurrencyAmount = $converter->convert($fromCurrencyAmount, new Currency($this->fromCurrency), 3);
            // info('currencyAmount', [$toCurrencyAmount->getAmount()]);
            // return number_format($toCurrencyAmount->getAmount() / 100, 2, '.', '');
            return sprintf("%.2f", $toCurrencyAmount->getAmount() / 100);
        } catch (Exception $e) {
            info('reversedCurrenciesExchange', [$e->getMessage(), $e->getFile(), $e->getLine()]);

            return 0;
        }
    }
}
