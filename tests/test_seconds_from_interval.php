<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/common.php';

class SecondsFromIntervalTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testSecondsFromInterval()
    {
        // Switch to GMT to avoid Daylight saving time whackiness.
        date_default_timezone_set("GMT");

        $this->intervalTest('43s', 43);
        $this->intervalTest('24m', 1440);
        $this->intervalTest('24m 43s', 1483);
        $this->intervalTest('17h', 61200);
        $this->intervalTest('17h 43s', 61243);
        $this->intervalTest('17h 24m', 62640);
        $this->intervalTest('17h 24m 43s', 62683);
        $this->intervalTest('8 days', 691200);
        $this->intervalTest('8 days 43s', 691243);
        $this->intervalTest('8 days 24m', 692640);
        $this->intervalTest('8 days 24m 43s', 692683);
        $this->intervalTest('8 days 17h', 752400);
        $this->intervalTest('8 days 17h 43s', 752443);
        $this->intervalTest('8 days 17h 24m', 753840);
        $this->intervalTest('8 days 17h 24m 43s', 753883);
        // Not testing months since they have a variable number of seconds.

        // if 2 years ago was a leap year and the month is greater than Feb (2)
        $extraDay = date('L', strtotime('2 years')) && date('n') > 2;
        if (!$extraDay) {
            // if last year was a leap year.
            $extraDay = date('L', strtotime('1 year'));
        }

        $leapYearSeconds = $extraDay ? 86400 : 0;
        $this->intervalTest('2 years', 63072000 + $leapYearSeconds);
        $this->intervalTest('2 years 43s', 63072043 + $leapYearSeconds);
        $this->intervalTest('2 years 24m', 63073440 + $leapYearSeconds);
        $this->intervalTest('2 years 24m 43s', 63073483 + $leapYearSeconds);
        $this->intervalTest('2 years 17h', 63133200 + $leapYearSeconds);
        $this->intervalTest('2 years 17h 43s', 63133243 + $leapYearSeconds);
        $this->intervalTest('2 years 17h 24m', 63134640 + $leapYearSeconds);
        $this->intervalTest('2 years 17h 24m 43s', 63134683 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days', 63763200 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 43s', 63763243 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 24m', 63764640 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 24m 43s', 63764683 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 17h', 63824400 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 17h 43s', 63824443 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 17h 24m', 63825840 + $leapYearSeconds);
        $this->intervalTest('2 years 8 days 17h 24m 43s', 63825883 + $leapYearSeconds);
    }

    public function intervalTest($input, $expected)
    {
        $received = get_seconds_from_interval($input);
        if ($received !== $expected) {
            $this->fail("Expected $expected but received $received for '$input'");
        }
    }
}
