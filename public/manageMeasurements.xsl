<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headscripts.xsl"/>
   <xsl:include href="headeradminproject.xsl"/>

    <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/headscripts.xsl"/>
   <xsl:include href="local/headeradminproject.xsl"/>

<xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

<xsl:template match="/">
<html>
<head>
  <title><xsl:value-of select="cdash/title"/></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="StyleSheet" type="text/css">
    <xsl:attribute name="href">
      <xsl:value-of select="cdash/cssfile"/>
    </xsl:attribute>
  </link>
  <script src="js/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
  <script type="text/javascript" charset="utf-8">
    function Ask()
    {
      if(confirm('Are you sure you want to delete selected entries?')) return true;
      else return false;
    }
  </script>
  <xsl:call-template name="headscripts"/>
</head>
<body bgcolor="#ffffff">

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="headeradminproject_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="headeradminproject"/>
</xsl:otherwise>
</xsl:choose>

<form method='POST'>
<xsl:attribute name="action">manageMeasurements.php?projectid=<xsl:value-of select="cdash/project/id" /></xsl:attribute>

  <br/>
<table width="800px" align='center'>
<tr bgcolor="#CCCCCC"><th>Measurement Name</th><th>Show on Test Page</th><th>Show Test Summary Page</th><th>Delete</th></tr>
  <xsl:for-each select="/cdash/measurements/measurement">
     <tr>
       <td align="center">
         <input name="name[]" type="text" id="name[]" size="25">
           <xsl:attribute name="value"><xsl:value-of select="name" /></xsl:attribute>
         </input>
         <input type='hidden' name='id[]'>
       <xsl:attribute name="value"><xsl:value-of select="id" /></xsl:attribute>
         </input>
       </td>
       <td align="center">
        <input type="checkbox" value="1">
          <xsl:attribute name="name">showT[<xsl:value-of select="id" />]</xsl:attribute>
            <xsl:if test="showT=1">
              <xsl:attribute name="checked"></xsl:attribute>
            </xsl:if>
        </input>
       </td>
       <td align="center">
        <input type="checkbox" value="1">
          <xsl:attribute name="name">showS[<xsl:value-of select="id" />]</xsl:attribute>
            <xsl:if test="showS=1">
              <xsl:attribute name="checked"></xsl:attribute>
            </xsl:if>
        </input>
       </td>
       <td align="center">
         <input type="checkbox" name="select[]">
           <xsl:attribute name='value'><xsl:value-of select='id'/></xsl:attribute>
         </input>
       </td>
   </tr>
   </xsl:for-each>
   <tr bgcolor="#CADBD9">
      <td align="center">
        <input name="nameN" type="text" id="nameN" size="25"/>
      </td>
      <td align="center">
        <input type="checkbox" name="showTN" value="1"/>
      </td>
      <td align="center">
        <input type="checkbox" name="showSN" value="1"/>
      </td>
      <td></td>
   </tr>
</table>
<center>
  <input name='submit' value='Save' type='submit'/>
  <input name='del' value='Delete Selected' onClick='javascript:return Ask()' type='submit'/>
</center>
  <input type='hidden' name='projectid'>
    <xsl:attribute name="value">
      <xsl:value-of select="/cdash/project/id" />
    </xsl:attribute>
  </input>
</form>

<!-- FOOTER -->
<br/>
<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="footer_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="footer"/>
</xsl:otherwise>
</xsl:choose>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
