<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="footer.xsl"/>
   <xsl:include href="headerback.xsl"/> 
   <xsl:include href="header.xsl"/>
   <xsl:include href="local/header.xsl"/>

    <xsl:output method="html" encoding="iso-8859-1"/>
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

<table id="siteStatisticsTable" border="0" cellspacing="0" cellpadding="3" class="tabb">
<thead> 
  <tr class="table-heading1">
  <th id="sort_0">Site Name</th>
  <th id="sort_1" class="nob">Busy time</th>
  </tr>
</thead>  
<xsl:for-each select="cdash/site">
<tr>
  <td><b>
  <a>
  <xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute>
  <xsl:value-of select="name"/></a></b></td><td><xsl:value-of select="busytime"/></td>
</tr>
</xsl:for-each>
</table>

<br/>
<!-- FOOTER -->
<br/>
<xsl:call-template name="footer"/>
        </body>
      </html>
    </xsl:template>
</xsl:stylesheet>
