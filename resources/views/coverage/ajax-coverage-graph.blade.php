<script type="text/javascript">
    $(function () {
        const percent_array = [];
        const loctested_array = [];
        const locuntested_array = [];
        const buildids = [];
        @php($i = 0)
        @foreach($previousbuilds as $build_array)
            @php($t = strtotime($build_array->starttime) * 1000) {{-- flot expects milliseconds --}}
            @php(@$percent = round(intval($build_array->loctested) / (intval($build_array->loctested) + intval($build_array->locuntested)) * 100, 2))
            percent_array.push([{{ $t }}, {{ $percent }}]);
            loctested_array.push([{{ $t }}, {{ $build_array->loctested }}]);
            locuntested_array.push([{{ $t }}, {{ $build_array->locuntested }}]);
            buildids[{{ $t }}] = {{ $build_array->id }};
            @php($i++)
        @endforeach

        const options = {
            lines: {show: true},
            points: {show: true},
            xaxis: {mode: "time", axisLabel: 'Time of build (UTC)',
                            timeformat: "%Y/%m/%d %H:%M",
                            timeBase: "milliseconds"},
            yaxis: {min: 0, max: 100, axisLabel: 'Coverage Percentage',},
            legend: {position: "nw", show: true},
            grid: {
                backgroundColor: "#fffaff",
                clickable: true,
                hoverable: true,
                hoverFill: '#444',
                hoverRadius: 4
            },
            selection: {mode: "x"},
            colors: ["#0000FF", "#dba255", "#919733"]
        };

        $("<div id='tooltip'></div>").css({
			position: "absolute",
			display: "none",
			border: "1px solid #fdd",
			padding: "2px",
			"background-color": "#fee",
			opacity: 0.80
		}).appendTo("body");

        $("#grapholder").bind("plothover", function (event, pos, item) {

            if (!pos.x || !pos.y) {
                return;
            }
            if (item) {
                $("#tooltip").html(`${item.series.label}: ${item.datapoint[1]}<br>LOC Untested: ${locuntested_array[item.dataIndex][1]}<br>LOC Tested: ${loctested_array[item.dataIndex][1]}`)
                    .css({top: item.pageY+5, left: item.pageX+5})
                    .fadeIn(200);
            } else {
                $("#tooltip").hide();
            }
        });


        $("#grapholder").bind("selected", function (event, area) {
            plot = $.plot($("#grapholder"),
                [{label: "% coverage", data: percent_array}],
                $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $("#grapholder").bind("plotclick", function (e, pos, item) {
            if (item) {
                plot.highlight(item.series, item.datapoint);
                buildid = buildids[item.datapoint[0]];
                window.location = "build/" + buildid;
            }
        });

        plot = $.plot($("#grapholder"), [{label: "% coverage", data: percent_array}], options);
    });
</script>
