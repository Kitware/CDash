<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

   <xsl:include href="coverageRow.xsl"/>
<xsl:template name="coreCoverage">

    <tr class="even parent_row">
      <td></td>
      <td align="left" class="paddt"></td>
      <td align="center">
        <xsl:attribute name="class"><xsl:choose>
          <xsl:when test="cdash/coreCoverage >= cdash/coreThreshold">
            normal
          </xsl:when>
          <xsl:otherwise>
            warning
          </xsl:otherwise>
        </xsl:choose></xsl:attribute>
        <xsl:value-of select="cdash/coreCoverage"/>%
      </td>
      <td align="center" >
        <xsl:value-of select="cdash/coreTested"/>
      </td>
      <td align="center" >
        <xsl:value-of select="cdash/coreUntested"/>
      </td>
      <td align="center"></td>
      <td class="nob" align="left"><b>All core packages</b>
        <div class="glyphicon glyphicon-folder-open"/>
      </td>
    </tr>

  <xsl:for-each select="cdash/buildgroup/coverage[core='1']">
    <xsl:call-template name="coverageRow"/>
  </xsl:for-each>

    <tr class="odd parent_row">
      <td></td>
      <td align="left" class="paddt"></td>
      <td align="center">
        <xsl:attribute name="class"><xsl:choose>
          <xsl:when test="cdash/nonCoreCoverage >= cdash/nonCoreThreshold">
            normal
          </xsl:when>
          <xsl:otherwise>
            warning
          </xsl:otherwise>
        </xsl:choose></xsl:attribute>
        <xsl:value-of select="cdash/nonCoreCoverage"/>%
      </td>
      <td align="center" >
        <xsl:value-of select="cdash/nonCoreTested"/>
      </td>
      <td align="center" >
        <xsl:value-of select="cdash/nonCoreUntested"/>
      </td>
      <td align="center"></td>
      <td class="nob" align="left"><b>All non-core packages</b>
        <div class="glyphicon glyphicon-folder-open"/>
      </td>
    </tr>

  <xsl:for-each select="cdash/buildgroup/coverage[core='0']">
    <xsl:call-template name="coverageRow"/>
  </xsl:for-each>

</xsl:template>
</xsl:stylesheet>
