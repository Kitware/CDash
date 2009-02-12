<?php
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
      return;
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
      $this->assertResponse(200);
      $error = true;
      }
    if(isset($error))
      {
      return false;
      }
    return $content;
    }
}
?>
