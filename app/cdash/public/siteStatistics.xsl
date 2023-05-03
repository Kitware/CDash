<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
    doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

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

    </xsl:template>
</xsl:stylesheet>
