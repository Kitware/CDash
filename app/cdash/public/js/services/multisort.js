// Sort by multiple columns at once.
CDash.factory('multisort', () => {
  return {
    updateOrderByFields: function(obj, field, $event) {
      // Note that by default we sort in descending order.
      // This is accomplished by prepending the field with '-'.

      const idx = obj.orderByFields.indexOf(`-${field}`);
      if ($event.shiftKey) {
        // When shift is held down we append this field to the list of sorting
        // criteria.
        // eslint-disable-next-line eqeqeq
        if (idx != -1) {
          // Reverse sort for this field because it was already in the list.
          obj.orderByFields[idx] = field;
        }
        else {
          const idx2 = obj.orderByFields.indexOf(field);
          // eslint-disable-next-line eqeqeq
          if (idx2 != -1) {
            // If field is in the list replace it with -field.
            obj.orderByFields[idx2] = `-${field}`;
          }
          else {
            // Otherwise just append -field to the end of the list.
            obj.orderByFields.push(`-${field}`);
          }
        }
      }
      else {
        // Shift wasn't held down so this field is the only criterion that we
        // will use for sorting.
        // eslint-disable-next-line eqeqeq
        if (idx != -1) {
          obj.orderByFields = [field];
        }
        else {
          obj.orderByFields = [`-${field}`];
        }
      }
    },
  };
});
