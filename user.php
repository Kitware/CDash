<?php
include("config.php");
include('login.php');
include_once('common.php');

if ($session_OK) 
  {
		$userid = $_SESSION['cdash']['loginid'];
		$xml = "<cdash>";
		$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $user = mysql_query("SELECT * FROM user WHERE id='$userid'");
		$user_array = mysql_fetch_array($user);
	 $xml .= add_XML_value("user_name",$user_array["firstname"]);
		
		$xml .= "</cdash>";
		
		// Now doing the xslt transition
		$xh = xslt_create();
		$filebase = 'file://' . getcwd () . '/';
		xslt_set_base($xh,$filebase);
		
		$arguments = array (
				'/_xml' => $xml
		);
		
		$html = xslt_process($xh, 'arg:/_xml', 'user.xsl', NULL, $arguments);
		
		echo $html;
		
		xslt_free($xh);
  }

?>
