<?php # vim:ts=2:sw=2:noet:
/* Copyright (c) 2007, OmniTI Computer Consulting, Inc.
 * All Rights Reserved.
 * For licensing information, see:
 * http://labs.omniti.com/alexandria/trunk/LICENSE
 */

/* This module provides a simple testing framework along the lines of the perl
 * Test::More module.
 *
 * We use the Test::More style of testing in a number of other non-PHP projects
 * at OmniTI, so it makes sense to preserve that style when working with PHP,
 * and so we have this module.
 */

// http://search.cpan.org/~petdance/Test-Harness-2.64/lib/Test/Harness/TAP.pod

class OmniTI_Test_More {
	static $tests = null;
	static $todo = array();
	static $fails = 0;
	static $current_test = 0;

	function cmp($left, $op, $right) {
		switch ($op) {
			case 'ge':
			case '>=':
				return $left >= $right;
			case 'le':
			case '<=':
				return $left <= $right;
			case 'eq':
			case '=':
			case '==':
				return $left == $right;
			case '===':
				return $left === $right;
			case '!==':
				return $left !== $right;
			case 'lt':
			case '<':
				return $left < $right;
			case 'gt':
			case '>':
				return $left > $right;
			case '!=':
			case 'ne':
				return $left != $right;
		}
		return false;
	}

	function summarize() {
		$ran = self::$current_test;
		$fail = self::$fails;
		$expected = self::$tests;
		if ($fail == 0 && $ran == $expected) {
			diag("all tests passed");
		} else if ($fail && $ran > $expected) {
			diag("Looks like you failed $fail test(s) and ran " .
				($ran - $expected) . " more than expected ($expected)");
		} else if ($fail) {
			diag("Looks like you failed $fail test(s) of $expected");
		} else if ($ran > $expected) {
			diag("Looks like you ran $ran test(s) but only planned for $expected");
		} else {
			diag("Looks like you only ran $ran test(s) of $expected");
		}
	}

	function readable_escape($string) {
		if (!is_string($string)) {
			ob_start();
			var_dump($string);
			$string = ob_get_contents();
			ob_end_clean();
			return $string;
		}
		return addcslashes($string, "\0\r\n\t");
	}
}

/** set the plan for a test.
 *
 * It is important to call this function once for every test, so that the test
 * harness can determine the overall success of the test run.
 *
 * In its simplest form, the plan call is passed a integer value representing
 * the number of sub-tests that will be executed by a given test file.
 *
 * It should usually be called before you call any of the other test functions
 * provided by this module, but can be called part-way through the test
 * execution if you don't know the number of tests up-front.
 *
 * If plan() is passed an array, it must have a 'tests' key that specifies the
 * number of tests to be run, and may have a 'todo' key that specifies the
 * numbers of sub-tests that should be considered to have TODO status.  TODO
 * status sub-tests are expected to fail, but should not be considered an
 * overal failure of the test when run by the harness.
 *
 * \code
 * require 'OmniTI/Test/More.php';
 * plan(1);
 * is(1, 1, "one is one");
 * \endcode
 *
 * \code
 * require 'OmniTI/Test/More.php';
 * # indicate that test 2 is expected to fail at this time
 * plan(array('tests' => 2, 'todo' => array(2)));
 * is(1, 1, "one is one");
 * function enable_world_peace() { return false; }
 * ok(enable_world_peace(), "let there be peace"); # this is test 2
 * \endcode
 */

function plan($plan) {
	static $called = false;

	if ($called) {
		throw new Exception("You must call plan only once");
	}
	$called = true;

	if (is_array($plan)) {
		foreach ($plan as $key => $value) {
			switch ($key) {
				case 'todo': OmniTI_Test_More::$todo = $value; break;
				case 'tests':
					if (!is_integer($value)) {
						throw new Exception("tests must be an integer number");
					}
					OmniTI_Test_More::$tests = $value;
					break;
				case 'skip_all':
					echo "1..0 # skip_all $value\n";
					exit(0);
				default:
					throw new Exception("Invalid plan type $key");
			}
		}
	} else {
		if (!is_integer($plan)) {
			throw new Exception("tests must be an integer number");
		}
		OmniTI_Test_More::$tests = $plan;
	}

	echo "1.." . OmniTI_Test_More::$tests . "\n";

	register_shutdown_function(array('OmniTI_Test_More', 'summarize'));
}

/** tests a boolean condition.
 *
 * If the first parameter evaluates to true, then this subtest is considered
 * to have passed.  Otherwise, it is considered to have failed.
 *
 * The second parameter is an optional test name to indicate what was being tested,
 * and the third parameter is an optional diagnostic string that will be emitted
 * if the test failed.
 */
function ok($status, $name = '', $diagnostics = null)
{
	$num = ++OmniTI_Test_More::$current_test;

	if ($name[0] != '-') {
		$name = "- $name";
	}

	$is_todo = in_array($num, OmniTI_Test_More::$todo);

	if ($is_todo) {
		$name .= " # TODO";
	}

	if ($status) {
		echo "ok $num $name\n";
	} else {

		$trace = debug_backtrace();
		while ($trace[0]['file'] == __FILE__) {
			array_shift($trace);
		}
		$file = $trace[0]['file'];
		$cwd = getcwd();
		if (!strncasecmp($cwd, $file, strlen($cwd))) {
			$file = substr($file, strlen($cwd)+1);
		}
		$line = $trace[0]['line'];

		echo "not ok $num $name $file:$line\n";
		if ($diagnostics !== null) {
			diag($diagnostics);
		}

		OmniTI_Test_More::$fails++;
	}

	return $status;
}

/** asserts that a value meets expectations
 *
 * If the first parameter is equal to the second, expected, parameter
 * then this sub-test passes.  Otherwise it fails.
 * The third parameter is an optional test name.
 */
