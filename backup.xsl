<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
<xsl:include href="footer.xsl"/>
<xsl:output method="html" encoding="iso-8859-1"/>
<xsl:template match="/">
<html>
<head>
  <title>CDash database backup</title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="StyleSheet" type="text/css">
    <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
  </link>
</head>
<body bgcolor="#ffffff">
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - Database Backup</h2></font>
</td></tr><tr><td></td><td>
<div id="navigator">
</div>
</td>
</tr>
</table>

<br/>
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="Submit" value="Download database backup"/></td>
  </tr>
</table>
</form>
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
             </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
