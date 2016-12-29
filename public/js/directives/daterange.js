CDash.directive('daterange', function (VERSION) {
  return {
    restrict: 'A',
    templateUrl: 'build/views/partials/daterange_' + VERSION + '.html',
    link: function (scope, element, attrs, ngModelCtrl) {
      var format = "yy-mm-dd",

      from = element.find("#from").datepicker({
        changeMonth: true,
        dateFormat: format,
        defaultDate: scope.cdash.date
      }).on( "change", function() {
        var date = getDate(this);
        to.datepicker("option", "minDate", date);
      }),

      to = element.find("#to").datepicker({
        changeMonth: true,
        dateFormat: format,
        defaultDate: scope.cdash.date
      }).on( "change", function() {
        var date = getDate(this);
        from.datepicker("option", "maxDate", date);
      });

      function getDate(element) {
        var date;
        try {
          date = $.datepicker.parseDate(format, element.value);
        } catch( error ) {
          date = null;
        }
        return date;
      }
    }
  };
});
