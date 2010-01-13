<?php
require_once(dirname(dirname(__FILE__)) . '/config.test.php');

/**#@+
 *  include other SimpleTest class files
 */
require_once(dirname(__FILE__) . '/simpletest/web_tester.php');

/**
 *    Test case for testing of web pages. Allows
 *    fetching of pages, parsing of HTML and
 *    submitting forms.
 *    @package KWSimpletest
 *    @subpackage WebTester
 */
class KWWebTestCase extends WebTestCase {
  
  /**
   * find a string into another one
   * @return true if the search string has found or false in the other case
   * @param string $mystring
   * @param string $findme
   */
  function findString($mystring,$findme)
    {
    if (strpos($mystring, $findme) === false)
      {
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
  function connect($url)
    {
    $page = $this->get($url);
    return $this->analyse($page);
    }
  
  /** Delete the log file */
  function deleteLog($filename)
    {  
    if(file_exists($filename))
      {
      unlink($filename);
      }
    }
    
  /** Look at the log file and return false if errors are found */
  function checkLog($filename)
    {
    if(file_exists($filename))
      {
      $content = file_get_contents($filename);
      if($this->findString($content,'ERROR')   ||
         $this->findString($content,'WARNING'))
        {
        $this->fail("Log file as error or warnings");
        return false;
        }
      return $content;  
      }
    return true;   
    }

  /** Compare the current log with a file */
  function compareLog($logfilename,$template)
    {
    $log = "";  
    if(file_exists($logfilename))
      {
      $log = file_get_contents($logfilename);
      $log = str_replace("\r",'',$log);
      }
    $templateLog = file_get_contents($template);
    $templateLog = str_replace("\r",'',$templateLog);

    // Compare char by char
    $il=0;
    $it=0;
    while($il<strlen($log) && $it<strlen($templateLog))
      {
      if($templateLog[$it] == '<')
        {
        $pos2 = strpos($templateLog,"<NA>",$it);
        $pos3 = strpos($templateLog,"<NA>\n",$it);
  
        // We skip the line
        if($pos3 == $it)
          {
          while(($it < strlen($templateLog)) && ($templateLog[$it] != "\n"))
            {
            $it++;
            }
          while(($il < strlen($log)) && ($log[$il] != "\n"))
            {
            $il++;
            }
          continue;
          }
        // if we have the tag we skip the word
        else if($pos2 == $it)
          {
          while(($it < strlen($templateLog)) && ($templateLog[$it] != ' ') && ($templateLog[$it] != '/'))
            {
            $it++;
            }  
          while(($il < strlen($log)) && ($log[$il] != ' ') && ($log[$il] != '/'))
            {
            $il++;
            }  
          continue; 
          }
        }
      
      if($log[$il] != $templateLog[$it])
        {  
        $this->fail("Logs are different at char $it: ".ord($templateLog[$it])."=".ord($log[$il])." *".substr($templateLog,$it,10)."* v.s. *".substr($log,$il,10)."*");
        return false;
        }
      $it++;
      $il++;
      }
    return true;
    }
  
  /** Check the current content for errors */
  function checkErrors()
    {
    $content = $this->getBrowser()->getContent();
    if($this->findString($content,'error:'))
      {
      $this->assertNoText('error');
      return false;
      }
    if($this->findString($content,'Warning'))
      {
      $this->assertNoText('Warning');
      return false;
      }
    if($this->findString($content,'Notice'))
      {
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
  function analyse($page)
    {
    if(!$page)
      {
      $this->assertTrue(false,"The requested URL was not found on this server.");
      return false;
      }
    $browser = $this->getBrowser();
    $content = '';
    if($browser->getResponseCode() == 200)
      {
      $content = $browser->getContent();
      if($this->findString($content,' error</b>:'))
        {
        $this->assertNoText('error');
        $error = true;
        }
      if($this->findString($content,'Warning:'))
        {
        $this->assertNoText('Warning');
        $error = true;
        }
      if($this->findString($content,'Notice:'))
        {
        $this->assertNoText('Notice');
        $error = true;
        }
      }
    else
      {
      $this->assertResponse(200,"The following url $page is not reachable");
      $error = true;
      }
    if(isset($error))
      {
      return false;
      }
    return $content;
    }
    
  function login()
    {
    $this->get($this->url);
    $this->clickLink('Login');
    $this->setField('login','simpletest@localhost');
    $this->setField('passwd','simpletest');
    return $this->clickSubmitByName('sent');
    }
    
  function submission($projectname,$file)
    {
      $url = $this->url."/submit.php?project=$projectname";
      $result = $this->uploadfile($url,$file);
      if($this->findString($result,'error')   ||
         $this->findString($result,'Warning') ||
         $this->findString($result,'Notice'))
        {
        $this->assertEqual($result,"\n");
        return false;
        }
      return true;
    }
    
  function uploadfile($url,$filename)
    {
    set_time_limit(0); // sometimes this is slow when access the local webserver from external URL
    $fp = fopen($filename, 'r');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_UPLOAD, 1);
    curl_setopt($ch, CURLOPT_INFILE, $fp);
    curl_setopt ($ch, CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($filename));
    $page = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $page;
    }   
    
  // In case of the project does not exist yet
  function createProject($name,$description,$svnviewerurl="")
    {
    $this->clickLink('[Create new project]');
    $this->setField('name',$name);
    $this->setField('description',$description);
    $this->setField('cvsURL',$svnviewerurl);
    $this->setField('public','1');
    $this->clickSubmitByName('Submit');  
    return $this->clickLink('BACK');
    }
}
?>
