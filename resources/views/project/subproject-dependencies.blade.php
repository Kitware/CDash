@extends('cdash', [
    'title' => 'SubProject Dependencies Graph'
])

@section('main_content')
    <div style="top:10px; left:20px; overflow:hidden;">
        <label style="font-size:1.2em;"><b>SubProject Dependencies Graph</b></label>
        <label for="selectedsort" style="margin-left:80px; font-size:.95em;">Sorted by:</label>
        <select id="selectedsort">
            <option value="0" selected="selected">subproject name</option>
            <option value="1">subproject id</option>
        </select>
        <button onclick="download_svg()" style="float:right; width:200px; margin-right:30px">Export as svg file</button>
    </div>
    <div class="hint" style="top:20px; left:20px; font-size:0.9em; width:350px; color:#999;">
        This circle plot captures the interrelationships among subgroups. Mouse over any of the subgroup in this graph to see incoming links (dependents) in green and the outgoing links (dependencies) in red.
    </div>
    <div id="chart_placeholder" style="left:150px; top:-60px; text-align:center;">
    </div>
    <!-- Tooltip -->
    <div id="toolTip" class="tooltip" style="opacity:0;">
        <div id="header1" class="header"></div>
        <div id="dependency" style="color:#d62728;"></div>
        <div id="dependents" style="color:#2ca02c;"></div>
        <div  class="tooltipTail"></div>
    </div>
    <link href="//fonts.googleapis.com/css?family=Open+Sans:400,700|Roboto:400,700" rel="stylesheet" type="text/css"/>
    <link href="{{ asset('css/d3.dependencyedgebundling.css') }}" rel="stylesheet" type="text/css"/>

    <script type="text/javascript" src="{{ asset('js/d3.min.js') }}"></script>
    <script type="text/javascript" src="{{ asset('js/d3.dependencyedgebundling.js') }}"></script>
    <script>
        var chart = d3.chart.dependencyedgebundling();
        chart.mouseOvered(mouseOvered).mouseOuted(mouseOuted);
        var dataroot;
        function sort_by_name (a, b) {
            if (a.name < b.name) {
                return -1;
            }
            if (a.name > b.name) {
                return 1;
            }
            return 0;
        }

        function sort_by_id (a, b) {
            if (a.id < b.id) {
                return -1;
            }
            if (a.id > b.id) {
                return 1;
            }
            return 0;
        }

        function mouseOvered(d) {
            var header1Text = "Name: " + d.key;
            if (d.group !== undefined) {
                header1Text += ", Group: " + d.group;
            }
            $('#header1').html(header1Text);
            if (d.depends) {
                var depends = "<p>Depends: ";
                depends += d.depends.join(", ") + "</p>";
                $('#dependency').html(depends);
            }
            var dependents = "";
            d3.selectAll('.node--source').each(function (p) {
                if (p.key) {
                    dependents += p.key + ", ";
                }
            });

            if (dependents) {
                dependents = "Dependents: " + dependents.substring(0,dependents.length-2);
                $('#dependents').html(dependents);
            }
            d3.select("#toolTip").style("left", (d3.event.pageX + 40) + "px")
                .style("top", (d3.event.pageY + 5) + "px")
                .style("opacity", ".9");
        }

        function mouseOuted(d) {
            $('#header1').text("");
            $('#dependents').text("");
            $('#dependency').text("");
            d3.select("#toolTip").style("opacity", "0");
        }

        function resetDepView() {
            d3.select('#chart_placeholder svg').remove();
            d3.select('#chart_placeholder')
                .datum(dataroot)
                .call(chart);
        }
        $(function(){
            $('#selectedsort').on("change", function(e) {
                selected = $(this).val();
                if (parseInt(selected) === 1) {
                    dataroot.sort(sort_by_id);
                } else if (parseInt(selected) === 0) {
                    dataroot.sort(sort_by_name);
                }
                resetDepView(dataroot);
            });
            var ajaxlink = "{{ url('ajax/getsubprojectdependencies.php') }}?project={{ urlencode($project->Name) }}";
            d3.json(ajaxlink, function(error, classes) {
                if (error){
                    errormsg = "json error " + error + " data: " + classes;
                    console.log(errormsg);
                    document.write(errormsg);
                    return;
                }
                dataroot = classes;
                dataroot.sort(sort_by_name);
                resetDepView(dataroot);
            });
        });
    </script>
@endsection
