<xsl:stylesheet
  xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:include href="header.xsl"/>
   <xsl:include href="footer.xsl"/>
<!--    <xsl:include href="headscripts.xsl"/> -->
<!--    <xsl:include href="headeradminproject.xsl"/> -->

    <!-- Local includes -->
   <xsl:include href="local/header.xsl"/>
   <xsl:include href="local/footer.xsl"/>
<!--    <xsl:include href="local/headscripts.xsl"/> -->
<!--    <xsl:include href="local/headeradminproject.xsl"/> -->
   <xsl:include href="filterdataTemplate.xsl"/>
<xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

<xsl:template match="/">
<html>
<head>
  <title><xsl:value-of select="cdash/title"/></title>
  <meta name="robots" content="noindex,nofollow" />
  <link rel="StyleSheet" type="text/css">
    <xsl:attribute name="href">
      <xsl:value-of select="cdash/cssfile"/>
    </xsl:attribute>
  </link>
  <xsl:call-template name="headscripts"/>
  <script src="javascript/je_compare-1.0.0.min.js" type="text/javascript" charset="utf-8"></script>
  <script src="javascript/cdashFilters.js" type="text/javascript" charset="utf-8"></script>
  <script src="javascript/cdashTestGraph.js" type="text/javascript" charset="utf-8"></script>
</head>
<script type="text/javascript" language='javascript'>
function showAllGraphs(zoomout)
  {
  <xsl:for-each select="/cdash/test">
     <xsl:for-each select="site">
              displaygraph_viewerphp('<xsl:value-of select="mname"/>',
                                     '<xsl:value-of select="tname"/>',
                                     '<xsl:value-of select="name"/>',
                                     '<xsl:value-of select="mname"/><xsl:value-of select="tname"/><xsl:value-of select="name"/>',
                                     '<xsl:value-of select="/cdash/starttime"/>',
                                     '<xsl:value-of select="/cdash/endtime"/>',
                                     zoomout);
     </xsl:for-each>
  </xsl:for-each>
  }

 $(function() {
    $( "#starttime" ).datepicker({
      defaultDate: "+0d",
      changeMonth: true,
      numberOfMonths: 1,
      dateFormat: "yy-mm-dd",
      onClose: function( selectedDate ) {
        $( "#endtime" ).datepicker( "option", "minDate", selectedDate );
      }
    });
    $( "#endtime" ).datepicker({
      defaultDate: "+0d",
      changeMonth: true,
      numberOfMonths: 1,
      dateFormat: "yy-mm-dd",
      onClose: function( selectedDate ) {
        $( "#starttime" ).datepicker( "option", "maxDate", selectedDate );
      }
    });
  });

 
</script>
<body bgcolor="#ffffff">

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="header_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="header"/>
</xsl:otherwise>
</xsl:choose>
Please set filters to display all graphs based on the search results:
<a id="label_showfilters" href="javascript:filters_toggle();">
<xsl:if test="cdash/filterdata/showfilters = 0">Set Filters <xsl:if test="cdash/filtercount > 0"> (<xsl:value-of select="cdash/filtercount"/>)</xsl:if></xsl:if>
<xsl:if test="cdash/filterdata/showfilters != 0">Hide Filters</xsl:if>
</a>

<!-- Filters? -->
<xsl:if test="count(cdash/filterdata) = 1">
  <xsl:call-template name="filterdata" select="."/>
</xsl:if>

  <br/>
  <xsl:if test="/cdash/filtercount > 0">
    <center>
      <a href="javascript:showAllGraphs(false)">Display All Graphs</a> -
      <a href="javascript:showAllGraphs(true)">Zoom Out All Graphs (This may take a while!)</a>
    </center>
  </xsl:if>
<table width="800px" align='center' border='1'>
  <xsl:for-each select="/cdash/test">
     <tr><xsl:attribute name="class"><xsl:if test="position() mod 2 = 0">treven</xsl:if><xsl:if test="position() mod 2 = 1">trodd</xsl:if></xsl:attribute>
     <td>
           <b><xsl:value-of select="name" /> - <xsl:value-of select="mname" /></b>
     </td></tr>
     <xsl:for-each select="site">
      <tr><xsl:attribute name="class"><xsl:if test="position() mod 2 = 0">treven</xsl:if><xsl:if test="position() mod 2 = 1">trodd</xsl:if></xsl:attribute><td>
            <i><xsl:value-of select="name" /></i> -
            <a>
            <xsl:attribute name="href">
              javascript:displaygraph_viewerphp('<xsl:value-of select="mname"/>','<xsl:value-of select="tname"/>','<xsl:value-of select="name"/>','<xsl:value-of select="mname"/><xsl:value-of select="tname"/><xsl:value-of select="name"/>','<xsl:value-of select="/cdash/starttime"/>','<xsl:value-of select="/cdash/endtime"/>',false)
            </xsl:attribute>
            Show Graph </a><br />
            <!-- Graph holder -->
            <div id="graph"></div>
            <div>
              <xsl:attribute name="id">graph_options_<xsl:value-of select="mname"/><xsl:value-of select="tname"/><xsl:value-of select="name"/></xsl:attribute>
            </div>
            <div>
              <xsl:attribute name="id">graph_<xsl:value-of select="mname"/><xsl:value-of select="tname"/><xsl:value-of select="name"/></xsl:attribute>
            </div>
            <div>
              <xsl:attribute name="id">graph_holder_<xsl:value-of select="mname"/><xsl:value-of select="tname"/><xsl:value-of select="name"/></xsl:attribute>
            </div>
      </td></tr>
     </xsl:for-each>
  </xsl:for-each>
</table>

<!-- FOOTER -->
<br/>

<xsl:choose>
<xsl:when test="/cdash/uselocaldirectory=1">
  <xsl:call-template name="footer_local"/>
</xsl:when>
<xsl:otherwise>
  <xsl:call-template name="footer"/>
</xsl:otherwise>
</xsl:choose>
</body>
</html>
</xsl:template>
</xsl:stylesheet>
