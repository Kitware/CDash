<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

  <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" encoding="UTF-8"/>

    <xsl:template name="headscripts">

    <xsl:comment><![CDATA[[if IE]>
    <script language="javascript" type="text/javascript" src="javascript/excanvas.js">
    </script>
    <![endif]]]></xsl:comment>

    <link rel="shortcut icon" href="favicon.ico"/>

    <!-- Include JQuery -->
    <script src="javascript/jquery-1.6.2.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/jquery.flot.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/jquery.flot.selection.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/tooltip.js" type="text/javascript" charset="utf-8"></script>
    <link type="text/css" rel="stylesheet" href="javascript/jquery.qtip.min.css" />
    <script src="javascript/jquery.qtip.min.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include Core Datepicker JavaScript -->
    <script src="javascript/jquery-ui-1.8.16.min.js" type="text/javascript" charset="utf-8"></script>
    <link type="text/css" rel="stylesheet" href="javascript/jquery-ui-1.8.16.css" />

    <!-- Include Calendar JavaScript -->
    <script src="javascript/cdashmenu.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include Core Datepicker Stylesheet -->
    <link rel="stylesheet" href="javascript/ui.datepicker.css" type="text/css" media="screen" title="core css file" charset="utf-8" />

    <!-- Include the sorting -->
    <script src="javascript/jquery.cookie.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/jquery.tablesorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/cdashTableSorter.js" type="text/javascript" charset="utf-8"></script>
    <script src="javascript/jquery.metadata.js" type="text/javascript" charset="utf-8"></script>

    <!-- Include jtooltip -->
    <script src="javascript/jtip.js" type="text/javascript" charset="utf-8"></script>

   <!-- include jqModal -->
  <script src="javascript/jqModal.js" type="text/javascript" charset="utf-8"></script>
  <link type="text/css" rel="stylesheet" media="all" href="javascript/jqModal.css" />

    </xsl:template>
</xsl:stylesheet>
