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
        <xsl:text disable-output-escaping="yes">
          &lt;script type="text/javascript"&gt;
              $(document).ready(function() {
                  $('.configure-toggle').click(function() {
                      $(this).next('.configure').toggle();
                  });
              });
          &lt;/script&gt;
        </xsl:text>
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

        <xsl:variable name="site" select="cdash/build/site"/>
        <xsl:variable name="siteid" select="cdash/build/siteid"/>
        <xsl:variable name="buildname" select="cdash/build/buildname"/>
        <xsl:variable name="hassubprojects" select="cdash/build/hassubprojects"/>


        <xsl:if test="$hassubprojects=0">
          <table border="0">
            <tr><td align="left"><b>Site: </b><a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="$siteid"/></xsl:attribute>
            <xsl:value-of select="$site"/></a></td></tr>
            <tr><td align="left"><b>Build Name: </b><xsl:value-of select="$buildname"/></td></tr>
            <tr><td align="left"><b>Configure Command: </b><xsl:value-of select="cdash/configures/configure/command"/></td></tr>
            <tr><td align="left"><b>Configure Return Value: </b><xsl:value-of select="cdash/configures/configure/status"/></td></tr>
            <tr><td align="left"><b>Configure Output:</b></td></tr>
            <tr><td align="left"><pre><xsl:value-of select="cdash/configures/configure/output"/></pre></td></tr>
          </table>
        </xsl:if>


        <xsl:if test="$hassubprojects=1">
          <table style="width:100%">
            <thead>
              <tr class="table-heading">
                <th align="left" width="15%">
                  Subproject
                </th>
                <th align="left" width="5%">
                  Error
                </th>
                <th align="left" width="5%">
                  Warn
                </th>
                <th align="left">
                  Configure
                </th>
              </tr>
            </thead>

            <tbody>
              <xsl:for-each select="cdash/configures/configure">
                <tr>
                  <td style="vertical-align:top"><xsl:value-of select="subprojectname"/></td>
                  <td style="vertical-align:top"><xsl:value-of select="configureerrors"/></td>
                  <td style="vertical-align:top"><xsl:value-of select="configurewarnings"/></td>
                  <td>
                    <span style="cursor:pointer" class="configure-toggle">View</span>
                    <table class="configure tabb" style="display:none"  border="0" cellpadding="4" cellspacing="0" width="100%">
                      <tr><td align="left"><b>Site: </b><a><xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="$siteid"/></xsl:attribute>
                      <xsl:value-of select="$site"/></a></td></tr>
                      <tr><td align="left"><b>Build Name: </b><xsl:value-of select="$buildname"/></td></tr>
                      <tr><td align="left"><b>Configure Command: </b><xsl:value-of select="command"/></td></tr>
                      <tr><td align="left"><b>Configure Return Value: </b><xsl:value-of select="status"/></td></tr>
                      <tr><td align="left"><b>Configure Output:</b></td></tr>
                      <tr><td align="left"><pre><xsl:value-of select="output"/></pre></td></tr>
                    </table>
                  </td>
                </tr>
              </xsl:for-each>
            </tbody>
          </table>
        </xsl:if>

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
