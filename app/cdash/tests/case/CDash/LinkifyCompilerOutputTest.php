<?php

use App\Utils\RepositoryUtils;
use CDash\Test\CDashTestCase;

class LinkifyCompilerOutputTest extends CDashTestCase
{
    public function testLinkifyCompilerOutput()
    {
        $compiler_output =
            "/.../file.cxx:1:22: error: <fakefile.h>: No such file";

        $linkified_output = RepositoryUtils::linkify_compiler_output(
            'https://github.com/Kitware/CDash', "/\.\.\.", 'master',
            $compiler_output);

        $expected_output = "<a href='https://github.com/Kitware/CDash/blob/master/file.cxx#L1'>file.cxx:1</a>:22: error: &lt;fakefile.h&gt;: No such file";

        $this->assertEquals($expected_output, $linkified_output);
    }
}
