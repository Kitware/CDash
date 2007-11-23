<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    
    <xsl:output method="html"/>
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
								
								<!-- Include the rounding css -->
		      <script src="javascript/rounded.js"></script>

       </head>
       <body bgcolor="#ffffff">

<table border="0" cellpadding="0" cellspacing="2" width="100%">
<tr>
<td align="center"><a href="index.php"><img alt="Logo/Homepage link" height="100" src="images/cdash.gif" border="0"/></a>
</td>
<td valign="bottom" width="100%">
<div style="margin: 0pt auto; background-color: #6699cc;"  class="rounded">		
<font color="#ffffff"><h2>CDash - Build Groups</h2>
<h3>Manage groups of builds</h3></font>
<br/></div>
</td>
</tr>
<tr>
<td></td><td>
</td>
</tr>
</table>
<br/>

<a href="user.php">[back]</a>
<br/><br/>

<xsl:choose>
 <xsl:when test="cdash/group_created=1">
 The group <b><xsl:value-of select="cdash/group_name"/></b> has been created successfully.<br/>          
 Click here to access the  <a>
 <xsl:attribute name="href">index.php?project=<xsl:value-of select="cdash/project_name"/></xsl:attribute>
project page</a>
 </xsl:when>
<xsl:otherwise>
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table width="100%"  border="0">
  <tr>
    <td width="14%"><div align="right"><strong>Project:</strong></div></td>
    <td width="86%"><select name="projectSelection">
				    <xsl:for-each select="cdash/project">
        <option>
								<xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
								<xsl:value-of select="name"/>
								</option>
								</xsl:for-each>
								</select></td>
  </tr>
  <tr>
    <td><div align="right"><strong>Name:</strong></div></td>
    <td><input name="name" type="text" id="name" size="50"/></td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="Submit" value="Create Group"/></td>
  </tr>
</table>
</form>
</xsl:otherwise>
</xsl:choose>
        
<script type="text/javascript">
  Rounded('rounded', 15, 15,0,0);
</script>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
