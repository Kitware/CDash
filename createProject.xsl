<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

			<xsl:include href="footer.xsl"/>
				
    <xsl:output method="html"/>
    <xsl:template match="/">
      <html>
	      <head>
							<title><xsl:value-of select="cdash/title"/></title>
		      <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css" href="cdash.css" />
	      </head>
       <body bgcolor="#ffffff">
			
<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td bgcolor="#6699cc" valign="top" width="100%">
<font color="#ffffff"><h2>CDash - New Project</h2>
<h3>Creating new project</h3></font>
</td></tr><tr><td></td><td>
<div id="navigator">
</div>
</td>
</tr>
</table>

<br/>

<xsl:choose>
 <xsl:when test="cdash/project_created=1">
 The project <b><xsl:value-of select="cdash/project_name"/></b> has been created successfully.<br/>          
	Click here to access the  <a>
	<xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
	CDash project page</a>
	</xsl:when>
<xsl:otherwise>
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td width="14%"><div align="right"><strong>Name:</strong></div></td>
    <td width="86%"><input name="name" type="text" id="name"/></td>
  </tr>
  <tr>
    <td><div align="right"><strong>Description:</strong></div></td>
    <td><textarea name="description" id="description" cols="40" rows="5"></textarea></td>
  </tr>
  <tr>
    <td><div align="right"><strong>Home URL :</strong></div></td>
    <td><input name="homeURL" type="text" id="homeURL" size="50"/></td>
  </tr>
  <tr>
    <td><div align="right"><strong>CVS URL :</strong></div></td>
    <td><input name="cvsURL" type="text" id="cvsURL" size="50"/></td>
  </tr>
  <tr>
    <td><div align="right"><strong>Bug Tracker URL:</strong></div></td>
    <td><input name="bugURL" type="text" id="bugURL" size="50"/></td>
  </tr>
  <tr>
    <td><div align="right"><strong>Logo:</strong></div></td>
    <td><input type="file" name="logo"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="Submit" value="Create Project"/></td>
  </tr>
</table>
</form>
</xsl:otherwise>
</xsl:choose>
								
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
					   </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
