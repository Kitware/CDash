<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
use CDash\Config;

require_once dirname(__FILE__) . '/cdash_test_case.php';
require_once 'include/common.php';
require_once 'include/pdo.php';

class OverrideHeaderTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testOverrideHeader()
    {
        config(['cdash.allow.local_directory' => true]);

        // Create a local header & footer.
        $view_path = config('cdash.file.path.custom.views');

        // TODO: this path should either exist or not
        // TODO: consider moving files like this (e.g. footer.html) into <root>/storage/cdash/...
        if (!file_exists($view_path)) {
            mkdir($view_path);
        }

        touch("{$view_path}/header.html");
        touch("{$view_path}/footer.html");

        // Verify that these are used.
        $this->get($this->url . '/api/v1/index.php?project=InsightExample');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        if ($jsonobj['header'] !== 'local/views/header.html') {
            $this->fail('Expected local/views/header.html, found ' . $jsonobj['header']);
            $this->cleanup();
            return 1;
        }

        if ($jsonobj['footer'] !== 'local/views/footer.html') {
            $this->fail('Expected local/views/footer.html, found ' . $jsonobj['footer']);
            $this->cleanup();
            return 1;
        }

        $this->cleanup();
        $this->pass('Passed');
        return 0;
    }

    private function cleanup()
    {
        $config = Config::getInstance();
        $root_dir = $config->get('CDASH_ROOT_DIR');

        // Delete the local files that we created.
        unlink("$root_dir/public/local/views/header.html");
        unlink("$root_dir/public/local/views/footer.html");
    }
}
