<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
    
    
   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
    
    <xsl:output method="html" encoding="iso-8859-1"/>
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
   
       <xsl:call-template name="header"/>
<br/>

<h3>
<xsl:if test="cdash/build/site">
Files changed <xsl:value-of select="cdash/build/site"/> -- <xsl:value-of select="cdash/build/buildname"/> as of <xsl:value-of select="cdash/build/buildtime"/>
</xsl:if>
<xsl:if test="cdash/updates/timestamp">
Nightly Changes as of <xsl:value-of select="cdash/updates/timestamp"/>
</xsl:if>
</h3>

<h3>
<font style="background-color: #C22b25">
<xsl:value-of select="cdash/updates/status"/>
<xsl:if test="string-length(cdash/updates/status)>0">
</xsl:if></font>
</h3>

<script type="text/javascript">var Icons = "images/";</script>
<script type="text/javascript" SRC="javascript/tree.js"></script>

[<a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onMouseOver="window.parent.status='Expand all';return true;" onClick="explode()">Expand all</a> <xsl:text>&#x20;</xsl:text>|<xsl:text>&#x20;</xsl:text><a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onMouseOver="window.parent.status='Collapse all';return true;" onClick="contract()">Collapse all</a>]

<p></p>
<script LANGUAGE="JavaScript">

    <xsl:value-of select="cdash/updates/javascript"/>

</script>

<script type="text/javascript" SRC="javascript/tree_init.js"></script>
<br/>
[<a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onMouseOver="window.parent.status='Expand all';return true;" onClick="explode()">Expand all</a> <xsl:text>&#x20;</xsl:text>|<xsl:text>&#x20;</xsl:text><a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onMouseOver="window.parent.status='Collapse all';return true;" onClick="contract()">Collapse all</a>]
<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
