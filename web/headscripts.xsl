<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>

    <xsl:template name="headscripts">

    <xsl:comment><![CDATA[[if IE]>
    <script language="javascript" type="text/javascript" src="js/excanvas.js">
    </script>
    <![endif]]]></xsl:comment>

    <link rel="shortcut icon" href="favicon.ico"/>

    <!-- Include JQuery -->
    <script src="js/jquery-1.10.2.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.flot.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.flot.time.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.flot.selection.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/tooltip.js" type="text/javascript" charset="utf-8"></script>
    <link type="text/css" rel="stylesheet" href="js/jquery.qtip.min.css" />
    <script src="js/jquery.qtip.min.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include Core Datepicker JavaScript -->
    <script src="js/jquery-ui-1.10.4.min.js" type="text/javascript" charset="utf-8"></script>
    <link type="text/css" rel="stylesheet" href="js/jquery-ui-1.8.16.css" />

    <!-- Include Calendar JavaScript -->
    <script src="js/cdashmenu.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include the sorting -->
    <script src="js/jquery.cookie.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.dataTables.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/cdashTableSorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="js/jquery.metadata.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include jtooltip -->
    <script src="js/jtip.js" type="text/javascript" charset="utf-8"></script>

   <!-- include jqModal -->
  <script src="js/jqModal.js" type="text/javascript" charset="utf-8"></script>
  <link type="text/css" rel="stylesheet" media="all" href="js/jqModal.css" />
  <link type="text/css" rel="stylesheet" media="all" href="js/jquery.dataTables.css" />

  <!-- call the local/headerscripts to add new functionalities -->
  <xsl:if test="/cdash/uselocaldirectory=1">
    <xsl:call-template name="headscripts_local"/>
  </xsl:if>

    </xsl:template>
</xsl:stylesheet>
