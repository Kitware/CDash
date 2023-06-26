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
            xaxis: {mode: "time"},
            yaxis: {min: 0, max: 100},
            legend: {position: "nw"},
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

        $("#grapholder").bind("selected", function (event, area) {
            plot = $.plot($("#grapholder"),
                [{label: "% coverage", data: percent_array},
                    {label: "loc tested", data: loctested_array, yaxis: 2},
                    {label: "loc untested", data: locuntested_array, yaxis: 2}],
                $.extend(true, {}, options, {xaxis: {min: area.x1, max: area.x2}}));
        });

        $("#grapholder").bind("plotclick", function (e, pos, item) {
            if (item) {
                plot.highlight(item.series, item.datapoint);
                buildid = buildids[item.datapoint[0]];
                window.location = "build/" + buildid;
            }
        });

        plot = $.plot($("#grapholder"), [{label: "% coverage", data: percent_array},
            {label: "loc tested", data: loctested_array, yaxis: 2},
            {label: "loc untested", data: locuntested_array, yaxis: 2}], options);
    });
</script>
