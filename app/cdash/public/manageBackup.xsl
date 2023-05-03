<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

    <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
    doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">

<table border="0" cellpadding="4" cellspacing="0" width="100%" class="tabb">
<tbody>
    <tr class="table-heading1"><td id="nob"><h3>Import</h3></td></tr>
    <tr class="treven"><td id="nob"><a href="import.php">[Import Dart1 Files]</a></td></tr>
    <tr class="trodd"><td id="nob"><a href="importBackup.php">[Import from current backup directory]</a></td></tr>
    <tr class="treven"><td id="nob"><a href="removeBuilds.php">[Remove builds]</a></td></tr>
</tbody>
</table>

    </xsl:template>
</xsl:stylesheet>
