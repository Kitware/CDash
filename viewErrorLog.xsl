<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
   
   <!-- Local includes -->
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="local/header.xsl"/> 
       
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN" 
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">
      <html>
       <head>
       <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
         <link rel="StyleSheet" type="text/css">
         <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
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

<h3 style="background: #b0c4de">Error Log</h3>
<xsl:for-each select="cdash/error">
  <xsl:if test="type=4">
  <img src="images/error.png"/> <b> Error </b> 
  </xsl:if>
  <xsl:if test="type=5">
  <img src="images/warning.png"/> <b> Warning </b>
  </xsl:if>

  reported on <xsl:value-of select="date"/>
  <xsl:if test="buildid>0">
   for <a>
   <xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="buildid"/></xsl:attribute>
   build #<xsl:value-of select="buildid"/>
   </a>
  </xsl:if> 
  <xsl:if test="projectname">
   (<xsl:value-of select="projectname"/>)
  </xsl:if> 
  <br/>
  <i><xsl:value-of select="description"/></i><br/>
  
  <xsl:if test="resourcetype=1 and resourceid>0">Project: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=2 and resourceid>0">Build: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=3 and resourceid>0">Update: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=4 and resourceid>0">Configure: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=5 and resourceid>0">Test: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=6 and resourceid>0">Coverage: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=7 and resourceid>0">Dynamic Analysis: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <xsl:if test="resourcetype=8 and resourceid>0">User: <xsl:value-of select="resourceid"/><br/></xsl:if>
  <br/>
</xsl:for-each>
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
