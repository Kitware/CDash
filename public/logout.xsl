<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:template name="logout" />
    <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
                doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />
    <xsl:template match="/">
        <a href="logout" onclick="event.preventDefault(); document.getElementById('logout-form').submit()">Log Out</a>
        <form id="logout-form" action="logout" method="POST">
            <input type="hdden" name="_token" value="{{ csrf_token() }}" />
        </form>
    </xsl:template>

</xsl:stylesheet>
