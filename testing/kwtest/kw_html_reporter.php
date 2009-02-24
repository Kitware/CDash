<?php
require_once('simpletest/reporter.php');
    
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

