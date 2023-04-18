CDash.directive('daterange', (VERSION) => {
  return {
    restrict: 'A',
    templateUrl: `build/views/partials/daterange_${VERSION}.html`,
    link: function (scope, element, attrs, ngModelCtrl) {
      const format = 'yy-mm-dd',

        begin = element.find('#begin').datepicker({
          constrainInput: false,
          changeMonth: true,
          dateFormat: format,
          defaultDate: scope.cdash.date,
        }).on( 'change', function() {
          const date = getDate(this);
          if (date) {
            end.datepicker('option', 'minDate', date);
          }
        }),

        end = element.find('#end').datepicker({
          constrainInput: false,
          changeMonth: true,
          dateFormat: format,
          defaultDate: scope.cdash.date,
        }).on( 'change', function() {
          const date = getDate(this);
          if (date) {
            begin.datepicker('option', 'maxDate', date);
          }
        });

      function getDate(element) {
        let date;
        try {
          date = $.datepicker.parseDate(format, element.value);
        }
        catch ( error ) {
          date = null;
        }
        return date;
      }
    },
  };
});
