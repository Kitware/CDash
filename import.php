<?php
// This is the installation script for the InsightJournalManager
include_once("config.php");
include_once("common.php");
include_once("ctestparser.php");

$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
mysql_select_db("$CDASH_DB_NAME",$db);

  
$xml = "<cdash>";
$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";

$project = mysql_query("SELECT name,id FROM project ORDER BY id");
while($project_array = mysql_fetch_array($project))
  {
  $xml .= "<project>";
  $xml .= "<name>".$project_array["name"]."</name>";
  $xml .= "<id>".$project_array["id"]."</id>";
  $xml .= "</project>";
  }
$xml .= "</cdash>";

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  $directory = $_POST["directory"];
		$projectid = $_POST["project"];
		
		if(strlen($directory)>0)
		{
		
		$directory = str_replace('\\\\','/',$directory);
		$files = globr($directory,"*.xml");
		foreach($files as $file)
		  {
				if(strlen($file)==0)
				  {
						continue;
						}

    $handle = fopen($file,"r");
    $contents = fread($handle,filesize($file));
				ctest_parse($contents,$projectid);
				fclose($handle);
		  }
				} // end strlen(directory)>0
}

// Now doing the xslt transition
$xh = xslt_create();
$filebase = 'file://' . getcwd () . '/';
xslt_set_base($xh,$filebase);

$arguments = array (
  '/_xml' => $xml
);

$html = xslt_process($xh, 'arg:/_xml', 'import.xsl', NULL, $arguments);

echo $html;

xslt_free($xh);
?>