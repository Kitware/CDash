CDash.directive('daterange', ["VERSION", function (VERSION) {
  return {
    restrict: 'A',
    templateUrl: 'assets/js/angular/views/partials/daterange.html?id=' + VERSION,
    link: function (scope, element, attrs, ngModelCtrl) {
      var format = "yy-mm-dd",

      begin = element.find("#begin").datepicker({
        constrainInput: false,
        changeMonth: true,
        dateFormat: format,
        defaultDate: scope.cdash.date
      }).on( "change", function() {
        var date = getDate(this);
        if (date) {
          end.datepicker("option", "minDate", date);
        }
      }),

      end = element.find("#end").datepicker({
        constrainInput: false,
        changeMonth: true,
        dateFormat: format,
        defaultDate: scope.cdash.date
      }).on( "change", function() {
        var date = getDate(this);
        if (date) {
          begin.datepicker("option", "maxDate", date);
        }
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
}]);
