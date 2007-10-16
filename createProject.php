<?php
// This is the installation script for the InsightJournalManager
include("config.php");
@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);
$xml = "<cdash>";

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  $Name = $_POST["name"];
  $Description = addslashes($_POST["description"]);
		$HomeURL = $_POST["homeURL"];
		$CVSURL = $_POST["cvsURL"];
		$BugURL = $_POST["bugURL"];
				
		$handle = fopen($_FILES['logo']['tmp_name'],"r");
		$contents = addslashes(fread($handle,$_FILES['logo']['size']));
		fclose($handle);
		
		$sql = "INSERT INTO project(name,description,homeurl,cvsurl,bugtrackerurl,logo) 
	    				VALUES ('$Name','$Description','$HomeURL','$CVSURL','$BugURL','$contents')"; 
  if(mysql_query("$sql"))
		  {
				$xml .= "<project_name>$Name</project_name>";
				$xml .= "<project_created>1</project_created>";
		  }
		
		echo mysql_error();
} // end submit

$xml .= "</cdash>";

// Now doing the xslt transition
$xh = xslt_create();
$filebase = 'file://' . getcwd () . '/';
xslt_set_base($xh,$filebase);

$arguments = array (
  '/_xml' => $xml
);

$html = xslt_process($xh, 'arg:/_xml', 'createProject.xsl', NULL, $arguments);

echo $html;

xslt_free($xh);
?>