<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

<xsl:template name="coverageRow">
   <tr class="child_row">
      <xsl:if test="/cdash/dashboard/childview=0">
        <td align="left" class="paddt"><xsl:value-of select="site"/></td>

        <td align="left" class="paddt">
        <xsl:choose>
          <xsl:when test="childlink">
            <a>
              <xsl:attribute name="href">
                <xsl:value-of select="childlink"/>
              </xsl:attribute>
              <xsl:value-of select="buildname"/>
            </a>
          </xsl:when>
          <xsl:otherwise>
            <xsl:value-of select="buildname"/>
          </xsl:otherwise>
        </xsl:choose>
        </td>
      </xsl:if>
      <xsl:if test="/cdash/dashboard/childview=1">
        <td align="left" class="paddt">
          <xsl:if test="count(labels/label)=0">(none)</xsl:if>
          <xsl:if test="count(labels/label)=1"><xsl:value-of select="labels/label"/></xsl:if>
          <xsl:if test="count(labels/label)>1">(<xsl:value-of select="count(labels/label)"/> labels)</xsl:if>
        </td>
      </xsl:if>

      <td align="center">
        <xsl:attribute name="class">
          <xsl:choose>
            <xsl:when test="percentage >= percentagegreen">
              normal
              </xsl:when>
            <xsl:otherwise>
              warning
             </xsl:otherwise>
          </xsl:choose>
        </xsl:attribute>
        <a>
          <xsl:attribute name="href">
            <xsl:choose>
              <xsl:when test="childlink">
                <xsl:value-of select="childlink"/>
              </xsl:when>
              <xsl:otherwise>
                viewCoverage.php?buildid=<xsl:value-of select="buildid"/>
              </xsl:otherwise>
            </xsl:choose>
          </xsl:attribute>
          <xsl:value-of select="percentage"/>%
        </a>
        <xsl:if test="percentagediff > 0">
          <sub>+<xsl:value-of select="percentagediff"/>%</sub>
        </xsl:if>
        <xsl:if test="percentagediff &lt; 0">
          <sub><xsl:value-of select="percentagediff"/>%</sub>
        </xsl:if>
      </td>

      <td align="center" ><xsl:value-of select="pass"/>
      <xsl:if test="passdiff > 0"><sub>+<xsl:value-of select="passdiff"/></sub></xsl:if>
      <xsl:if test="passdiff &lt; 0"><sub><xsl:value-of select="passdiff"/></sub></xsl:if>
      </td>
      <td align="center" ><xsl:value-of select="fail"/>
      <xsl:if test="faildiff > 0"><sub>+<xsl:value-of select="faildiff"/></sub></xsl:if>
      <xsl:if test="faildiff &lt; 0"><sub><xsl:value-of select="faildiff"/></sub></xsl:if>
      </td>
      <td align="center">
      <xsl:if test="/cdash/dashboard/displaylabels=0">
       <xsl:attribute name="class">nob</xsl:attribute>
      </xsl:if>
      <span class="sorttime" style="display:none"><xsl:value-of select="datefull"/></span>
      <span class="builddateelapsed">
         <xsl:attribute name="alt"><xsl:value-of select="date"/></xsl:attribute>
         <xsl:value-of select="dateelapsed"/>
      </span>
      </td>

      <xsl:if test="/cdash/dashboard/childview=0">
        <xsl:if test="/cdash/dashboard/displaylabels=1">
          <td class="nob" align="left">
          <xsl:if test="count(labels/label)=0">(none)</xsl:if>
          <xsl:if test="count(labels/label)=1"><xsl:value-of select="labels/label"/></xsl:if>
          <xsl:if test="count(labels/label)>1">(<xsl:value-of select="count(labels/label)"/> labels)</xsl:if>
          </td>
        </xsl:if>
      </xsl:if>
   </tr>

</xsl:template>
</xsl:stylesheet>
