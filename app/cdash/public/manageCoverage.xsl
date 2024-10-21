<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">

<xsl:if test="string-length(cdash/warning)>0">
<br/><b><xsl:value-of select="cdash/warning"/></b><br/>
</xsl:if>
<br/>
<table width="100%"  border="0">
  <tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <form name="form1" method="post">
    <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location = 'manageCoverage.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
        <option value="0">Choose project</option>
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
  <tr>
    <td width="10%"><div align="right"><strong>Build:</strong></div></td>
    <td width="90%" >
    <form name="form_build" method="post">
    <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select name="buildSelection">
    <xsl:attribute name="onchange">location='manageCoverage.php?buildid='+this.options[this.selectedIndex].value+'&#38;projectid=<xsl:value-of select="cdash/project/id"/>';</xsl:attribute>
        <option value="0">Choose build</option>
        <xsl:for-each select="cdash/project/build">
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
<xsl:if test="count(cdash/project)>0">

<form name="formnewgroup" method="post">
<xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="cdash/project/id"/>&#38;buildid=<xsl:value-of select="cdash/project/buildid"/></xsl:attribute>

<table width="100%"  border="0">
  <tr>
    <td><div align="right"></div></td>
    <td bgcolor="#DDDDDD"><strong>Coverage files</strong></td>
  </tr>
  <tr>
    <td width="10%"></td>
    <td width="90%">

    <table id="manageCoverageTable" cellspacing="0" class="tabb">
    <thead>
    <tr class="table-heading1">
      <th id="sort_0" width="80%">Filename</th>
      <th id="sort_1" width="20%">Priority</th>
    </tr>
    </thead>
    <tbody>
    <xsl:for-each select="cdash/project/file">
    <tr>
      <td><input type="checkbox">
      <xsl:attribute name="name">selectionFiles[<xsl:value-of select="fileid"/>]</xsl:attribute>
      <xsl:attribute name="value"><xsl:value-of select="fullpath"/></xsl:attribute>
      </input>
      <xsl:value-of select="fullpath"/></td>
      <td>
      <form name="form_priority" method="post">
      <xsl:attribute name="action">manageCoverage.php?projectid=<xsl:value-of select="/cdash/project/id"/>&#38;buildid=<xsl:value-of select="/cdash/project/buildid"/></xsl:attribute>
      <select onchange="this.form.submit();" name="prioritySelection">
      <option value="0">Choose Priority</option>
      <option value="1"><xsl:if test="priority=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Low</option>
      <option value="2"><xsl:if test="priority=2"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Medium</option>
      <option value="3"><xsl:if test="priority=3"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>High</option>
      <option value="4"><xsl:if test="priority=4"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Urgent</option>
      </select>
      <input type="hidden" name="fullpath"><xsl:attribute name="value"><xsl:value-of select="fullpath"/></xsl:attribute></input>
      </form>
      </td>
    </tr>
    </xsl:for-each>
    </tbody>
    </table>
    </td>
  </tr>
  <tr>
    <td><div align="right"></div></td>
    <td>
      <select name="prioritySelectedSelection">
      <option value="0">Choose Priority</option>
      <option value="1"><xsl:if test="priority=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Low</option>
      <option value="2"><xsl:if test="priority=2"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Medium</option>
      <option value="3"><xsl:if test="priority=3"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>High</option>
      <option value="4"><xsl:if test="priority=4"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>Urgent</option>
      </select>
      <input type="submit" name="changePrioritySelected" value="Change priority of selected files >>"/></td>
  </tr>
</table>
</form>


</xsl:if>

<script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
<script src="js/cdashManageCoverageSorter.js" type="text/javascript" charset="utf-8"></script>

    </xsl:template>
</xsl:stylesheet>
