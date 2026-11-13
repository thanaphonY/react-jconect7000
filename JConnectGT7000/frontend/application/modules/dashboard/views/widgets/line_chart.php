<?php

$cfg = $widget->config ?? (object)[];
$chartId = 'chart_' .  $widget_id;
$tableId = 'table_' .  $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'height: 100%; overflow-y:hidden');

?>
<div class="widget-linechart <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
    <div class="small text-muted mb-1"><?= htmlspecialchars($cfg->label ?? 'Line Chart') ?></div>
    <div class="loading-overlay" id="overlay_<?= $chartId ?>" style="display:none;">
        <div class="spinner-border text-primary me-2" role="status"></div> Loading...
    </div>

    <!-- Chart Area -->
    <div style=" flex: 1;">
        <canvas id="<?= $chartId ?>" height="100px"></canvas>
    </div>
    <!-- Table Area -->
    <div class="mt-2" style="max-height: 50%; min-height: 30%; overflow-y: auto;">
        <table class="table table-sm table-hover align-middle" id="<?= $tableId ?>">
            <thead>
                <tr class="table-light">
                    <th scope="col" style="width: 90px;"><input type="checkbox" class="form-check-input series-toggle" data-index="<?= $chartId ?>" checked> Select</th>
                    <th scope="col">Device Name</th>
                    <!--<th scope="col">Location</th>-->
                    <th scope="col">Label</th>
                    <th scope="col">Last Data</th>
                    <th scope="col">Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <!-- Filled by JS -->
            </tbody>
        </table>
    </div>
</div>

