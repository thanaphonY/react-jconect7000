<!DOCTYPE html>
<html>

<head>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>

<body>


    <div id="chart"></div>

    <script>
        const device_data = <?= json_encode($device_data); ?>;

        const timestamps = device_data.map(item => item.timestamp);
        const values = device_data.map(item => item.value);

        // console.log(timestamps[0])
        // console.log(timestamps[1])

        // console.log(timestamps[timestamps.length - 1])

        // const filtered = timestamps
        //     .map(ts => new Date(ts.replace(" ", "T").replace("+00", "Z")))
        //     .filter(date => date.getUTCHours() === 1);

        // console.log(timestamps[0])
        // filtered.forEach(element => {
        //     console.log(element)
        // });



        // console.log(formatted[0]); 

        // console.log(typeof(formatted[0]))
        // console.log(timestamps[0])

        const options = {
            chart: {
                type: 'line',
                height: 400
            },
            series: [{
                name: 'Temperature',
                data: values
            }],
            xaxis: {
                title: {
                    text: 'Timestamp' 
                },
                categories: timestamps, 
                labels: {
                    rotate: -45,
                    style: {
                        fontSize: '12px',
                        colors: '#333'
                    }
                }
            }


        };

        const chart = new ApexCharts(document.querySelector("#chart"), options);
        chart.render();
    </script>

</body>

</html>