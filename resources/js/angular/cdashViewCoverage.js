$(document).ready(function() {
    $('#coverageTable').dataTable( {
      "aaSorting": [ [2,'asc'] ],
      "bProcessing": true,
      "bServerSide": true,
      "bAutoWidth" : false,
      "bSortClasses": false,
      "bFilter": false,
      "iDisplayLength":  25,
      "sPaginationType": "full_numbers",
      "sAjaxSource": "ajax/getviewcoverage.php",
      "fnServerParams": function ( aoData ) {
            aoData.push( { "name": "buildid", "value": $('#buildid').val() } );
            aoData.push( { "name": "status", "value": $('#coverageStatus').val() } );
            aoData.push( { "name": "dir", "value": $('#coverageDir').val() } );
            aoData.push( { "name": "ndirectories", "value": $('#coverageNDirectories').val() } );
            aoData.push( { "name": "nno", "value": $('#coverageNNo').val() } );
            aoData.push( { "name": "nzero", "value": $('#coverageNZero').val() } );
            aoData.push( { "name": "nlow", "value": $('#coverageNLow').val() } );
            aoData.push( { "name": "nmedium", "value": $('#coverageNMedium').val() } );
            aoData.push( { "name": "nsatisfactory", "value": $('#coverageNSatisfactory').val() } );
            aoData.push( { "name": "ncomplete", "value": $('#coverageNComplete').val() } );
            aoData.push( { "name": "nall", "value": $('#coverageNAll').val() } );
            aoData.push( { "name": "metricerror", "value": $('#coverageMetricError').val() } );
            aoData.push( { "name": "metricpass", "value": $('#coverageMetricPass').val() } );
            aoData.push( { "name": "userid", "value": $('#userid').val() } );
            aoData.push( { "name": "displaylabels", "value": $('#displaylabels').val() } );
            aoData.push( { "name": "showfilters", "value": $('#id_showfilters').val() } );
            aoData.push( { "name": "limit", "value": $('#id_limit').val() } );
            aoData.push( { "name": "filtercombine", "value": $('#id_filtercombine').val() } );
            filtercount = $('#id_filtercount').val();
            aoData.push( { "name": "filtercount", "value": filtercount } );
            for (idx = 1; idx <= filtercount; ++idx) {
              name_field = 'field' + idx;
              id_field = '#id_' + name_field;
              aoData.push( { "name": name_field, "value": $(id_field).val() } );
              name_compare = 'compare' + idx;
              id_compare = '#id_' + name_compare;
              aoData.push( { "name": name_compare, "value": $(id_compare).val() } );
              name_value = 'value' + idx;
              id_value = '#id_' + name_value;
              aoData.push( { "name": name_value, "value": $(id_value).val() } );
              }
            },
      "fnRowCallback": function( nRow, aData, iDisplayIndex ) {
            $('td:eq(3)', nRow).addClass($('td:eq(3) span', nRow).attr("class"));
            $('td:eq(3)', nRow).attr('align','center');

            if($('td:eq(4) span', nRow).attr("class"))
              {
              $('td:eq(4)', nRow).addClass($('td:eq(4) span', nRow).attr("class"));
              $('td:eq(4)', nRow).attr('align','center');
              }

            if($('td:eq(5) span', nRow).attr("class")) 
             {      
              $('td:eq(5)', nRow).addClass($('td:eq(5) span', nRow).attr("class"));
              $('td:eq(5)', nRow).attr('align','center');
             }
            return nRow;
            }
      } );
} );
