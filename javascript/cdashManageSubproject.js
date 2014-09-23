$(document).ready(function() {
  $('.subproject_core').change(function(e) {
    var catid = $(this).val();
    var nextBtn = $(this).next('.changeSubprojectGroup');
    nextBtn.attr("data-core", catid);
    console.log("core value is " + nextBtn.attr("data-core"));
    console.log("current core is : " + catid);
    nextBtn.removeAttr("disabled");
  });
  $('.changeSubprojectGroup').click(function(e) {
    var project = $(this).data('projectid');
    var subproject = $(this).data('subprojectid');
    var core = $(this).attr('data-core');
    var link = $(this);
    console.log("project id is " + project + " subpro id is: " + subproject +" core: " + core);
    $.get("manageSubproject.php", {"projectid": project, "subprojectid": subproject, "core": core })
      .done(function( data ) {
        console.log("call done and return value is " + data);
        link.attr("disabled","disabled");
      })
      .fail(function ( data) {
        console.log("call failed and return value is " + data);
      });
  });
});
