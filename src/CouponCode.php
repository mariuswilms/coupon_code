<?php
/**
 * CouponCode
 * Copyright (c) 2014 Atelier Disko. All rights reserved.
 *
 * Modified by Alex Rabinovich
 * 
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace CouponCode;

use Exception;

class CouponCode
{
    /**
     * The prefix to be added to every CouponCode.
     *
     * @var string
     */
    protected $_prefix = '';

    /**
     * The separator to be used in the CouponCode.
     *
     * @var string
     */
    protected $_separator = '-';

    /**
     * Number of parts of the code.
     *
     * @var integer
     */
    protected $_parts = 2;

    /**
     * Length of each part.
     *
     * @var integer
     */
    protected $_partLength = 4;

    /**
     * Alphabet used when generating codes. Already leaves
     * easy to confuse letters out.
     *
     * @var array
     */
    protected $_symbols = [
        '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
        'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'J', 'K',
        'L', 'M', 'N', 'P', 'Q', 'R', 'T', 'U', 'V', 'W',
        'X', 'Y'
    ];

    /**
     * ROT13 encoded list of bad words.
     *
     * @var array
     */
    protected $_badWords = [
        '0TER', 'AHEQ', 'BTER', 'C00C', 'C0EA', 'CBBC', 'CBEA', 'CEVPX', 'CRAVF', 'CUNG', 'CVFF', 'CVT', 'DHRRE',
        'ENG', 'FA0O', 'FABO', 'FCREZ', 'FUNT', 'FUVG', 'FYHG', 'FYNT', 'G0FF', 'GBFF', 'GHEQ', 'GJNG', 'GVGF',
        'J0EZ', 'JBEZ', 'JNAT', 'JNAX', 'LNX', 'NCR', 'NEFR', 'NFF', 'NVQF', 'O00MR', 'O00O', 'O00OL', 'O0M0',
        'OBBMR', 'OBBO', 'OBBOL', 'OBMB', 'OHGG', 'OHZ', 'ONYYF', 'ORNFG', 'OVGPU', 'P0J', 'P0PX', 'PBJ', 'PBPX',
        'PENC', 'PERRC', 'PHAG', 'PY0JA', 'PYBJA', 'PYVG', 'QRIVY', 'QVPX', 'SERNX', 'SHPX', 'SNEG', 'SNPX', 'SNG',
        'SNGF0', 'SNGFB', 'SRPX', 'TU0FG', 'TUBFG', 'U0Z0', 'UBZB', 'URYY', 'VQV0G', 'VQVBG', 'W0XR', 'W0XRE', 'W1MM',
        'WBXR', 'WBXRE', 'WREX', 'WVFZ', 'WVMM', 'XA0O', 'XABO', 'YVNE', 'ZHSS'
    ];

    /**
     * Constructor.
     *
     * @param array $config Available options are `prefix`, `separator`, `parts` and `partLength`.
     */
    public function __construct(array $config = [])
    {
        $config += [
            'prefix' => null,
            'separator' => null,
            'parts' => null,
            'partLength' => null
        ];

        if (isset($config['prefix'])) {
            $this->_prefix = $config['prefix'];
        }

        if (isset($config['separator'])) {
            $this->_separator = $config['separator'];
        }

        if (isset($config['parts'])) {
            $this->_parts = $config['parts'];
        }

        if (isset($config['partLength'])) {
            $this->_partLength = $config['partLength'];
        }
    }

    /**
     * Generates a coupon code using the format `XXXX-XXXX` by default.
     *
     * The 4th character of each part is a checkdigit.
     *
     * Not all letters and numbers are used, so if a person enters the letter 'O' we
     * can automatically correct it to the digit '0' (similarly for I => 1, S => 5, Z
     * => 2).
     *
     * The code generation algorithm avoids 'undesirable' codes. For example any code
     * in which transposed characters happen to result in a valid checkdigit will be
     * skipped.  Any generated part which happens to spell an 'inappropriate' 4-letter
     * word (e.g.: 'P00P') will also be skipped.
     *
     * @param string $random Allows to directly support a plaintext i.e. for testing.
     * @return string Dash separated and normalized code.
     * @throws Exception
     */
    public function generate($random = null)
    {
        $results = [];
        $plaintext = $this->_convert($random ?: $this->_random(8));
        // String is already normalized by used alphabet.
        $part = $try = 0;

        while (count($results) < $this->_parts) {
            $result = substr($plaintext, $try * $this->_partLength, $this->_partLength - 1);

            if (!$result || strlen($result) !== $this->_partLength - 1) {
                throw new Exception('Ran out of plaintext.');
            }

            $result .= $this->_checkdigitAlg1($part + 1, $result);
            $try++;

            if ($this->_isBadWord($result)) {
                continue;
            }

            $part++;
            $results[] = $result;
        }

        if (!empty($this->_prefix)) {

            return $this->_codeWithPrefix(implode($this->_separator, $results));
        }

        return implode($this->_separator, $results);
    }


    /**
     * Generates an array of coupon codes.
     * 
     * @param int $maxNumberOfCoupons
     * @param null|string $random
     * @return array
     */
    public function generateCoupons($maxNumberOfCoupons = 1, $random = null)
    {
        $coupons = [];
        for ($i = 0; $i < $maxNumberOfCoupons; $i++) {
            $temp = $this->generate($random);
            $coupons[] = $temp;
        }

        return $coupons;
    }

    /**
     * Validates given code. Codes are not case sensitive and
     * certain letters i.e. `O` are converted to digit equivalents
     * i.e. `0`.
     *
     * @param $code string Potentially unnormalized code.
     * @return boolean
     */
    public function validate($code)
    {
        if (!empty($this->_prefix) && substr_count($code, $this->_separator) > ($this->_parts - 1)) {
            //if 'true' there must be a prefix to the code, so we'll remove it to validate.
            $codeParts = explode($this->_separator, $code);

            if (strlen($codeParts[0]) > strlen($this->_prefix) && $codeParts[0] !== $this->_prefix) {
                return false;
            } else {
                unset($codeParts[0]);
                $code = implode($this->_separator, $codeParts);
            }
        }

        $code = $this->_normalize($code, ['clean' => true, 'case' => true]);

        if (strlen($code) !== ($this->_parts * $this->_partLength)) {
            return false;
        }

        $parts = str_split($code, $this->_partLength);

        foreach ($parts as $number => $part) {
            $expected = substr($part, -1);
            $result = $this->_checkdigitAlg1($number + 1, $x = substr($part, 0, strlen($part) - 1));
            if ($result !== $expected) {
                return false;
            }
        }

        return true;
    }

    /**
     * Implements the checkdigit algorithm #1 as used by the original library.
     *
     * @param integer $partNumber Number of the part.
     * @param string $value Actual part without the checkdigit.
     * @return string The checkdigit symbol.
     */
    protected function _checkdigitAlg1($partNumber, $value)
    {
        $symbolsFlipped = array_flip($this->_symbols);
        $result = $partNumber;

        foreach (str_split($value) as $char) {
            $result = $result * 19 + $symbolsFlipped[$char];
        }

        return $this->_symbols[$result % (count($this->_symbols) - 1)];
    }

    /**
     * Verifies that a given value is a bad word.
     *
     * @param string $value
     * @return boolean
     */
    protected function _isBadWord($value)
    {
        return isset($this->_badWords[str_rot13($value)]);
    }

    /**
     * Normalizes a given code using dash separators.
     *
     * @param string $string
     * @param array $options
     * @return string
     */
    public function normalize($string, array $options = [])
    {
        $options += [
            'checkPrefix' => true
        ];

        if ($options['checkPrefix'] && !empty($this->_prefix)) {
            $codeParts = explode($this->_separator, $string);
            $currentPrefix = $codeParts[0];

            if ($currentPrefix !== $this->_prefix) {
                $string = $this->_normalize($string, ['clean' => true, 'case' => true]);
            } else {
                $string = $this->_normalize(str_replace($currentPrefix, '', $string), ['clean' => true, 'case' => true]);

                return $this->_codeWithPrefix(implode($this->_separator, str_split($string, $this->_partLength)));
            }
        } else {
            $string = $this->_normalize($string, ['clean' => true, 'case' => true]);
        }

        return implode($this->_separator, str_split($string, $this->_partLength));
    }

    /**
     * Converts givens string using symbols.
     *
     * @param string $string
     * @return string
     */
    protected function _convert($string)
    {
        $symbols = $this->_symbols;
        $result = array_map(function ($value) use ($symbols) {
            return $symbols[ord($value) & (count($symbols) - 1)];
        }, str_split(hash('sha1', $string)));
        return implode('', $result);
    }

    /**
     * Internal method to normalize given strings.
     *
     * @param string $string
     * @param array $options
     * @return string
     */
    protected function _normalize($string, array $options = [])
    {
        $options += [
            'clean' => false,
            'case' => false
        ];

        if ($options['case']) {
            $string = strtoupper($string);
        }

        $string = strtr($string, [
            'I' => 1,
            'O' => 0,
            'S' => 5,
            'Z' => 2,
        ]);

        if ($options['clean']) {
            $string = preg_replace('/[^0-9A-Z]+/', '', $string);
        }

        return $string;
    }

    /**
     * Generates a cryptographically secure sequence of bytes.
     *
     * @param integer $bytes Number of bytes to return.
     * @return string
     * @throws Exception
     */
    protected function _random($bytes)
    {
        if (is_readable('/dev/urandom')) {
            $stream = fopen('/dev/urandom', 'rb');
            $result = fread($stream, $bytes);
            fclose($stream);
            return $result;
        }

        if (function_exists('mcrypt_create_iv')) {
            return mcrypt_create_iv($bytes, MCRYPT_DEV_RANDOM);
        }

        throw new Exception("No source for generating a cryptographically secure seed found.");
    }

    private function _codeWithPrefix($code)
    {
        return strtoupper($this->_prefix) . $this->_separator . $code;
    }
}
?>