function is($value, $expected, $name = '') {
	$pass = ($value == $expected);
	$pass = ok($pass, $name);
	if (!$pass) {
		$value = OmniTI_Test_More::readable_escape($value);
		$expected = OmniTI_Test_More::readable_escape($expected);
		diag("         got: '$value'");
		diag("    expected: '$expected'");
	}
	return $pass;
}

/** asserts that a value is not equal to another.
 *
 * If the first parameter is equal to the second, unexpected, parameter
 * then this sub-test fails.  Otherwise it passes.
 * The third parameter is an optional test name.
 */

function isnt($value, $unexpected, $name = '') {
	$pass = ($value != $unexpected);
	$pass = ok($pass, $name);
	if (!$pass) {
		$value = OmniTI_Test_More::readable_escape($value);
		$unexpected = OmniTI_Test_More::readable_escape($unexpected);
		diag("         '$value'");
		diag("            should not equal");
		diag("         '$unexpected'");
	}
	return $pass;
}

/** test whether a value matches a pcre regex
 *
 * If the value matches the pattern, the test passes.
 */

function like($value, $pattern, $name = '') {
	$pass = preg_match($pattern, $value);
	$pass = ok($pass, $name);
	if (!$pass) {
		$value = OmniTI_Test_More::readable_escape($value);
		$pattern = OmniTI_Test_More::readable_escape($pattern);
		diag("                  '$value'");
		diag("    doesn't match '$pattern'");
	}
	return $pass;
}

/** test whether a value doesn't match a pcre regex
 *
 * If the value matches the pattern, the test fails.
 */

function unlike($value, $pattern, $name) {
	$pass = !preg_match($pattern, $value);
	$pass = ok($pass, $name);
	if (!$pass) {
		$value = OmniTI_Test_More::readable_escape($value);
		$pattern = OmniTI_Test_More::readable_escape($pattern);
		diag("             '$value'");
		diag("     matches '$pattern'");
	}
	return $pass;
}

/** test whether a value passes a relational comparison test.
 *
 */

function cmp_ok($left, $op, $right, $name) {
	$pass = ok(OmniTI_Test_More::cmp($left, $op, $right), $name);
	if (!$pass) {
		$left = OmniTI_Test_More::readable_escape($left);
		$right = OmniTI_Test_More::readable_escape($right);
		diag("         '$left'");
		diag("         not $op");
		diag("         '$right'");
	}
	return $pass;
}

/** test whether an object has one or more methods */
function can_ok($object, $methods, $name = 'has methods') {
	$pass = true;
	$cant = array();
	if (!is_array($methods)) $methods = array($methods);
	foreach ($methods as $method) {
		if (!method_exists($object, $method)) {
			$cant[] = $method;
			$pass = false;
		}
	}
	if ($pass) {
		ok(1, $name);
	} else {
		ok(0, $name);
		foreach ($cant as $method) {
			diag("   can't $method");
		}
	}
	return $pass;
}

/** test whether an object is an instance of a particular class or interface */
function isa_ok($object, $expected_class, $name = 'the object') {
	$pass = $object instanceof $expected_class;
	ok($pass, "$name isa $expected_class");
	if (!$pass) {
		$left = get_class($object);
		diag("         got: '$left'");
		diag("    expected: '$expected_class'");
	}
	return $pass;
}

/** an unconditionally passing sub-test.
 *
 * It should generally be avoided in favor of using the other
 * test forms.
 */
function pass($name = '') {
	return ok(1, $name);
}

/** an unconditionally failing sub-test.
 *
 * It should generally be avoided in favor of using the other
 * test forms.
 */
function fail($name = '') {
	return ok(0, $name);
}

/** emits diagnostic output.
 *
 * You should avoid using echo/print or otherwise sending data to
 * stdout when running a test, as the harness expects that output
 * stream to consist of "TAP" formatted data.
 *
 * If you want to emit diagnostic output, use diag().
 *
 * Will accept either an array of strings or the same parameters
 * as printf.
 */
function diag($message) {
	if (is_array($message)) {
		foreach ($message as $msg) {
			echo "# $msg\n";
		}
	} elseif (is_object($message)) {
		ob_start();
		var_dump($message);
		$data = ob_get_contents();
		ob_end_clean();
		diag($data);
	} else {
		$args = func_get_args();
		$message = call_user_func_array('sprintf', $args);
		$message = str_replace("\n", "\n# ", $message);
		echo "# $message\n";
	}
}

/** tests that an include succeeds */
function include_ok($module) {
	$pass = ((include $module) == 1);
	return ok($pass, "include $module");
}

/** skip the next n tests.
 *
 * This test is a little strange; it is intended to be used to
 * skip a block of tests.  If the first parameter is true, then
 * skip() immediately returns true.  Otherwise, it generates
 * a number of passes equal to the n_to_skip parameter,
 * annotated with the reason from the second parameter, and then
 * returns false.
 *
 * The intention is for it to be used as the condition in an if
 * statement as shown below:
 *
 * \code
 * if (skip($thing_supported, "skipping this block", 2)) {
 *   ok(optional_thing_one());
 *   ok(optional_thing_two());
 * }
 * \endcode
 *
 * If $thing_supported is true, then the optional_thing_xxx() functions
 * are presumed to be present and working, so the if block is entered
 * and executed as normal.  Otherwise, the skip() call generates 2 passing
 * tests to match the number of tests within the if block, so that the
 * overall number of tests executed matches the number in the test plan.
 */
function skip($should_not_skip, $reason, $n_to_skip = 1) {
	if ($should_not_skip) {
		return true;
	}
	for ($i = 0; $i < $n_to_skip; $i++) {
		ok(1, "# SKIP $reason");
	}
	return false;
}

