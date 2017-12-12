<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>


   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>

   <!-- Include local common files -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>

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
         <!-- Include JavaScript -->
         <script src="js/cdashUpdateGraph.js" type="text/javascript" charset="utf-8"></script>
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

<h4>
<xsl:if test="cdash/build/site">
Files changed on <a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="cdash/build/siteid"/></xsl:attribute>
<xsl:value-of select="cdash/build/site"/></a>
(<xsl:value-of select="cdash/build/buildname"/>) as of <xsl:value-of select="cdash/build/buildtime"/>
</xsl:if>
<xsl:if test="cdash/updates/timestamp">
Nightly Changes as of <xsl:value-of select="cdash/updates/timestamp"/>
</xsl:if>
</h4>

<xsl:if test="string-length(cdash/updates/revision)>0">
<b>Revision: </b>
<a><xsl:attribute name="href"><xsl:value-of select="cdash/updates/revisionurl"/></xsl:attribute>
<xsl:value-of select="cdash/updates/revision"/>
</a>
<xsl:if test="string-length(cdash/updates/priorrevision)>0">
<br/>
<b>Prior Revision: </b>
<a><xsl:attribute name="href"><xsl:value-of select="cdash/updates/revisiondiff"/></xsl:attribute>
<xsl:value-of select="cdash/updates/priorrevision"/>
</a>
<br/>
<br/>
</xsl:if>
</xsl:if>

<!-- Graph -->
<xsl:if test="cdash/build/site">
<a>
<xsl:attribute name="href">javascript:showbuildgraph_click(<xsl:value-of select="cdash/build/buildid"/>)</xsl:attribute>
Show Activity Graph
</a>
</xsl:if>

<div id="graphoptions"></div>
<div id="graph"></div>
<center>
<div id="grapholder"></div>
</center>

<h3>
<font style="background-color: #C22b25">
<xsl:value-of select="cdash/error"/>
</font>
</h3>

<h3>
<font style="background-color: #C22b25">
<xsl:value-of select="cdash/updates/status"/>
<xsl:if test="string-length(cdash/updates/status)>0">
</xsl:if></font>
</h3>

<xsl:text disable-output-escaping="yes">&lt;script type="text/javascript">var Icons = "img/";&lt;/script&gt;</xsl:text>
<script type="text/javascript" src="js/tree.js"></script>

<a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onmouseover="window.parent.status='Expand all';return true;" onclick="explode()">Expand all</a> <xsl:text>&#x20;</xsl:text>|<xsl:text>&#x20;</xsl:text><a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onmouseover="window.parent.status='Collapse all';return true;" onclick="contract()">Collapse all</a>

<p></p>
<xsl:text disable-output-escaping="yes">
&lt;script type="text/javascript" language="JavaScript"&gt;</xsl:text><xsl:value-of select="cdash/updates/javascript"/>
<xsl:text disable-output-escaping="yes">
&lt;/script&gt;
</xsl:text>

<script type="text/javascript" src="js/tree_init.js"></script>
<br/>
<a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onmouseover="window.parent.status='Expand all';return true;" onclick="explode()">Expand all</a> <xsl:text>&#x20;</xsl:text>|<xsl:text>&#x20;</xsl:text><a xmlns:lxslt="http://xml.apache.org/xslt" href="javascript:reload()" onmouseover="window.parent.status='Collapse all';return true;" onclick="contract()">Collapse all</a>
<br/>

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
