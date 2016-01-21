<?php
require_once(dirname(__FILE__) . '/kw_unlink.php');

/**#@+
 *  include other SimpleTest class files
 */
require_once('tests/kwtest/simpletest/web_tester.php');

/**
 *    Test case for testing of web pages. Allows
 *    fetching of pages, parsing of HTML and
 *    submitting forms.
 *    @package KWSimpletest
 *    @subpackage WebTester
 */
class KWWebTestCase extends WebTestCase
{
    public $url           = null;
    public $db            = null;
    public $logfilename   = null;
    public $cdashpro   = null;

    public function __construct()
    {
        parent::__construct();

        global $configure;
        $this->url = $configure['urlwebsite'];
        $this->cdashpro = false;
        if (isset($configure['cdashpro']) && $configure['cdashpro']=='1') {
            $this->cdashpro = true;
        }

        global $db;
        $this->db = new database($db['type']);
        $this->db->setDb($db['name']);
        $this->db->setHost($db['host']);
        $this->db->setPort($db['port']);
        $this->db->setUser($db['login']);
        $this->db->setPassword($db['pwd']);

        global $cdashpath;
        $this->logfilename = $cdashpath."/backup/cdash.log";
    }

    public function startCodeCoverage()
    {
        //echo "startCodeCoverage called...\n";
    if (extension_loaded('xdebug')) {
        xdebug_start_code_coverage(XDEBUG_CC_UNUSED | XDEBUG_CC_DEAD_CODE);
      //echo "xdebug_start_code_coverage called...\n";
    }
    }

    public function stopCodeCoverage()
    {
        //echo "stopCodeCoverage called...\n";
    if (extension_loaded('xdebug')) {
        $data = xdebug_get_code_coverage();
        xdebug_stop_code_coverage();
      //echo "xdebug_stop_code_coverage called...\n";
      global $CDASH_COVERAGE_DIR;
        $file = $CDASH_COVERAGE_DIR . DIRECTORY_SEPARATOR .
        md5($_SERVER['SCRIPT_FILENAME']);
        file_put_contents(
        $file . '.' . md5(uniqid(rand(), true)) . '.' . get_class(),
        serialize($data)
      );
    }
    }

  /**
   * find a string into another one
   * @return true if the search string has found or false in the other case
   * @param string $mystring
   * @param string $findme
   */
  public function findString($mystring, $findme)
  {
      if (strpos($mystring, $findme) === false) {
          return false;
      }
      return true;
  }

  /**
   * Try to connect to the website
   * @return the content if the connection succeeded
   *         or false if there were some errors
   * @param string $url
   */
  public function connect($url)
  {
      $page = $this->get($url);
      return $this->analyse($page);
  }

  /** Delete the log file */
  public function deleteLog($filename)
  {
      if (file_exists($filename)) {
          global $CDASH_TESTING_RENAME_LOGS;
          if ($CDASH_TESTING_RENAME_LOGS) {
              // Rename to a random name to keep for later inspection:
        //
        global $CDASH_LOG_FILE;
              rename($filename, $CDASH_LOG_FILE . "." . mt_rand() . ".txt");
          } else {
              // Delete file:
        cdash_testsuite_unlink($filename);
          }
      }
  }

  /** Look at the log file and return false if errors are found */
  public function checkLog($filename)
  {
      if (file_exists($filename)) {
          $content = file_get_contents($filename);
          if ($this->findString($content, 'ERROR')   ||
         $this->findString($content, 'WARNING')) {
              $this->fail("Log file has errors or warnings");
              return false;
          }
          return $content;
      }
      return true;
  }

