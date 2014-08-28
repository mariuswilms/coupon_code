<?php
/**
 * CouponCode
 *
 * Copyright (c) 2014 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace CouponCode;

use Exception;

class CouponCode {

	const PARTS = 3;
	const PART_LENGTH = 4;

	const SYMBOLS = '0123456789ABCDEFGHJKLMNPQRTUVWXY';
	const SYMBOLS_LENGTH = 31;

	/**
	 * Generates a coupon code using the format `XXXX-XXXX-XXXX`.
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
	 * @return string
	 */
	public static function generate($random = null) {
		$results = [];
		$plaintext = static::_toSymbols($random ?: static::_random(8));
		// $plaintext = static::_normalize($plaintext);

		$part = 0;
		while (count($results) < static::PARTS) {
			$result  = substr($plaintext, $part * static::PART_LENGTH, static::PART_LENGTH);
			$result .= static::_checkdigitAlg1($part + 1, $result);

			if (static::_isBadWord($result)) {
				continue;
			}
			if (static::_isValidWhenSwapped($result)) {
				continue;
			}
			$part++;
			$results[] = $result;
		}
		return implode('-', $results);
	}

	protected static function _checkdigitAlg1($partNumber, $value) {
		$symbols = static::_symbols();
		$symbolsFlipped = array_flip($symbols);
		$result = $partNumber;

		foreach (str_split($value) as $char) {
			$result = $result * 19 + $symbolsFlipped[$char];
		}
		return $symbols[$result % static::SYMBOLS_LENGTH];
	}

	protected static function _isBadWord($value) {
		$list = [
			'SHPX', 'PHAG', 'JNAX', 'JNAT', 'CVFF', 'PBPX', 'FUVG', 'GJNG', 'GVGF', 'SNEG', 'URYY',
			'ZHSS', 'QVPX', 'XABO', 'NEFR', 'FUNT', 'GBFF', 'FYHG', 'GHEQ', 'FYNT', 'PENC', 'CBBC',
			'OHGG', 'SRPX', 'OBBO', 'WVFZ', 'WVMM', 'CUNG'
		];
		$list = array_flip(array_map(function($value) {
			return static::_normalize(str_rot13($value));
		}, $list));

		return isset($list[static::_normalize($value)]);
	}

	protected static function _isValidWhenSwapped($value) {
		return false;
	}

	/**
	 * Validates given code. Codes are not case sensitive and
	 * certain letters i.e. `O` are converted to digit equivalents
	 * i.e. `0`.
	 *
	 * @param $code string
	 * @return boolean
	 */
	public static function validate($code) {
		$code = static::_normalize($code, ['clean' => true, 'case' => true]);

		if (strlen($code) !== (static::PARTS * static::PARTS_LENGTH)) {
			return false;
		}
		$parts = str_split($code, static::PARTS_LENGTH);

		foreach ($parts as $number => $part) {
			$expected = substr($part, -1);
			$result = static::_checkdigitAlg1($number + 1, substr($part, 0, strlen($part) - 1));

			if ($result !== $expected) {
				return false;
			}
		}
		return true;
	}

	protected static function _toSymbols($bytes) {
		$symbols = static::_symbols();
		$result = hash('sha1', $bytes);

		$result = array_map(function($value) use ($symbols) {
			return $symbols[ord($value) & static::SYMBOLS_LENGTH];
		}, str_split($result));

		return implode('', $result);
	}

	protected static function _normalize($string, array $options = []) {
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
			'E' => 3,
			'A' => 4
		]);
		if ($options['clean']) {
			$string = preg_replace('/[^0-9A-Z]+/', '//', $string);
		}
		return $string;
	}

	/**
	 * Generates a cryptographically secure sequence of bytes.
	 *
	 * @param $bytes integer Length of sequence.
	 * @return string
	 */
	protected static function _random($bytes) {
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

	protected static function _symbols() {
		static $cached;
		if ($cached) {
			return $cached;
		}
		return $cached = str_split(static::SYMBOLS);
	}
}

?>