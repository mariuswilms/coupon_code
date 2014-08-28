<?php
/**
 * CouponCode
 *
 * Copyright (c) 2014 Atelier Disko. All rights reserved.
 *
 * Use of this source code is governed by a BSD-style
 * license that can be found in the LICENSE file.
 */

namespace CouponCode\tests;

require_once __DIR__ . '/../src/CouponCode.php';

use CouponCode\CouponCode;

class CouponCodeTest extends \PHPUnit_Framework_TestCase {

	public function testGenerateUsingSeed() {
		$result0 = CouponCode::generate('123456890');
		$this->assertEquals('1K7Q-CTFM-LMTC', $result0);

		$result1 = CouponCode::generate('12345689A');
		$this->assertEquals('X730-KCV1-MA2G', $result1);
	}

	public function testGenerateDiffer() {
		$result0 = CouponCode::generate();
		$result1 = CouponCode::generate();

		$this->assertNotEquals($result0, $result1);
	}

	public function testGenerateFormat() {
		$result = CouponCode::generate('123456890');
		$this->assertRegExp(
			'/^[0-9A-Z-]+$/',
			$result,
			'code comprises uppercase letter, digits and dashes'
		);
		$this->assertRegExp(
			'/^\w{4}-\w{4}-\w{4}$/',
			$result,
			'pattern is XXXX-XXXX-XXXX'
		);
	}
}

?>