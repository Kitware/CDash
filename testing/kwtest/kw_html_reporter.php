<?php
require_once(dirname(dirname(__FILE__)) . '/config.test.php');
require_once(dirname(__FILE__).'/simpletest/reporter.php');
    
class KWHtmlReporter extends HtmlReporter {
  function paintPass($message) {
        parent::paintPass($message);
        print "<span class=\"pass\">Pass</span>: ";
        $breadcrumb = $this->getTestList();
        array_shift($breadcrumb);
        print implode("->", $breadcrumb);
        print "->$message<br />\n";
    }
}
?>

