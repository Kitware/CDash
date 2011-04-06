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
       <script src="javascript/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>

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

<!-- Message -->

<p><b>Site: </b><xsl:value-of select="/cdash/sitename" /></p>
<p><b>Build name: </b><a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="/cdash/buildid" /></xsl:attribute><xsl:value-of select="/cdash/buildname" /></a></p>
<p><b>Build start time: </b><xsl:value-of select="/cdash/buildstarttime" /></p>

<h3>Files submitted with this build:</h3>
<table class="tabb">
<thead class="table-heading1">
  <tr>
    <th id="sort_0">File</th>
    <th id="sort_1">Size</th>
    <th id="sort_2">SHA-1</th></tr>
</thead>
<xsl:for-each select="/cdash/uploadfile">
  <tr>
  <td><a><xsl:attribute name="href"><xsl:value-of select="href" /></xsl:attribute><xsl:value-of select="filename" /></a></td>
  <td><xsl:value-of select="filesize" /></td>
  <td><xsl:value-of select="sha1sum" /></td>
  </tr>
</xsl:for-each>
</table>


<!-- FOOTER -->
<br/><br/>
<xsl:call-template name="footer"/>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
