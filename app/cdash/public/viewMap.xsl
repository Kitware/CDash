<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

 <!-- HEADER -->
   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

<xsl:if test="string-length(cdash/googlemapkey)>0">
 <script type="text/javascript">
      <xsl:attribute name="src">http://maps.google.com/maps?file=api&amp;v=2&amp;key=<xsl:value-of select="cdash/dashboard/googlemapkey"/></xsl:attribute>
   </script>
  <xsl:text disable-output-escaping="yes">
    &lt;script type="text/javascript"&gt;
      // Creates a marker whose info window displays the letter corresponding
      // to the given index.
      function createMarker(point,title)
        {
        var marker = new GMarker(point);
        GEvent.addListener(marker, "click", function()
          {
          marker.openInfoWindowHtml(title);
          });
        return marker;
      }

    function load() {
      if (GBrowserIsCompatible()) {
        var map = new GMap2(document.getElementById("map"));
        map.setCenter(new GLatLng(37.4419, -30.00), 2);
        map.addControl(new GLargeMapControl());
  </xsl:text>
        <xsl:for-each select="cdash/site">
        <xsl:if test="string-length(latitude)>0">
        var point = new GLatLng(<xsl:value-of select="latitude"/>,<xsl:value-of select="longitude"/>);
        map.addOverlay(createMarker(point,'<xsl:value-of select="name"/>'));
        </xsl:if>
        </xsl:for-each><xsl:text disable-output-escaping="yes">
      }
    }
    &lt;/script&gt;
    </xsl:text>
</xsl:if>

<div style="position:relative;">
<div style="float:left; padding-right:20px;">
<!-- Display the table of maintainers -->
<table id="maintainerTable" width="100%" cellspacing="0" class="tabb">
<thead>
  <tr class="table-heading1">
    <th id="sort_0">Site Name</th>
    <th id="sort_1" >Maintainer</th>
    <th id="sort_2" >Processor Speed</th>
    <th id="sort_3" class="nob"># Processors</th>
  </tr>
</thead>
<xsl:for-each select="cdash/site">
<tr>
<td><center>
<a>
<xsl:attribute name="href">viewSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute>
<xsl:value-of select="name"/></a></center></td>
<td><center>
<xsl:if test="string-length(maintainer_name)>1">
<xsl:value-of select="maintainer_name"/>
</xsl:if>
<xsl:if test="string-length(maintainer_name)=1">
<a>
<xsl:attribute name="href">editSite.php?siteid=<xsl:value-of select="id"/></xsl:attribute>
[claim this site]
</a>
</xsl:if>
</center></td>
<td><center><xsl:value-of select="processor_speed"/></center></td>
<td><center><xsl:value-of select="numberphysicalcpus"/></center></td>
</tr>
</xsl:for-each>
</table>
</div>

<xsl:if test="string-length(cdash/googlemapkey)>0">
  <!-- Display the map -->
  <div id="map" style="width: 700px; height: 400px; float:left;"></div>
</xsl:if>
</div>

<script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
<script language="javascript" type="text/javascript" src="js/cdashSiteSorter.js"></script>

    </xsl:template>
</xsl:stylesheet>
