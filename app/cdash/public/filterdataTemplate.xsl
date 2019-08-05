<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>


<!-- filterdata template -->

<xsl:template name="filterdata">

<div id="div_showfilters">
<xsl:if test="cdash/filterdata/showfilters = 0">
  <xsl:attribute name="style">display: none;</xsl:attribute>
</xsl:if>

<xsl:if test="cdash/filterdata/debug = 1">
Filter Definitions:<br/>
  <xsl:for-each select="cdash/filterdata/filterdefinitions/def">
    <xsl:value-of select="key"/>, <xsl:value-of select="uitext"/>, <xsl:value-of select="type"/>, <xsl:value-of select="valuelist"/>, <xsl:value-of select="defaultvalue"/><br/>
  </xsl:for-each>
<br/>
</xsl:if>

<form method="post" action="">
  <table id="tablefilters" cellpadding="0" cellspacing="0">
  <tr class="table-heading0" >
      <td colspan="10" class="nob">
          <h3>Filters</h3>
      </td>
  </tr>
  <tr class="trodd">
  <td>
  <span id="Match_filter">
    <xsl:if test="count(cdash/filterdata/filters/filter) = 1">
      Match the following rule:
      <input type="hidden" name="filtercombine"  id="id_filtercombine">
        <xsl:attribute name="value"><xsl:value-of select="cdash/filterdata/filtercombine"/></xsl:attribute>
      </input>
    </xsl:if>
    <xsl:if test="count(cdash/filterdata/filters/filter) > 1">
      Match<xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <select name="filtercombine" id="id_filtercombine">
      <option value="and">
        <xsl:if test="cdash/filterdata/filtercombine != 'or'">
          <xsl:attribute name="selected">selected</xsl:attribute>
        </xsl:if>
        all
      </option>
      <option value="or">
        <xsl:if test="cdash/filterdata/filtercombine = 'or'">
          <xsl:attribute name="selected">selected</xsl:attribute>
        </xsl:if>
        any
      </option>
      </select>
      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>of the following rules:
    </xsl:if>
    </span>
  </td>
  </tr>

  <xsl:for-each select="cdash/filterdata/filters/filter">
  <tr><xsl:attribute name="class"><xsl:if test="position() mod 2 = 0">trodd filterFields</xsl:if><xsl:if test="position() mod 2 = 1">treven filterFields</xsl:if></xsl:attribute>
  <xsl:attribute name="number"><xsl:value-of select="position()"/></xsl:attribute>
  <xsl:attribute name="id">filter<xsl:value-of select="position()"/></xsl:attribute>
  <td>
      <select onchange="filters_field_onchange(this)" onfocus="filters_field_onfocus(this)" onblur="filters_onblur(this)">
        <xsl:attribute name="id">id_field<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:attribute name="name">field<xsl:value-of select="position()"/></xsl:attribute>
      <xsl:variable name="xv_field" select="field"/>
      <xsl:for-each select="../../filterdefinitions/def">
        <option>
          <xsl:attribute name="value"><xsl:value-of select="key"/>/<xsl:value-of select="type"/></xsl:attribute>
          <xsl:if test="$xv_field = key">
            <xsl:attribute name="selected">selected</xsl:attribute>
          </xsl:if>
          <xsl:value-of select="uitext"/>
        </option>
      </xsl:for-each>
      </select>

      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <select onblur="filters_onblur(this)" onchange="filters_onchange(this)">
        <xsl:attribute name="id">id_compare<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:attribute name="name">compare<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:choose>
        <xsl:when test ="compare &gt;= 80">
          <option value="80"><xsl:if test="compare=80"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>-- choose comparison --</option>
          <option value="81"><xsl:if test="compare=81"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is</option>
          <option value="82"><xsl:if test="compare=82"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is not</option>
          <option value="83"><xsl:if test="compare=83"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is after</option>
          <option value="84"><xsl:if test="compare=84"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is before</option>
        </xsl:when>
        <xsl:when test ="compare &gt;= 60">
          <option value="60"><xsl:if test="compare=60"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>-- choose comparison --</option>
          <option value="63"><xsl:if test="compare=63"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>contains</option>
          <option value="64"><xsl:if test="compare=64"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>does not contain</option>
          <option value="61"><xsl:if test="compare=61"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is</option>
          <option value="62"><xsl:if test="compare=62"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is not</option>
          <option value="65"><xsl:if test="compare=65"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>starts with</option>
          <option value="66"><xsl:if test="compare=66"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>ends with</option>
        </xsl:when>
        <xsl:when test ="compare &gt;= 40">
          <option value="40"><xsl:if test="compare=40"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>-- choose comparison --</option>
          <option value="41"><xsl:if test="compare=41"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is</option>
          <option value="42"><xsl:if test="compare=42"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is not</option>
          <option value="43"><xsl:if test="compare=43"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is greater than</option>
          <option value="44"><xsl:if test="compare=44"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is less than</option>
        </xsl:when>
        <xsl:when test="compare &gt;= 0">
          <option value="0"><xsl:if test="compare=0"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>-- choose comparison --</option>
          <option value="1"><xsl:if test="compare=1"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is true</option>
          <option value="2"><xsl:if test="compare=2"><xsl:attribute name="selected">selected</xsl:attribute></xsl:if>is false</option>
        </xsl:when>
        </xsl:choose>
      </select>

      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <input type="text" size="60" onblur="filters_onblur(this)" onchange="filters_onchange(this)">
        <xsl:attribute name="id">id_value<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:attribute name="name">value<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:attribute name="value"><xsl:value-of select="value"/></xsl:attribute>
      </input>

      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <input type="button" value="-">
        <xsl:attribute name="name">remove<xsl:value-of select="position()"/></xsl:attribute>
        <xsl:attribute name="onclick">removeFilter(<xsl:value-of select="position()"/>)</xsl:attribute>
        <xsl:if test="last() = 1">
          <xsl:attribute name="disabled">disabled</xsl:attribute>
        </xsl:if>
      </input>
      <input type="button" value="+">
        <xsl:attribute name="onclick">addFilter(<xsl:value-of select="position()"/>)</xsl:attribute>
        <xsl:attribute name="name">add<xsl:value-of select="position()"/></xsl:attribute>
      </input>
  </td>
  </tr>

  <xsl:if test="../../debug != 0">
  <tr><xsl:attribute name="class"><xsl:if test="position() mod 2 = 0">treven</xsl:if><xsl:if test="position() mod 2 = 1">trodd</xsl:if></xsl:attribute>
  <td>
      field<xsl:value-of select="position()"/>: <xsl:value-of select="field"/>,
      compare<xsl:value-of select="position()"/>: <xsl:value-of select="compare"/>,
      value<xsl:value-of select="position()"/>: <xsl:value-of select="value"/>
  </td>
  </tr>
  </xsl:if>

  </xsl:for-each>

  <tr>
    <xsl:attribute name="class">
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 0">treven</xsl:if>
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 1">trodd</xsl:if>
    </xsl:attribute>
  <xsl:if test="cdash/filterdata/showlimit = 0">
  <td>
      <input type="hidden" id="id_limit" name="limit">
        <xsl:attribute name="value"><xsl:value-of select="cdash/filterdata/limit"/></xsl:attribute>
      </input>
  </td>
  </xsl:if>
  <xsl:if test="cdash/filterdata/showlimit = 1">
  <td>
      Limit results to
      <input type="text" size="3" onblur="filters_onblur(this)" onchange="filters_onchange(this)"
             id="id_limit" name="limit" align="center">
        <xsl:attribute name="value"><xsl:value-of select="cdash/filterdata/limit"/></xsl:attribute>
      </input>
      rows (0 for unlimited)
  </td>
  </xsl:if>
  </tr>

  <tr>
    <xsl:attribute name="class">
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 0">
      <xsl:if test="cdash/filterdata/showlimit = 0">treven</xsl:if>
      <xsl:if test="cdash/filterdata/showlimit = 1">trodd</xsl:if>
      </xsl:if>
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 1">
      <xsl:if test="cdash/filterdata/showlimit = 0">trodd</xsl:if>
      <xsl:if test="cdash/filterdata/showlimit = 1">treven</xsl:if>
      </xsl:if>
    </xsl:attribute>
  <td>
      <input type="hidden" name="filtercount" id="id_filtercount">
        <xsl:attribute name="value"><xsl:value-of select="count(cdash/filterdata/filters/filter)"/></xsl:attribute>
      </input>
      <input type="hidden" name="showfilters" id="id_showfilters" value="1" />
      <input type="submit" name="apply" value="Apply" />
      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <input type="submit" name="clear" value="Clear" />
      <xsl:text disable-output-escaping="yes">&amp;nbsp;</xsl:text>
      <input type="button" onclick="filters_create_hyperlink()" name="create_hyperlink" value="Create Hyperlink"/>
  </td>
  </tr>

  <tr>
    <xsl:attribute name="class">
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 0">
      <xsl:if test="cdash/filterdata/showlimit = 0">trodd</xsl:if>
      <xsl:if test="cdash/filterdata/showlimit = 1">treven</xsl:if>
      </xsl:if>
      <xsl:if test="count(cdash/filterdata/filters/filter) mod 2 = 1">
      <xsl:if test="cdash/filterdata/showlimit = 0">treven</xsl:if>
      <xsl:if test="cdash/filterdata/showlimit = 1">trodd</xsl:if>
      </xsl:if>
    </xsl:attribute>
  <td>
    <div id="div_filtersAsUrl"/>
  </td>
  </tr>
  </table>
</form>
</div>
</xsl:template>

</xsl:stylesheet>
