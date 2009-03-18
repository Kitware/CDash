function addFilter(indexSelected)
{
  indexSelected=parseInt(indexSelected);
  string=$(".filterFields:last").html();// find index last element
  index=$(".filterFields:last input:last").attr("name");
  index=parseInt(index.substr(3));
  lastIndex=parseInt($(".filterFields:last").attr("number"));

  for(i=(lastIndex+1);i>=(indexSelected+1);i--)
    {
      if($(".filterFields[number='"+i+"']").length)//test if the element exists
        {
        element=$(".filterFields[number='"+i+"']");
        element.find("select:first").attr("name","field"+(i+1));
        element.find("select:last").attr("name","compare"+(i+1));
        element.find("select:last").attr("id","id_compare"+(i+1));
        element.find("select[type='text']:first").attr("id","id_value"+(i+1));
        element.find("input[type='text']:first").attr("name","value"+(i+1));
        element.find("input[type='button']:first").attr("name","remove"+(i+1));
        $(".filterFields[number='"+i+"'] input[type='button']:first").attr("id","temp");
        document.getElementById("temp").setAttribute("onclick","removeFilter("+(i+1)+")");
        $(".filterFields[number='"+i+"'] input[type='button']:first").removeAttr("id");
        element.find("input[type='button']:last").attr("name","add"+(i+1));
        $(".filterFields[number='"+i+"'] input[type='button']:last").attr("id","temp");
        document.getElementById("temp").setAttribute("onclick","addFilter("+(i+1)+")");
        $(".filterFields[number='"+i+"'] input[type='button']:last").removeAttr("id");
        element.attr("number",(i+1));
        element.attr("id","filter"+(i+1));
        }
    }
  // create html
  string=string.replace("name=\"field"+index,"name=\"field"+(indexSelected+1));
  string=string.replace("id=\"id_compare"+index+"\" name=\"compare"+index+"\"","id=\"id_compare"+(indexSelected+1)+"\" name=\"compare"+(indexSelected+1)+"\"");
  string=string.replace("id=\"id_value"+index+"\" name=\"value"+index+"\"","id=\"id_value"+(indexSelected+1)+"\" name=\"value"+(indexSelected+1)+"\"");
  string=string.replace("name=\"remove"+index,"name=\"remove"+(indexSelected+1));
  string=string.replace("name=\"add"+index,"name=\"add"+(indexSelected+1));
  string=string.replace("disabled=\"disabled\"","");
  string=string.replace("disabled=\"\"","");
  string=string.replace("selected=\"selected\"","");
  string=string.replace("selected=\"selected\"","");
  string=string.replace("removeFilter("+index+")","removeFilter("+(indexSelected+1)+")");
  string=string.replace("addFilter("+index+")","addFilter("+(indexSelected+1)+")");
  class_filed='treven';
  if($(".filterFields[number='"+indexSelected+"']").attr("class")=="treven filterFields")
    {
    class_filed='trodd';
    }
    //create new element
  $(".filterFields[number='"+(indexSelected)+"']").after('<tr class="'+class_filed+' filterFields" number="'+(indexSelected+1)+'" id="filter'+(indexSelected+1)+'">'+string+'</tr>');
  previousValue=$(".filterFields[number='"+(indexSelected)+"'] input[type='text']").attr("value");
  $(".filterFields[number='"+(indexSelected+1)+"'] input[type='text']").attr("value",previousValue);
  selectOption=$(".filterFields[number='"+(indexSelected)+"'] select:first").attr("value");
  $(".filterFields[number='"+(indexSelected+1)+"'] select:first option").each(function(){
          if ($(this).attr("value") == selectOption) {
            $(this).attr("selected",true);
          };
     });
  $(".filterFields:first input:first").removeAttr("disabled"); //enable remove button
  $("input[name='filtercount']").attr("value",countFilters()); //set value of the hidden input which tell the number of filter

  content=$(".filterFields[number='"+(indexSelected)+"'] select:last").html();
  $(".filterFields[number='"+(indexSelected+1)+"'] select:last").html(content);
  selectOption=$(".filterFields[number='"+(indexSelected)+"'] select:last").attr("value");
  $(".filterFields[number='"+(indexSelected+1)+"'] select:last option").each(function(){
          if ($(this).attr("value") == selectOption) {
            $(this).attr("selected",true);
          };
     });

  if(countFilters()==2)
    {
    string=' Match <select name="filtercombine"><option value="and" selected="selected"> all</option';
    string+='><option value="or">any </option></select> of the following rules:';
    $("#Match_filter").html(string);
    }
}

function removeFilter(index)
{
  $(".filterFields").each(function(){ // remove selected element
    if($(this).find("input:last").attr("name")=="add"+index)
      {
      $(this).remove();
      }
  });
  if(countFilters()==1)
    {
    $(".filterFields:first input[value='-']").attr("disabled","disabled");
    }
  $("input[name='filtercount']").attr("value",countFilters());
  if(countFilters()==1)
    {
    $("#Match_filter").html('Match the following rule: ');
    }
}

