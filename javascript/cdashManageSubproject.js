$(document).ready(function() {

  /* React to the core / non-core selection changing. */
  $('.subproject_core').change(function(e) {
    var catid = $(this).val();
    var nextBtn = $(this).next('.changeSubprojectGroup');
    nextBtn.attr("data-core", catid);
    nextBtn.removeAttr("disabled");
  });


  /* React to the "Modify" button being pressed. */
  $('.changeSubprojectGroup').click(function(e) {
    var project = $(this).data('projectid');
    var subproject = $(this).data('subprojectid');
    var core = $(this).attr('data-core');
    var link = $(this);
    $.get("manageSubproject.php", {"projectid": project, "subprojectid": subproject, "core": core })
      .done(function( data ) {
        link.attr("disabled","disabled");
      })
  });


  /* React to the "Add dependency" selection changing.   */
  /* Used to enable/disable the associated "Add" button. */
  $('.dependency_selector').change(function(e) {
    var val = $(this).val();
    var button = $(this).next("input[name=addDependency]");
    if (val == -1)
      {
      button.prop('disabled', true);
      }
    else
      {
      button.prop('disabled', false);
      }
  });


  /* React to the "Display" selector being changed. */
  $('#displayChooser').change(function(e) {
    var selectedText = $("#displayChooser option:selected").text();
    switch (selectedText)
      {
      case "Non-Core":
        console.log('noncore');
        $('.noncore').show();
        $('.core').hide();
        $('.thirdparty').hide();
        break;
      case "Core":
        console.log('core');
        $('.noncore').hide();
        $('.core').show();
        $('.thirdparty').hide();
        break;
      case "Third Party":
        console.log('thirdparty');
        $('.noncore').hide();
        $('.core').hide();
        $('.thirdparty').show();
        break;
      case "All":
      default:
        console.log('all');
        $('.noncore').show();
        $('.core').show();
        $('.thirdparty').show();
        break;
      }
  });

});
