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
        $this->ConfigFile = dirname(__FILE__) . '/../config/config.local.php';
    }

    public function testEnableConfigSetting()
    {
        $contents = file_get_contents($this->ConfigFile);
        $handle = fopen($this->ConfigFile, 'w');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (strpos($line, 'CDASH_USE_LOCAL_DIRECTORY') !== false) {
                fwrite($handle, "\$CDASH_USE_LOCAL_DIRECTORY = '1';\n");
            } elseif ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
    }

    public function testOverrideHeader()
    {
        $config = Config::getInstance();
        $root_dir = $config->get('CDASH_ROOT_DIR');

        // Create a local header & footer.
        $dir_name = "$root_dir/public/local/views";
        if (!file_exists($dir_name)) {
            mkdir($dir_name);
        }
        touch("$root_dir/public/local/views/header.html");
        touch("$root_dir/public/local/views/footer.html");

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

    public function testRestoreConfigSetting()
    {
        $contents = file_get_contents($this->ConfigFile);
        $handle = fopen($this->ConfigFile, 'w');
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            if (strpos($line, 'CDASH_USE_LOCAL_DIRECTORY') !== false) {
                fwrite($handle, "\$CDASH_USE_LOCAL_DIRECTORY = '0';\n");
            } elseif ($line != '') {
                fwrite($handle, "$line\n");
            }
        }
        fclose($handle);
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
