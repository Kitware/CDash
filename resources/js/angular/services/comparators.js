// How to compare different types of data.
angular
  .module('CDash')
  .factory('comparators', comparators);

function comparators() {
  return {
    getComparators : function() {
      return {
        array:
          [
            {
              pos: 1,
              symbol: "in",
              text: "contains"
            },
            {
              pos: 2,
              symbol: "not in",
              text: "does not contain"
            }
          ],
        bool:
          [
            {
              pos: 1,
              symbol: "==",
              text: "is"
            },
            {
              pos: 2,
              symbol: "!=",
              text: "is not"
            }
          ],
        number:
          [
            {
              pos: 1,
              symbol: "<",
              text: "is less than"
            },
            {
              pos: 2,
              symbol: ">",
              text: "is greater than"
            },
            {
              pos: 3,
              symbol: "==",
              text: "equals"
            }
          ]
      };
    }
  }
}
