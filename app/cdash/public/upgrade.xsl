<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"/>
    <xsl:template match="/">

<xsl:if test="string-length(cdash/alert)>0">
<b><xsl:value-of select="cdash/alert"/></b>
</xsl:if>
<form name="form1" enctype="multipart/form-data" method="post" action="">
<table border="0">
  <tr>
    <td><div align="right">Assign unknown builds to group based on type:</div></td>
    <td><div align="left"><input type="submit" name="AssignBuildToDefaultGroups" value="Assign builds to default groups"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Fix build group based on build rules:</div></td>
    <td><div align="left"><input type="submit" name="FixBuildBasedOnRule" value="Fix build groups"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Delete builds with wrong start date:</div></td>
    <td><div align="left"><input type="submit" name="CheckBuildsWrongDate" value="Check builds"/><input type="submit" name="DeleteBuildsWrongDate" value="Delete builds"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Compute test timing:</div></td>
    <td><div align="left">for the last <input type="text" name="TestTimingDays" size="2" value="4"/> days <input type="submit" name="ComputeTestTiming" value="Compute test timing"/></div></td>
  </tr>
  <tr>
    <td><div align="right">Compute update statistics:</div></td>
    <td><div align="left">for the last <input type="text" name="UpdateStatisticsDays" size="2" value="4"/> days <input type="submit" name="ComputeUpdateStatistics" value="Compute update statistics"/></div></td>
  </tr>
</table>
</form>
    </xsl:template>
</xsl:stylesheet>
