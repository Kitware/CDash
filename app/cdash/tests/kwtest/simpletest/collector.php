<?php

/**
 * This file contains the following classes: {@link SimpleCollector},
 * {@link SimplePatternCollector}.
 *
 * @author Travis Swicegood <development@domain51.com>
 *
 * @version $Id$
 */

/**
 * The basic collector for {@link GroupTest}
 *
 * @see collect(), GroupTest::collect()
 */
class SimpleCollector
{
    /**
     * Strips off any kind of slash at the end so as to normalise the path.
     *
     * @param string $path path to normalise
     *
     * @return string path without trailing slash
     */
    protected function removeTrailingSlash($path)
    {
        if (substr($path, -1) == DIRECTORY_SEPARATOR) {
            return substr($path, 0, -1);
        } elseif (substr($path, -1) == '/') {
            return substr($path, 0, -1);
        } else {
            return $path;
        }
    }

    /**
     * Scans the directory and adds what it can.
     *
     * @param object $test group test with {@link GroupTest::addTestFile()} method
     * @param string $path directory to scan
     *
     * @see _attemptToAdd()
     */
    public function collect(&$test, $path)
    {
        $path = $this->removeTrailingSlash($path);
        if ($handle = opendir($path)) {
            while (($entry = readdir($handle)) !== false) {
                if ($this->isHidden($entry)) {
                    continue;
                }
                $this->handle($test, $path . DIRECTORY_SEPARATOR . $entry);
            }
            closedir($handle);
        }
    }

    /**
     * This method determines what should be done with a given file and adds
     * it via {@link GroupTest::addTestFile()} if necessary.
     *
     * This method should be overriden to provide custom matching criteria,
     * such as pattern matching, recursive matching, etc.  For an example, see
     * {@link SimplePatternCollector::_handle()}.
     *
     * @param object $test group test with {@link GroupTest::addTestFile()} method
     *
     * @see collect()
     */
    protected function handle(&$test, $file)
    {
        if (is_dir($file)) {
            return;
        }
        $test->addFile($file);
    }

    /**
     *  Tests for hidden files so as to skip them. Currently
     *  only tests for Unix hidden files.
     *
     * @param string $filename plain filename
     *
     * @return bool true if hidden file
     */
    protected function isHidden($filename)
    {
        return strncmp($filename, '.', 1) == 0;
    }
}

/**
 * An extension to {@link SimpleCollector} that only adds files matching a
 * given pattern.
 *
 * @see SimpleCollector
 */
class SimplePatternCollector extends SimpleCollector
{
    private $pattern;

    /**
     * @param string $pattern Perl compatible regex to test name against
     *                        See {@link http://us4.php.net/manual/en/reference.pcre.pattern.syntax.php PHP's PCRE}
     *                        for full documentation of valid pattern.s
     */
    public function __construct($pattern = '/php$/i')
    {
        $this->pattern = $pattern;
    }

    /**
     * Attempts to add files that match a given pattern.
     *
     * @see SimpleCollector::_handle()
     *
     * @param object $test group test with {@link GroupTest::addTestFile()} method
     */
    protected function handle(&$test, $filename)
    {
        if (preg_match($this->pattern, $filename)) {
            parent::handle($test, $filename);
        }
    }
}
