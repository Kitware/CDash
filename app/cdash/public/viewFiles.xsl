<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

<b>Site: </b><xsl:value-of select="/cdash/sitename" /><br/>
<b>Build name: </b><a><xsl:attribute name="href">build/<xsl:value-of select="/cdash/buildid" /></xsl:attribute><xsl:value-of select="/cdash/buildname" /></a><br/>
<b>Build start time: </b><xsl:value-of select="/cdash/buildstarttime" /><br/>

<h3>URLs or Files submitted with this build</h3>

<xsl:if test="count(/cdash/uploadurl)>0">
  <table id="filesTable" class="tabb">
  <thead class="table-heading1">
    <tr>
      <th id="sort_0">URL</th>
    </tr>
  </thead>
  <xsl:for-each select="/cdash/uploadurl">
    <tr>
    <td><a><xsl:attribute name="href"><xsl:value-of select="filename" /></xsl:attribute><xsl:value-of select="filename" />
    </a></td>
    </tr>
  </xsl:for-each>
  </table>
  <br/>
</xsl:if>

<xsl:if test="count(/cdash/uploadfile)>0">
  <table id="filesTable" class="tabb">
  <thead class="table-heading1">
    <tr>
      <th id="sort_0">File</th>
      <th id="sort_1">Size</th>
      <th id="sort_2">SHA-1</th></tr>
  </thead>
  <xsl:for-each select="/cdash/uploadfile">
    <tr>
    <td><a><xsl:attribute name="href"><xsl:value-of select="href" /></xsl:attribute><img src="img/package.png" alt="Files" border="0"/> <xsl:value-of select="filename" />
    </a></td>
    <td><span style="display:none"><xsl:value-of select="filesize" /></span><xsl:value-of select="filesizedisplay" /></td>
    <td><xsl:value-of select="sha1sum" /></td>
    </tr>
  </xsl:for-each>
  </table>
</xsl:if>

<script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
<script language="javascript" type="text/javascript" src="js/cdashUploadFilesSorter.js"></script>
</xsl:template>
</xsl:stylesheet>
