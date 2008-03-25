<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>
        
    <xsl:output method="xml" doctype-public="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="iso-8859-1"/>
    <xsl:template name="headerback" match="/">

<table width="100%" class="toptable" cellpadding="1" cellspacing="0">
  <tr>
    <td>
  <table width="100%" align="center" cellpadding="0" cellspacing="0" >
  <tr>
    <td height="22" class="topline"><xsl:text>&#160;</xsl:text></td>
  </tr>
  <tr>
    <td width="100%" align="left" class="topbg">

    <table width="100%" height="121" border="0" cellpadding="0" cellspacing="0" >
    <tr>
    <td width="195" height="121" class="topbgleft">
     <xsl:text>&#160;</xsl:text> <img  border="0" alt="" src="images/cdash.gif"/>
    </td>
    <td width="425" valign="top" class="insd">
    <div class="insdd">
      <span class="inn1"><xsl:value-of select="/cdash/menutitle"/></span><br />
      <span class="inn2"><xsl:value-of select="/cdash/menusubtitle"/></span>
      </div>
    </td>
    <td height="121" class="insd2"><xsl:text>&#160;</xsl:text></td>
   </tr>
  </table>

  </td>
    </tr>
  <tr>
    <td align="left" class="topbg2"><table width="100%" height="28" border="0" cellpadding="0" cellspacing="0">
 <tr>
  <td width="631" align="left" class="bgtm"><ul id="Nav" class="nav">
<li id="Dartboard">
<a><xsl:attribute name="href"><xsl:value-of select="/cdash/backurl"/></xsl:attribute>BACK</a><ul>
</ul>
</li>
</ul>
</td>
  <td height="28" class="insd3"><xsl:text>&#160;</xsl:text></td>
 </tr>
</table></td>
  </tr>
</table></td>
  </tr>
</table>


    </xsl:template>
</xsl:stylesheet>
