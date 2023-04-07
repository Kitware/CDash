<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">

<xsl:if test="string-length(cdash/warning)>0">
<b>Warning: <xsl:value-of select="cdash/warning"/></b><br/><br/>
</xsl:if>

<table width="100%"  border="0">
  <tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <form name="form1" method="post">
    <xsl:attribute name="action">manageBanner.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location = 'manageBanner.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option>
        <xsl:attribute name="value">-1</xsl:attribute>
        Choose...
        </option>

        <xsl:for-each select="cdash/availableproject">
        <option>
        <xsl:attribute name="value"><xsl:value-of select="id"/></xsl:attribute>
        <xsl:if test="selected=1">
        <xsl:attribute name="selected"></xsl:attribute>
        </xsl:if>
        <xsl:value-of select="name"/>
        </option>
        </xsl:for-each>
        </select>
      </form>
    </td>
  </tr>
</table>

<!-- If a project has been selected -->
<xsl:if test="count(cdash/project)>-1">
<form name="formnewgroup" method="post">
<xsl:attribute name="action">manageBanner.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Banner Message</strong></td>
  </tr>
  <tr>
    <td width="10%"></td>
    <td width="90%">
    <textarea name="message" cols="100" rows="3"><xsl:value-of select="cdash/project/text"/></textarea>
    </td>
  </tr>

  <tr>
    <td><div align="right"></div></td>
    <td><input type="submit" name="updateMessage" value="Update Message"/><br/><br/></td>
  </tr>
</table>
</form>


</xsl:if>

    </xsl:template>
</xsl:stylesheet>