<script>
    function formatDate(date) {

        // DD/MM/YYYY HH:mm:ss
        const dd = String(date.getDate()).padStart(2, "0");
        const mm = String(date.getMonth() + 1).padStart(2, "0");
        const yyyy = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, "0");
        const min = String(date.getMinutes()).padStart(2, "0");
        const ss = String(date.getSeconds()).padStart(2, "0");
        return `${dd}/${mm}/${yyyy} ${hh}:${min}:${ss}`;
    }

    function formatDate2(date) {
        // DD/MM/YYYY HH:mm
        const dd = String(date.getDate()).padStart(2, "0");
        const mm = String(date.getMonth() + 1).padStart(2, "0");
        const yyyy = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, "0");
        const min = String(date.getMinutes()).padStart(2, "0");
        const ss = String(date.getSeconds()).padStart(2, "0");
        return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
    }

    document.addEventListener("DOMContentLoaded", function() {

        const chartId = "<?= $chartId ?>";
        const ctx = document.getElementById(chartId).getContext("2d");
        const tableBody = document.querySelector("#<?= $tableId ?> tbody");
        const selectAllCheckbox<?= $chartId ?> = document.querySelector('.series-toggle[data-index="<?= $chartId ?>"]');

        const overlay = document.getElementById("overlay_<?= $chartId ?>")

        let chart<?= $chartId ?> = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                spanGaps: 1000 * 60 * 60,
                pointRadius: 0.5,
                scales: {
                    x: {
                        type: 'time',

                        time: {
                            unit: 'hour',
                            round: 'minute',
                            displayFormats: {
                                hour: 'HH:mm'
                            }
                        },
                        ticks: {
                            source: 'auto'
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: ''
                        },
                        ticks: {
                            stepSize: 5,
                            callback: function(value) {
                                const unit = this.chart.config.options.scales.y.yUnit || '';
                                return `${value}${unit}`;
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        position: 'nearest',
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        bodyFontColor: '#fff',
                        bodyFontSize: 12,
                        bodyFontStyle: 'normal',
                        bodyFontFamily: 'Arial',
                        bodyFontWeight: 'normal',
                        bodyFontOpacity: 1,
                        callbacks: {

                            title: ctx => {
                                const ts = new Date(ctx[0].parsed.x); // parsed.x คือ timestamp ของแกน x
                                return ts.toLocaleString('en-GB', {
                                    year: 'numeric',
                                    month: 'short',
                                    day: '2-digit',
                                    hour: '2-digit',
                                    minute: '2-digit',
                                    hour12: false
                                });
                            },
                            label: ctx => {
                                const label = ctx.dataset.label || '';
                                const value = ctx.raw ?? '';
                                const unit = ctx.dataset.unit || '';
                                return `${label}: ${Number(value).toFixed(2)}${unit}`;
                            }
                        }
                    }
                }
            }
        });

        // Select All toggle
        if (selectAllCheckbox<?= $chartId ?>) {
            selectAllCheckbox<?= $chartId ?>.addEventListener('change', function() {
                const check = this.checked;
                const checkboxes<?= $chartId ?> = document.querySelectorAll('#<?= $tableId ?> .series-toggle:not([data-index="<?= $chartId ?>"])');
                checkboxes<?= $chartId ?>.forEach(cb => {
                    cb.checked = check;
                    const idx = parseInt(cb.dataset.index);
                    chart<?= $chartId ?>.setDatasetVisibility(idx, check);
                });
                chart<?= $chartId ?>.update();
            });
        }

        // === LOAD CHART FUNCTION ===
        function loadChartData() {
            overlay.style.display = 'flex';
            fetch('/dashboard/widget_data/<?= $widget_id ?>')
                .then(res => res.json())
                .then(data => {
                    chart<?= $chartId ?>.data.labels = data.labels;
                    chart<?= $chartId ?>.data.datasets = data.datasets;

                    if (data.yMin) {
                        chart<?= $chartId ?>.options.scales.y.min = data.yMin;
                    } else if (data.yMin === 0) {
                        chart<?= $chartId ?>.options.scales.y.min = 0;
                    }

                    if (data.yMax) {
                        chart<?= $chartId ?>.options.scales.y.max = data.yMax;
                    }

                    chart<?= $chartId ?>.options.scales.y.title.display = true;
                    chart<?= $chartId ?>.options.scales.y.title.text = data.yTitle || '';

                    chart<?= $chartId ?>.options.scales.y.yUnit = data.yUnit || '';
                    chart<?= $chartId ?>.options.scales.y.yTitle = data.yTitle || '';


                    var xTitleBase = data.xTitle || '';

                    var xTitleText = xTitleBase;
                    if (data.labels && data.labels.length > 0) {
                        var startDate = formatDate2(new Date(data.labels[0]));
                        var endDate = formatDate2(new Date(data.labels[data.labels.length - 1]));
                        xTitleText =  startDate + ' to ' + endDate;
                    }

                    chart<?= $chartId ?>.options.scales.x.title.display = true;
                    chart<?= $chartId ?>.options.scales.x.title.text = xTitleText;

                    chart<?= $chartId ?>.options.scales.x.xUnit = data.xUnit || '';
                    chart<?= $chartId ?>.options.scales.x.xTitle = xTitleText;



                    chart<?= $chartId ?>.update();

                    tableBody.innerHTML = data.datasets.map((ds, i) => {
                        const ts = data.labels[data.labels.length - 1] ?? '--';
                        return `<tr>
                        <td style="background-color:${ds.borderColor};">
                            <input type="checkbox" class="form-check-input series-toggle" data-index="${i}" checked>
                        </td>
                        <td>${ds.device_name ?? '-'}</td>
                        <td>${ds.label ?? '-'}</td>
                        <td>${Number(ds.lastdata).toFixed(2)} ${ds.unit}</td>
                        <td>${ds.lasttime !== '' ? formatDate(new Date(ds.lasttime)) : ''}</td>
                    </tr>`;
                    }).join('');

                    // Bind per-series toggles
                    tableBody.querySelectorAll('.series-toggle').forEach(input => {
                        input.addEventListener('change', function() {
                            const idx = parseInt(this.dataset.index);
                            chart<?= $chartId ?>.setDatasetVisibility(idx, this.checked);
                            chart<?= $chartId ?>.update();

                            // Sync select all state
                            const allToggles = document.querySelectorAll('.series-toggle:not([data-index="<?= $chartId ?>"])');
                            const allChecked = [...allToggles].every(cb => cb.checked);
                            if (selectAllCheckbox<?= $chartId ?>) selectAllCheckbox<?= $chartId ?>.checked = allChecked;
                        });
                    });

                    overlay.style.display = 'none';
                }).finally(() => {
                    overlay.style.display = "none";
                });
        }

        loadChartData();
        setInterval(loadChartData, 900000); // refresh every 15 minutes
    });
</script>