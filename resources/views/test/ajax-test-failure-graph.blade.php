<script language="javascript" type="text/javascript">
    $(function () {
        const d1 = [];

        @foreach($tarray as $axis)
            d1.push([{{ $axis['x'] }}, {{ $axis['y'] }}]);
            @php($t = $axis['x'])
        @endforeach

        const options = {
            series: {
                bars: {
                    show: true,
                    barWidth: 0.5,
                },
            },
            legend: {
                show: true,
                position: "ne",
            },
            yaxis: {min: 0},
            xaxis: {
                mode: "time",
                min: {{ $t - 604800 }},
                max: {{ $t + 100000 }},
                timeformat: "%Y/%m/%d %H:%M",
                timeBase: "milliseconds",
            },
            grid: {backgroundColor: "#fffaff"},
            selection: {mode: "x"},
            colors: ["#0000FF", "#dba255", "#919733"]
        };

        const plot = $.plot($("#testfailuregrapholder"), [{label: "# builds failed", data: d1}], options);

        $("#testfailuregrapholder").bind("plotselected", function (event, ranges) {
            $.each(plot.getXAxes(), function(_, axis) {
                const opts = axis.options;
                opts.min = ranges.xaxis.from;
                opts.max = ranges.xaxis.to;
            });
            plot.setupGrid();
            plot.draw();
            plot.clearSelection();
        });
    });
</script>
