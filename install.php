<?php
// This is the installation script for the InsightJournalManager
include("config.php");

$xml = "<cdash>";

$xml .= "<connectiondb_host>".$CDASH_DB_HOST."</connectiondb_host>";
$xml .= "<connectiondb_login>".$CDASH_DB_LOGIN."</connectiondb_login>";
$xml .= "<connectiondb_name>".$CDASH_DB_NAME."</connectiondb_name>";

// Step 1: Check if we can connect to the database
@$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
if(!$db)
  {
		$xml .= "<connectiondb>0</connectiondb>";
  }
else
  {
		$xml .= "<connectiondb>1</connectiondb>";
  }  		
		
if(xslt_create() === FALSE)
  {
		$xml .= "<xslt>0</xslt>";
  }
else
  {
		$xml .= "<xslt>1</xslt>";
  }
		
		
// If the database already exists we quit
if(@mysql_select_db("$CDASH_DB_NAME",$db) === TRUE)
  {
		$xml .= "<database>1</database>";
  }
else
  {
		$xml .= "<database>0</database>";
		$xml .= "<dashboard_timeframe>24</dashboard_timeframe>";
		

// If we should create the tables
@$Submit = $_POST["Submit"];
if($Submit)
{
  if(!mysql_query("CREATE DATABASE IF NOT EXISTS $CDASH_DB_NAME"))
		  {
				$xml .= "<db_created>0</db_created>";
				$xml .= "<alert>".mysql_error()."</alert>";
				}
		else
		{
		mysql_select_db("$CDASH_DB_NAME",$db);
  $sqlfile = "sql/cdash.sql";
		$file_content = file($sqlfile);
  //print_r($file_content);
  $query = "";
  foreach($file_content as $sql_line)
		  {
    $tsl = trim($sql_line);
     if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) 
					  {
       $query .= $sql_line;
       if(preg_match("/;\s*$/", $sql_line)) 
							  {
         $query = str_replace(";", "", "$query");
         $result = mysql_query($query);
         if (!$result)
									  { 
											$xml .= "<db_created>0</db_created>";
											die(mysql_error());
           }
									$query = "";
         }
       }
     } // end for each line
		
		$sqlfile = "sql/cdashdata.sql";
		$file_content = file($sqlfile);
  //print_r($file_content);
  $query = "";
  foreach($file_content as $sql_line)
		  {
    $tsl = trim($sql_line);
     if (($sql_line != "") && (substr($tsl, 0, 2) != "--") && (substr($tsl, 0, 1) != "#")) 
					  {
       $query .= $sql_line;
       if(preg_match("/;\s*$/", $sql_line)) 
							  {
         $query = str_replace(";", "", "$query");
         $result = mysql_query($query);
         if (!$result)
									  { 
											$xml .= "<db_created>0</db_created>";
											die(mysql_error());
           }
									$query = "";
         }
       }
     } // end for each line*/
			$xml .= "<db_created>1</db_created>";
			} // end database created
} // end submit

} // end database doesn't exists


$xml .= "</cdash>";

// Now doing the xslt transition
$xh = xslt_create();
$filebase = 'file://' . getcwd () . '/';
xslt_set_base($xh,$filebase);

$arguments = array (
  '/_xml' => $xml
);

$html = xslt_process($xh, 'arg:/_xml', 'install.xsl', NULL, $arguments);

echo $html;

xslt_free($xh);
?>