function clearFilter()
{
  $(".filterFields:gt(0)").remove();// keep only one element(this element will be empty)
  $(".filterFields input[type='text']").attr("value","");
  $(".filterFields input[value='-']").attr("disabled",true);
  $("input[name='filtercount']").attr("value",1);
  $("#Match_filter").html('Match the following rule: ');
}

function countFilters()
{
  i=0;
  $(".filterFields").each(function(){
    i++;
  });
  return i;
}

function filters_toggle()
{
  if ($("#label_showfilters").html() == "[Hide Filters]")
    {
    $("#div_showfilters").hide();
    $("#label_showfilters").html("[Show Filters]");
    return;
    }

  $("#div_showfilters").show();
  $("#label_showfilters").html("[Hide Filters]");
}


function set_bool_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparison --", "0", true, true);
  s.options[1] = new Option("is true", "1", false, false);
  s.options[2] = new Option("is false", "2", false, false);
}


function set_number_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparison --", "40", true, true);
  s.options[1] = new Option("is", "41", false, false);
  s.options[2] = new Option("is not", "42", false, false);
  s.options[3] = new Option("is greater than", "43", false, false);
  s.options[4] = new Option("is less than", "44", false, false);
}


function set_string_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparison --", "60", true, true);
  s.options[1] = new Option("contains", "63", false, false);
  s.options[2] = new Option("does not contain", "64", false, false);
  s.options[3] = new Option("is", "61", false, false);
  s.options[4] = new Option("is not", "62", false, false);
  s.options[5] = new Option("starts with", "65", false, false);
  s.options[6] = new Option("ends with", "66", false, false);
}


function set_date_compare_options(s)
{
  s.options.length = 0;
  s.options[0] = new Option("-- choose comparison --", "80", true, true);
  s.options[1] = new Option("is", "81", false, false);
  s.options[2] = new Option("is not", "82", false, false);
  s.options[3] = new Option("is after", "83", false, false);
  s.options[4] = new Option("is before", "84", false, false);
}


// Prefix cdf_ == cdashFilters variable...
//
var cdf_last_data_type_value = '';


// See http://wsabstract.com/javatutors/selectcontent.shtml for an article
// on how to changs SELECT element content on the fly from javascript...
//
// And see http://www.throbs.net/web/articles/IE-SELECT-bugs/#ieInnerHTMLproperty
// and http://support.microsoft.com/default.aspx?scid=kb;en-us;276228 for articles
// explaining why you cannot use the select element's innerHTML to do it.
//
function update_compare_options(o)
{
  // Get current "name/type" value from o.value / o.options[o.selectedIndex].value:
  //
  cmps = o.value.split('/');
  name = cmps[0];
  type = cmps[1];

  // Only change the comparison choices if changing the data type
  // of the selected field...
  //
  if (type != cdf_last_data_type_value)
    {
    switch (type)
      {
      case 'bool':
      case 'date':
      case 'number':
      case 'string':
        opts = 'valid data type';
        break;
      default:
        opts = '';
        alert('error: unknown data type in javascript update_compare_options. type="' + type + '"');
        break;
      }

    if (opts != '')
      {
      cdf_last_data_type_value = type;

      // o.name is like "field1": Get just the trailing number from o.name:
      //
      num = o.name.substr(5);

      // o.name is "field1" (through "fieldN") -- when its selectedIndex changes,
      // we need to update the list in "id_compare1" to reflect the comparison
      // choices available for the type of data in field1...
      //
      selectElement = document.getElementById('id_compare' + num);

      switch (type)
        {
        case 'bool':
          set_bool_compare_options(selectElement);
          break;

        case 'date':
          set_date_compare_options(selectElement);
          break;

        case 'number':
          set_number_compare_options(selectElement);
          break;

        case 'string':
          set_string_compare_options(selectElement);
          break;

        // Should never get here because of above logic, but:
        //
        default:
          alert('error: unknown data type in javascript update_compare_options. type="' + type + '"');
          break;
        }

      // Also clear the corresponding 'value' input when data type changes:
      //
      //valueElement = document.getElementById('id_value' + num);
      $("#id_value"+num).attr("value",'');
      //valueElement.value = '';
      }
    }
}


//alert(o.name
//  + ' ' + compareSelect.name
//  + " num='" + num + "'"
//  + " name='" + name + "'"
//  + " type='" + type + "'"
//  + " selectedIndex=" + o.selectedIndex
//  + " value=" + o.value
//  + " (" + o.options[o.selectedIndex].value + ")"
//  + " (" + o.options[o.selectedIndex].innerHTML + ")"
//  );


function filters_field_changed(o)
{
  update_compare_options(o);
}
