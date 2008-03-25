<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
    <xsl:include href="headerback.xsl"/> 
   
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
         </link>
       </head>
       <body bgcolor="#ffffff">
   <xsl:call-template name="headerback"/>

<xsl:if test="string-length(cdash/alert)>0">
<b><xsl:value-of select="cdash/alert"/></b>
</xsl:if>
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
  <tr>
    <td><div align="right">Fix build group based on build rules:</div></td>
    <td><div align="left"><input type="submit" name="FixBuildBasedOnRule" value="Fix build groups"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Fix new table test:</div></td>
    <td><div align="left"><input type="submit" name="FixNewTableTest" value="Move current tests to new table"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Delete builds with wrong start date:</div></td>
    <td><div align="left"><input type="submit" name="DeleteBuildsWrongDate" value="Delete builds"/></div></td>
  </tr> 
  <!-- 
   <tr>
    <td><div align="right">Add support for compressed coverage:</div></td>
    <td><div align="left"><input type="submit" name="CompressCoverage" value="Compress coverage"/></div></td>
  </tr> 
  -->
  <tr>
    <td><div align="right">Upgrade CDash: (this might take some time)</div></td>
    <td><div align="left"><input type="submit" name="Upgrade" value="Upgrade CDash"/></div></td>
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
