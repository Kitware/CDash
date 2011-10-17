<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="local/footer.xsl"/>
   <xsl:include href="headerback.xsl"/>

    <xsl:output method="html" encoding="UTF-8"/>
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
            <xsl:call-template name="headerback"/>
<br/>

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1"><td id="nob"><h3>Import</h3></td></tr>
    <tr class="trodd"><td id="nob"><a href="backup.php">[Backup database]</a></td></tr>
    <tr class="treven"><td id="nob"><a href="import.php">[Import Dart1 Files]</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="importBackup.php">[Import from current backup directory]</a></td></tr>
    <tr class="treven"><td id="nob"><a href="removeBuilds.php">[Remove builds]</a></td></tr>
</tbody>
</table>



<br/>
<!-- FOOTER -->
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
