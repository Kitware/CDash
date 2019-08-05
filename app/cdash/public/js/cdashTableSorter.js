$(document).ready(function() {
  /** Load the help page */
  var helpfile = 'help.html';
  if(this.getElementById('cdashuselocal'))
    {
    helpfile = 'local/help.html';
    }
  $('#help').jqm({ajax: helpfile, trigger: 'a.helptrigger'});
  if(this.getElementById('projectname'))
    {
    var projectname = this.getElementById('projectname').value;
    $('#groupsdescription').jqm({ajax: 'groupsDescription.php?project='+projectname, trigger: 'a.grouptrigger'});

    // If we are in advanced view, we setup the advanced view
    if($.cookie('cdash_'+projectname+'_advancedview') == '1')
      {
      $('.advancedview').html("Simple View");
      $('.advancedviewitem').show();

      $timeheaders = $(".timeheader");
      $timeheaders.each(
        function(index) {
         var colspan = $(this).attr('colspan');
         colspan++;
         $(this).attr('colspan',colspan);
        });
      }
    }

  /** qtip on the build time elapsed */
  $('.builddateelapsed').qtip({
     content: {attr: 'alt'},
     style: {classes: 'ui-tooltip-blue'},
     position: {
      my: 'top right',  // Position my top left...
      at: 'bottom left' // at the bottom right of...
      }
   })

  /** qtip on the build time elapsed */
  $('.buildinfo').qtip({
     content: {attr: 'alt'},
     style: {classes: 'ui-tooltip-blue'},
     position: {
      my: 'top left',  // Position my top left...
      at: 'bottom right' // at the bottom right of...
      }
   })

  /** Show/Hide time */
  $('.advancedview').click(function()
    {
    var projectname = document.getElementById('projectname').value;

    if ($('.advancedview').html() == "Simple View")
      {
      $('.advancedview').html("Advanced View");
      $('.advancedviewitem').hide();
      $timeheaders = $(".timeheader");
      $timeheaders.each(
        function(index) {
         var colspan = $(this).attr('colspan');
         colspan--;
         $(this).attr('colspan',colspan);
        });
      $.cookie('cdash_'+projectname+'_advancedview','0');
      }
    else
      {
      $('.advancedview').html("Simple View");
      $('.advancedviewitem').show();

      $timeheaders = $(".timeheader");
      $timeheaders.each(
        function(index) {
         var colspan = $(this).attr('colspan');
         colspan++;
         $(this).attr('colspan',colspan);
        });
      $.cookie('cdash_'+projectname+'_advancedview','1');
      }
      return false;
    });

  /** Build name */
  $.tablesorter.addParser({
      // set a unique id
      id: 'buildname',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
            // format your data for normalization
            var t = s;
            var i = s.indexOf("<a ");
            if(i>0)
              {
              var j = s.indexOf(">",i);
              var k = s.indexOf("</a>",j);
              t = s.substr(j+1,k-j-1);
              }
            return t.toLowerCase();
        },
        // set type, either numeric or text
        type: 'text'
    });

  /** Update */
  $.tablesorter.addParser({
      // set a unique id
      id: 'numericvalue',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
          // Extract first integer from input string.
          // This gets rid of the little +1 / -1 that show changes
          // from the previous days dashboard.
          if (typeof(s) == "string") {
            var numArray = s.match(/\d+/);
            if (numArray != null) {
              s = numArray[0];
            }
          }
          return s;
        },
        // set type, either numeric or text
        type: 'numeric'
    });

  /** percent for coverage */
  $.tablesorter.addParser({
      // set a unique id
      id: 'percentage',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
            s = s.trim();
            // format your data for normalization
            var i = s.indexOf("<a");
            if(i == -1)
              {
              var t = s.substr(0,s.length-1);
              return t.toLowerCase();
              }
            var j = s.indexOf(">",i);
            var k = s.indexOf("</a>",j);
            var t = s.substr(j+1,k-j-2);
            return t.toLowerCase();
        },
        // set type, either numeric or text
        type: 'numeric'
    });

  /** elapsed time */
  $.tablesorter.addParser({
      // set a unique id
      id: 'elapsedtime',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
            // extract the numerical time value from our custom format
            var t = s.match(/sorttime=([0-9.]+)#/);
            if (t == null) {
              return s;
            }
            return t[1];
        },
        // set type, either numeric or text
        type: 'numeric'
    });

  /** Coverage percent table */
  $.tablesorter.addParser({
      // set a unique id
      id: 'coveragepercent',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
            // format your data for normalization
            var i = s.indexOf("percentvalue");
            if(i!=-1)
              {
              var j = s.indexOf(">",i);
              var k = s.indexOf("</div>",j);
              var t = s.substr(j+1,k-j-2);
              return t.toLowerCase();
              }
           return false;
        },
        // set type, either numeric or text
        type: 'numeric'
    });

  /** numeric for dynamic analysis */
  $.tablesorter.addParser({
      // set a unique id
      id: 'dynanalysismetric',
      is: function(s) {
            // return false so this parser is not auto detected
            return false;
        },
        format: function(s) {
            // format your data for normalization
            var i = s.indexOf("<a");
            var j = s.indexOf(">",i);
            var k = s.indexOf("</a>",j);
            var t = s.substr(j+1,k-j-1);
            return t.toLowerCase();
        },
        // set type, either numeric or text
        type: 'numeric'
    });

  // viewTest table sorting converted to AngularJS.
  $tabs = $("#viewTestTable");

  // Initialize the testSummary tables
  if($tabs.length==0)
    {
    $tabs = $("#testSummaryTable");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: {
                0: { sorter:'text'},
                1: { sorter:'buildname'},
                2: { sorter:'text'},
                3: { sorter:'buildname'},
                4: { sorter:'digit'},
                5: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra']
        });
      });
    }

  // Initialize the queryTests tables
  if($tabs.length==0)
    {
    $tabs = $("#queryTestsTable");
    if (document.getElementById('showtesttimediv'))
    {
    $tabs.each(function(index) {
        $(this).tablesorter({
               headers: {
                   0: { sorter:'text'},
                   1: { sorter:'buildname'},
                   2: { sorter:'text'},
                   3: { sorter:'buildname'},
                   4: { sorter:'buildname'},
                   5: { sorter:'digit'},
                   6: { sorter:'text'},
                   7: { sorter:'text'}
               },
             debug: false,
             widgets: ['zebra']
           });
         });
    }
    else
    {
    $tabs.each(function(index) {
        $(this).tablesorter({
               headers: {
                   0: { sorter:'text'},
                   1: { sorter:'buildname'},
                   2: { sorter:'text'},
                   3: { sorter:'buildname'},
                   4: { sorter:'digit'},
                   5: { sorter:'text'},
                   6: { sorter:'text'}
               },
             debug: false,
             widgets: ['zebra']
           });
         });
    }
    }

  //Initialize the coverage table
  /*if($tabs.length==0)
    {
    $tabs = $("#coverageTable");
    if($("#coverageType").val() == "gcov")
    {
    $tabs.each(function(index) {
        $(this).tablesorter({
               headers: {
                   0: { sorter:'buildname'},
                   1: { sorter:'text'},
                   2: { sorter:'coveragepercent'},
                   3: { sorter:'digit'},
                   4: { sorter:'text'},
                   5: { sorter:'text'},
                   6: { sorter:'text'}
               },
             debug: false,
             widgets: ['zebra']
           }).tablesorterPager({container: $("#pager")}); ;
         });
    }
    else //bull's eye
      {
      $tabs.each(function(index) {
        $(this).tablesorter({
             headers: {
             0: { sorter:'buildname'},
               1: { sorter:'text'},
               2: { sorter:'coveragepercent'},
               3: { sorter:'digit'},
               4: { sorter:'digit'},
               5: { sorter:'text'},
               6: { sorter:'text'},
               7: { sorter:'text'}
              },
           debug: false,
           widgets: ['zebra']
           }).tablesorterPager({container: $("#pager")}); ;
         });
       }
    } // end coverage
  */

  // Initialize the userStatistics table
  if($tabs.length==0)
    {
    $tabs = $("#userStatistics");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: {
                0: { sorter:'text'},
                1: { sorter:'numericvalue'},
                2: { sorter:'digit'},
                3: { sorter:'digit'},
                4: { sorter:'digit'},
                5: { sorter:'digit'},
                6: { sorter:'digit'},
                7: { sorter:'digit'},
                8: { sorter:'digit'}
            },
          debug: false,
          widgets: ['zebra']
        });
      });
    }

  // Initialize the subproject table
  if($tabs.length==0)
    {
    $tabs = $("#subproject");
    $tabs.each(function(index) {
     $(this).tablesorter({
            headers: {
                0: { sorter:'text'},
                1: { sorter:'digit'},
                2: { sorter:'digit'},
                3: { sorter:'digit'},
                4: { sorter:'digit'},
                5: { sorter:'digit'},
                6: { sorter:'digit'},
                7: { sorter:'digit'},
                8: { sorter:'digit'},
                9: { sorter:'digit'},
                10: { sorter:'text'}
            },
          debug: false,
          widgets: ['zebra']
        });
      });
    }

  // Initialize the indextable table
  if($tabs.length==0)
    {
    $tabs = $("#indexTable");
    $tabs.each(function(index) {
    $(this).tablesorter({
        headers: {
           0: { sorter:'buildname'},
           1: { sorter:'text'},
           2: { sorter:'elapsedtime'}
          },
          debug: false,
          widgets: ['zebra']
        });
      });
    }

  // If all the above are not working then it should be the index table
  if($tabs.length==0)
    {
    // Initialize the Index tables
    $tabs = $(".tabb",this);
    $tabs.each(
     function(index) {
      var tableid = this.id;
      if(tableid == "coveragetable" || tableid == "dynamicanalysistable")
        {
        return;
        }

       if ($(this).hasClass("childbuild")) {
         $(this).tablesorter({
                headers: {
                    0: { sorter:'text'},          // labels
                    1: { sorter:'numericvalue'},  // update files
                    2: { sorter:'elapsedtime'},   // update time
                    3: { sorter:'numericvalue'},  // config error
                    4: { sorter:'numericvalue'},  // config warning
                    5: { sorter:'elapsedtime'},   // configure time
                    6: { sorter:'numericvalue'},  // build error
                    7: { sorter:'numericvalue'},  // build warning
                    8: { sorter:'elapsedtime'},   // build time (advanced)
                    9: { sorter:'numericvalue'},  // tests not run
                    10: { sorter:'numericvalue'}, // test failed
                    11: { sorter:'numericvalue'}, // test passed
                    12: { sorter:'elapsedtime'},  // test time
                    13: { sorter:'elapsedtime'},  // build time
                },
              debug: false,
              widgets: ['zebra']
            });
       } else {
         $(this).tablesorter({
                headers: {
                    0: { sorter:'buildname'},     // site
                    1: { sorter:'buildname'},     // build name
                    2: { sorter:'numericvalue'},  // update files
                    3: { sorter:'elapsedtime'},   // update time
                    4: { sorter:'numericvalue'},  // config error
                    5: { sorter:'numericvalue'},  // config warning
                    6: { sorter:'elapsedtime'},   // configure time
                    7: { sorter:'numericvalue'},  // build error
                    8: { sorter:'numericvalue'},  // build warning
                    9: { sorter:'elapsedtime'},   // build time
                    10: { sorter:'numericvalue'}, // tests not run
                    11: { sorter:'numericvalue'}, // test failed
                    12: { sorter:'numericvalue'}, // test passed
                    13: { sorter:'elapsedtime'},  // test time
                    14: { sorter:'elapsedtime'},  // build time
                    15: { sorter:'text'}          // labels
                },
              debug: false,
              widgets: ['zebra']
            });
      }

      // Get the cookie
      var cookiename = "cdash_table_sort_"+tableid;
      var cook = $.cookie(cookiename); // get cookie
      if(cook)
        {
        var cookArray = cook.split(',');
        var sortArray = new Array();
        var j=0;
        for(var i=0; i < cookArray.length; i+=2)
          {
          sortArray[j] = [cookArray[i],cookArray[i+1]];
          j++;
          }
        $(this).trigger("sorton",[sortArray]);
        }
      });

    // Initialize the coverage table
    $tabs = $("#coveragetable");
    $tabs.each(function(index) {
      if ($(this).hasClass("childbuild")) {
        $(this).tablesorter({
          headers: {
            0: { sorter:'text'},         // subproject
            1: { sorter:'percentage'}, // coverage %
            2: { sorter:'numericvalue'}, // LOC tested
            3: { sorter:'numericvalue'}, // LOC untested
            4: { sorter:'elapsedtime'}   // date
          },
          debug: false,
          widgets: ['zebra']
        });
      } else {
        $(this).tablesorter({
          headers: {
            0: { sorter:'text'},
            1: { sorter:'text'},
            2: { sorter:'numericvalue'},
            3: { sorter:'numericvalue'},
            4: { sorter:'numericvalue'},
            5: { sorter:'elapsedtime'},
            6: { sorter:'text'}
          },
          debug: false,
          widgets: ['zebra']
        });
      }
    });

    // Initialize the dynamic analysis table
    $tabs = $("#dynamicanalysistable");
    $tabs.each(function(index) {

     if ($(this).hasClass("child")) {
       $(this).tablesorter({
              headers: {
                  0: { sorter:'text'},
                  1: { sorter:'text'},
                  2: { sorter:'numericvalue'}, // not percent but same format
                  3: { sorter:'elapsedtime'}
              },
            debug: false,
            widgets: ['zebra']
          });
     } else {
       $(this).tablesorter({
              headers: {
                  0: { sorter:'text'},
                  1: { sorter:'text'},
                  2: { sorter:'text'},
                  3: { sorter:'numericvalue'}, // not percent but same format
                  4: { sorter:'elapsedtime'},
                  5: { sorter:'text'}
              },
            debug: false,
            widgets: ['zebra']
          });
     }
      });
    } // end indextable

});
