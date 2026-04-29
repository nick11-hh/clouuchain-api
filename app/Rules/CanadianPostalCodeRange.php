<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Str;

/**
 * 校验加拿大邮编范围
 * Class CanadianPostalCodeRange
 * @package App\Rules
 * @author DonnyLiu <2365057581@qq.com>
 * @date 2024/12/16 17:18
 */
class CanadianPostalCodeRange implements Rule
{
    protected string $start;

    protected string $end;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($start, $end)
    {
        $this->start = Str::upper(str_replace(' ', '', $start));
        $this->end = Str::upper(str_replace(' ', '', $end));
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $input = Str::upper(str_replace(' ', '', $value));

        return $this->convertToNumeric($input) >= $this->convertToNumeric($this->start) &&
            $this->convertToNumeric($input) <= $this->convertToNumeric($this->end);
    }


    /**
     * Converts a postal code string into a numeric value for comparison.
     *
     * @param string $postalCode The postal code to convert.
     * @return int The numeric representation of the postal code.
     */
    private function convertToNumeric($postalCode)
    {
        $numericValue = 0;
        foreach (str_split($postalCode) as $char) {
            if (ctype_digit($char)) {
                $numericValue = $numericValue * 36 + intval($char);
            } else {
                $numericValue = $numericValue * 36 + ord($char) - ord('A') + 10;
            }
        }
        return $numericValue;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The :attribute must be within the range '. $this->start .' to '. $this->end .'.';
    }
}