  /** Compare the current log with a file */
  public function compareLog($logfilename, $template)
  {
      $log = "";
      if (file_exists($logfilename)) {
          $log = file_get_contents($logfilename);
          $log = str_replace("\r", '', $log);
      }
      $templateLog = file_get_contents($template);
      $templateLog = str_replace("\r", '', $templateLog);

    // Compare char by char
    $il=0;
      $it=0;
      while ($il<strlen($log) && $it<strlen($templateLog)) {
          if ($templateLog[$it] == '<') {
              $pos2 = strpos($templateLog, "<NA>", $it);
              $pos3 = strpos($templateLog, "<NA>\n", $it);

        // We skip the line
        if ($pos3 == $it) {
            while (($it < strlen($templateLog)) && ($templateLog[$it] != "\n")) {
                $it++;
            }
            while (($il < strlen($log)) && ($log[$il] != "\n")) {
                $il++;
            }
            continue;
        }
        // if we have the tag we skip the word
        elseif ($pos2 == $it) {
            while (($it < strlen($templateLog)) && ($templateLog[$it] != ' ') && ($templateLog[$it] != '/') && ($templateLog[$it] != ']')  && ($templateLog[$it] != '}') && ($templateLog[$it] != '"') && ($templateLog[$it] != '&')) {
                $it++;
            }
            while (($il < strlen($log)) && ($log[$il] != ' ') && ($log[$il] != '/') && ($log[$il] != ']') && ($log[$il] != '}') && ($log[$il] != '"') && ($log[$il] != '&')) {
                $il++;
            }
            continue;
        }
          }

          if ($log[$il] != $templateLog[$it]) {
              $this->fail("Log files are different\n  logfilename='$logfilename'\n  template='$template'\n  at char $it: ".ord($templateLog[$it])."=".ord($log[$il])."\n  **".substr($templateLog, $it, 10)."** vs. **".substr($log, $il, 10)."**");
              return false;
          }
          $it++;
          $il++;
      }
      return true;
  }

  /** Check the current content for errors */
  public function checkErrors()
  {
      $content = $this->getBrowser()->getContent();
      if ($this->findString($content, 'error:')) {
          $this->assertNoText('error');
          return false;
      }
      if ($this->findString($content, 'Warning')) {
          $this->assertNoText('Warning');
          return false;
      }
      if ($this->findString($content, 'Notice')) {
          $this->assertNoText('Notice');
          return false;
      }
      return true;
  }

  /**
   * Analyse a website page
   * @return the content of the page if there is no errors
   *         otherwise false
   * @param object $page
   */
  public function analyse($page)
  {
      if (!$page) {
          $this->assertTrue(false, "The requested URL was not found on this server");
          return false;
      }
      $browser = $this->getBrowser();
      $content = '';
      if ($browser->getResponseCode() == 200) {
          $content = $browser->getContent();
          if ($this->findString($content, ' error</b>:')) {
              $this->assertNoText('error');
              $error = true;
          }
          if ($this->findString($content, 'Warning:')) {
              $this->assertNoText('Warning');
              $error = true;
          }
          if ($this->findString($content, 'Notice:')) {
              $this->assertNoText('Notice');
              $error = true;
          }
      } else {
          $this->assertResponse(200, "The following url $page is not reachable");
          $error = true;
      }
      if (isset($error)) {
          return false;
      }
      return $content;
  }

    public function login($user='simpletest@localhost', $passwd='simpletest')
    {
        $this->get($this->url);
        $this->clickLink('Login');
        $this->setField('login', $user);
        $this->setField('passwd', $passwd);
        return $this->clickSubmitByName('sent');
    }

    public function logout()
    {
        $this->get($this->url);
        return $this->clickLink('Log Out');
    }

    public function submission($projectname, $file)
    {
        $url = $this->url."/submit.php?project=$projectname";
        $result = $this->uploadfile($url, $file);
        if ($this->findString($result, 'error')   ||
       $this->findString($result, 'Warning') ||
       $this->findString($result, 'Notice')) {
            $this->assertEqual($result, "\n");
            return false;
        }
        return true;
    }

    public function uploadfile($url, $filename)
    {
        set_time_limit(0); // sometimes this is slow when access the local webserver from external URL
    $fp = fopen($filename, 'r');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_UPLOAD, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
        $page = curl_exec($ch);
        curl_close($ch);
        fclose($fp);
        unset($fp);
        return $page;
    }

  // In case of the project does not exist yet
  public function createProject($name, $description, $svnviewerurl="", $bugtrackerfileurl="")
  {
      $this->clickLink('Create new project');
      $this->setField('name', $name);
      $this->setField('description', $description);
      $this->setField('cvsURL', $svnviewerurl);
      $this->setField('bugFileURL', $bugtrackerfileurl);
      $this->setField('public', '1');
      $this->clickSubmitByName('Submit');
      return $this->clickLink('Back');
  }
}
