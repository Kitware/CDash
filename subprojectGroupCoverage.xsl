<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

   <xsl:include href="coverageRow.xsl"/>
<xsl:template name="subprojectGroupCoverage">

  <xsl:for-each select="cdash/subprojectgroup">
    <xsl:variable name="groupid" select="id"/>
    <tr class="parent_row">
      <td></td>
      <td align="left" class="paddt"></td>

      <td align="center">
        <xsl:attribute name="class"><xsl:choose>
          <xsl:when test="coverage >= threshold">
            normal
          </xsl:when>
          <xsl:otherwise>
            warning
          </xsl:otherwise>
        </xsl:choose></xsl:attribute>
        <xsl:value-of select="coverage"/>%
      </td>

      <td align="center" >
        <xsl:value-of select="tested"/>
      </td>
      <td align="center" >
        <xsl:value-of select="untested"/>
      </td>

      <td align="center"></td>
      <td class="nob" align="left">
        <b><xsl:value-of select="name"/></b>
        <div class="glyphicon glyphicon-chevron-down"/>
      </td>
    </tr>

    <xsl:for-each select="/cdash/buildgroup/coverage[group=$groupid]">
      <xsl:call-template name="coverageRow"/>
    </xsl:for-each>

  </xsl:for-each>

</xsl:template>
</xsl:stylesheet>
