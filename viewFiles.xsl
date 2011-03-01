<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:include href="footer.xsl"/>
  <xsl:include href="headscripts.xsl"/>
  <xsl:include href="headeradminproject.xsl" />

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>
  <xsl:template match="/">
      <html>
      <head>
        <title><xsl:value-of select="cdash/title"/></title>
        <meta name="robots" content="noindex,nofollow" />
    <link rel="shortcut icon" href="favicon.ico"/>
        <link rel="StyleSheet" type="text/css">
          <xsl:attribute name="href"><xsl:value-of select="cdash/cssfile"/></xsl:attribute>
        </link>
        <xsl:call-template name="headscripts"/>
        <script src="javascript/cdashClient.js" type="text/javascript" charset="utf-8"></script>
      </head>

<body>
<xsl:call-template name="headeradminproject"/>

<!-- Message -->

<p><b>Site: </b><xsl:value-of select="/cdash/sitename" /></p>
<p><b>Build name: </b><a><xsl:attribute name="href">buildSummary.php?buildid=<xsl:value-of select="/cdash/buildid" /></xsl:attribute><xsl:value-of select="/cdash/buildname" /></a></p>
<p><b>Build start time: </b><xsl:value-of select="/cdash/buildstarttime" /></p>

<h3>Files submitted with this build:</h3>
<table border="2" cellpadding="4">
<tr><th>File</th><th>Size</th><th>MD5</th></tr>
<xsl:for-each select="/cdash/uploadfile">
  <tr>
  <td><a><xsl:attribute name="href">cdash/downloadFile.php?id=<xsl:value-of select="id" /></xsl:attribute><xsl:value-of select="filename" /></a></td>
  <td><xsl:value-of select="filesize" /></td>
  <td><xsl:value-of select="md5sum" /></td>
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
