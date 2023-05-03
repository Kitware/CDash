<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">

<div style="color: red;"><xsl:value-of select="cdash/warning" /></div>
<div style="color: green;"><xsl:value-of select="cdash/message" /></div>
<br/>

<!-- Main -->
<form method="post" action="" name="loginform">
<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
<tr class="table-heading">
  <td width="10%" class="nob"><div align="right"></div></td>
  <td width="90%" class="nob"><div align="left"><b>Enter your email address you registered with CDash.</b></div></td>
</tr>
<tr class="table-heading">
  <td width="10%" class="nob"><div align="right"> Email: </div></td>
  <td  width="90%" class="nob"><input class="textbox" name="email" size="40"/></td>
</tr>
<tr class="table-heading">
  <td width="10%" class="nob"></td>
  <td width="90%" class="nob"><input type="submit" value="Recover password &gt;&gt;" name="recover" class="textbox"/>
  </td>
</tr>
</tbody>
</table>
</form>

    </xsl:template>
</xsl:stylesheet>
