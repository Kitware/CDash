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
<font color="#ffffff"><h2>CDash - Backward Compatibility Tools</h2>
<h3>Manage backward compatibility</h3></font>
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

<xsl:when test="cdash/alert">
<b><xsl:value-of select="cdash/alert"/></b>
</xsl:when>
<br/><br/>

<form name="form1" enctype="multipart/form-data" method="post" action="">
<table border="0">
  <tr>
    <td><div align="right">Create default group (nightly/experimental/continuous) for projects:</div></td>
    <td><div align="left"><input type="submit" name="CreateDefaultGroups" value="Create default groups"/></div></td>
  </tr>  
		<tr>
    <td><div align="right">Assign unknown builds to group based on type:</div></td>
    <td><div align="left"><input type="submit" name="AssignBuildToDefaultGroups" value="Assign builds to default groups"/></div></td>
  </tr>
</table>
</form>
        
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
