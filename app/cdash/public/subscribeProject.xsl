<xsl:stylesheet
    xmlns:xsl="http://www.w3.org/1999/XSL/Transform" version='1.0'>

   <xsl:output method="xml" indent="yes"  doctype-public="-//W3C//DTD XHTML 1.0 Transitional//EN"
   doctype-system="http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd" />

    <xsl:template match="/">
          <script type="text/javascript">
          function saveChanges()
            {
              $("#changesmade").show();
            }
         </script>


<xsl:if test="string-length(cdash/warning)>0">
<xsl:value-of select="cdash/warning"/>
</xsl:if>

<table width="100%"  border="0">
<tr>
    <td width="10%"><div align="right"><strong>Project:</strong></div></td>
    <td width="90%" >
    <xsl:if test="count(cdash/availableproject)>0">
    <form name="form1" method="post">
    <xsl:attribute name="action">subscribeProject.php?projectid=<xsl:value-of select="cdash/project/id"/></xsl:attribute>
    <select onchange="location='subscribeProject.php?projectid='+this.options[this.selectedIndex].value;" name="projectSelection">
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
     </xsl:if>
    <xsl:if test="count(cdash/availableproject)=0">
      <xsl:value-of select="cdash/project/name"/>
    </xsl:if>
    </td>
  </tr>
<tr>
<td colspan="2">
<form name="form1" enctype="multipart/form-data" method="post" action="">
  <div id="wizard">
      <ul>
          <li>
            <a class="cdash-link" href="#fragment-3"><span>Email Notifications</span></a></li>
          <li>
            <a class="cdash-link" href="#fragment-4"><span>Email Category</span></a></li>
      </ul>
    <div id="fragment-3" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
         <xsl:if test="/cdash/project/emailbrokensubmission=0">
          <tr>
            <td></td>
            <td><font color="#900000">*This project has not been configured to send emails.
             <xsl:choose>
               <xsl:when test="/cdash/role>1"><a>
               <xsl:attribute name="href">project/<xsl:value-of select="/cdash/project/id"/>/edit#Email</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
           <tr>
            <td></td>
            <td><b>Email me:</b></td>
           </tr>
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="0">
             <xsl:if test="/cdash/emailtype=0">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> never (this is not recommended)
           </td>
          </tr>
           <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="1">
             <xsl:if test="/cdash/emailtype=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when <b>my checkins</b> are causing problems in <b>any sections</b> of the dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="2">
             <xsl:if test="/cdash/emailtype=2">
             <xsl:attribute name="checked">
             </xsl:attribute>
             </xsl:if></input> when <b>any checkins</b> are causing problems in the <b>Nightly section</b> of the dashboard
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="radio" onchange="saveChanges();" name="emailtype" value="3">
             <xsl:if test="/cdash/emailtype=3"><xsl:attribute name="checked"></xsl:attribute></xsl:if>
             </input> when <b>any checkins</b> are causing problems in <b>any sections</b> of the dashboard
           </td>
          </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailsuccess" value="1">
             <xsl:if test="/cdash/emailsuccess=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when <b>my checkins</b> are fixing build errors, warnings or tests
           </td>
         </tr>
         <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailmissingsites" value="1">
             <xsl:if test="/cdash/emailmissingsites=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> when expected sites are not submitting
           </td>
          </tr>
        </table>
    </div>
    <div id="fragment-4" class="tab_content" >
      <div class="tab_help"></div>
        <table width="800" >
        <xsl:if test="/cdash/project/emailbrokensubmission=0">
          <tr>
            <td></td>
            <td><font color="#900000">*This project has not been configured to send emails.
             <xsl:choose>
               <xsl:when test="/cdash/role>1"><a>
               <xsl:attribute name="href">project/<xsl:value-of select="/cdash/project/id"/>/edit#Email</xsl:attribute>Change the project settings.
               </a></xsl:when>
               <xsl:otherwise> Contact the project administrator.</xsl:otherwise>
             </xsl:choose>
            </font></td>
          </tr>
          </xsl:if>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_update" value="2">
             <xsl:if test="/cdash/emailcategory_update=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Update
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_configure" value="4">
             <xsl:if test="/cdash/emailcategory_configure=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Configure
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_warning" value="8">
             <xsl:if test="/cdash/emailcategory_warning=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Warning
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_error" value="16">
             <xsl:if test="/cdash/emailcategory_error=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Error
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_test" value="32">
             <xsl:if test="/cdash/emailcategory_test=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Test
           </td>
          </tr>
          <tr>
            <td></td>
            <td ><input type="checkbox" onchange="saveChanges();" name="emailcategory_dynamicanalysis" value="64">
             <xsl:if test="/cdash/emailcategory_dynamicanalysis=1">
             <xsl:attribute name="checked"></xsl:attribute>
             </xsl:if>
             </input> Dynamic Analysis
           </td>
          </tr>
        </table>
    </div>
 </div>

  <br/>
  <div style="width:900px;margin-left:auto;margin-right:auto;text-align:right;">
  <table width="100%" border="0">
  <tr>
    <td><span id="changesmade" style="color:red;display:none;">*Changes need to be updated </span>
    <input type="submit" name="updatesubscription" value="Update Subscription"/></td>
   </tr>
  </table>
  </div>

</form>
</td>
</tr>
</table>
    </xsl:template>
</xsl:stylesheet>
