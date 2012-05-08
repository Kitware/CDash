$(document).ready(function() {

    $('#coverageTable').dataTable(
      {
      "aaSorting": [ [2,'asc'] ],
      "bProcessing": true,
      "bServerSide": true,
      "bAutoWidth" : false,
      "bSortClasses": false,
      "iDisplayLength":  25,
      "sPaginationType": "full_numbers",
      "sAjaxSource": "ajax/getviewcoverage.php",
       "fnServerParams": function ( aoData ) {
            aoData.push( { "name": "buildid", "value": $('#buildid').val() } );
            aoData.push( { "name": "status", "value": $('#coverageStatus').val() } );
            aoData.push( { "name": "nno", "value": $('#coverageNNo').val() } );
            aoData.push( { "name": "nzero", "value": $('#coverageNZero').val() } );
            aoData.push( { "name": "nlow", "value": $('#coverageNLow').val() } );
            aoData.push( { "name": "nmedium", "value": $('#coverageNMedium').val() } );
            aoData.push( { "name": "nsatisfactory", "value": $('#coverageNSatisfactory').val() } );
            aoData.push( { "name": "ncomplete", "value": $('#coverageNComplete').val() } );
            aoData.push( { "name": "metricerror", "value": $('#coverageMetricError').val() } );
            aoData.push( { "name": "metricpass", "value": $('#coverageMetricPass').val() } );
            aoData.push( { "name": "userid", "value": $('#userid').val() } );
            aoData.push( { "name": "displaylabels", "value": $('#displaylabels').val() } );
        },
      "fnRowCallback": function( nRow, aData, iDisplayIndex ) {
            $('td:eq(3)', nRow).addClass($('td:eq(3) span', nRow).attr("class"));
            $('td:eq(3)', nRow).attr('align','center');
            return nRow;
        }
      }
     );
} );
