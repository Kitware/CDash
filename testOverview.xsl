<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
  <xsl:include href="header.xsl"/>
  <xsl:include href="footer.xsl"/>
  
  <xsl:include href="local/header.xsl"/>
  <xsl:include href="local/footer.xsl"/>
  
  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
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
    <xsl:call-template name="headscripts"/>
  </head>
  <body bgcolor="#ffffff">
  
<xsl:choose>         
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>
<br/>

<!-- Group selection -->
<form name="form1" method="post" action="">
<b>Group: </b>
<select onchange="document.form1.submit()" name="groupSelection">
  <option>
     <xsl:attribute name="value">0</xsl:attribute>All
  </option>
<xsl:for-each select="cdash/group">
  <option>
     <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
     <xsl:if test="selected=1">
     <xsl:attribute name="selected"></xsl:attribute>
     </xsl:if>
     <xsl:value-of select="name"/>
     </option>
     </xsl:for-each>
  </select>
</form>

<xsl:choose>
  <xsl:when test="count(cdash/tests)=0">
  <br/>
  No failing tests for this date.
  </xsl:when>
  <xsl:otherwise>

<h3>List of 
<u><xsl:value-of select="cdash/dashboard/projectname"/></u>
 tests that did not run cleanly on 
<xsl:value-of select="cdash/dashboard/startdate"/>
</h3>

<p>
<xsl:for-each select="cdash/tests/section">
<h3><xsl:value-of select="sectionName"/></h3>
<xsl:for-each select="test">
<a>
  <xsl:attribute name="href">
  <xsl:value-of select="summaryLink"/>
  </xsl:attribute><xsl:value-of select="testName"/></a>
<xsl:text disable-output-escaping="yes"> </xsl:text>
</xsl:for-each>
</xsl:for-each>
</p>
</xsl:otherwise>
</xsl:choose>

<br/>
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
