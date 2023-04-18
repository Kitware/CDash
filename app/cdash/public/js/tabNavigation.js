$(document).ready(() => {
  // Initialize tab navigation
  $('#tabs a').tab();

  // Enable link to tab
  const url = document.location.toString();
  if (url.match('#/')) {
    $(`.nav-tabs a[href=#${url.split('#/')[1]}]`).tab('show') ;
  }

  // Change hash for page-reload
  $('.nav-tabs a').on('shown', (e) => {
    angular.element(window).location.hash = e.target.hash;
  });

  $('#tabs a').click(function (e) {
    e.preventDefault();
    $(this).tab('show');
  });
});
