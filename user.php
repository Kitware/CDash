<?php
include("config.php");
include('login.php');
include_once('common.php');

if ($session_OK) 
  {
		$userid = $_SESSION['cdash']['loginid'];
		$xml = "<cdash>";
		$xml .= "<cssfile>".$CDASH_CSS_FILE."</cssfile>";
		$db = mysql_connect("$CDASH_DB_HOST", "$CDASH_DB_LOGIN","$CDASH_DB_PASS");
  mysql_select_db("$CDASH_DB_NAME",$db);

  $user = mysql_query("SELECT * FROM user WHERE id='$userid'");
		$user_array = mysql_fetch_array($user);
	 $xml .= add_XML_value("user_name",$user_array["firstname"]);
		
		$xml .= "</cdash>";
		
		// Now doing the xslt transition
  generate_XSLT($xml,"user");
  }

?>
