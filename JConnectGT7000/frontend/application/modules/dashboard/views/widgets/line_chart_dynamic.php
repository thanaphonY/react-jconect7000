<?php
$cfg = $widget->config ?? (object)[];
$chartId = 'chart_' .  $widget_id;
$tableId = 'table_' .  $widget_id;
$baseLabel = htmlspecialchars($cfg->label ?? 'Line Chart');
$timeRangeLabels = ['6h' => 'Last 6 Hours', '12h' => 'Last 12 Hours', '24h' => 'Last 24 Hours', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days', 'custom' => 'Custom range'];
$timeRangeVal = $cfg->time_range ?? '24h';
$timeRangeLabel = $timeRangeLabels[$timeRangeVal] ?? 'Last 24 Hours';
$chartTitle = $baseLabel . '<span class="text-muted"> (' . $timeRangeLabel . ')</span>';
$chartHeightPct = max(30, min(80, (int)($cfg->chart_height_pct ?? 60)));
$tableHeightPct = 100 - $chartHeightPct;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'height: 100%; overflow-y:hidden');
?>
<div class="widget-linechart <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column position-relative" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
    <div class="d-flex align-items-center mb-1 position-relative">
        <div class="flex-grow-1"></div>
        <div id="chartLabel_<?= $chartId ?>" class="small fw-semibold dashboard-chart-title position-absolute start-50 translate-middle-x" data-base-label="<?= $baseLabel ?>"><?= $chartTitle ?></div>
        <div class="flex-grow-1 d-flex justify-content-end">
            <button type="button" class="btn btn-link btn-sm p-0 text-muted" id="btnToggleSettings_<?= $chartId ?>" title="Show/hide settings"><i class="bi bi-gear"></i></button>
        </div>
    </div>
    <div class="loading-overlay" id="overlay_<?= $chartId ?>" style="display:none;">
        <div class="spinner-border text-primary me-2" role="status"></div> Loading...
    </div>

    <!-- Chart + Table body -->
    <div class="widget-linechart-body d-flex flex-column flex-grow-1" style="min-height: 0; overflow: hidden;">
        <!-- Chart Area (with overlay container) -->
        <div id="chartArea_<?= $chartId ?>" class="position-relative" style="flex: <?= $chartHeightPct ?> 1 0; min-height: 0;">
            <canvas id="<?= $chartId ?>" height="100px"></canvas>
            <!-- Settings overlay: floats on top of chart -->
            <div class="d-none position-absolute top-0 start-0 end-0 p-2 small rounded shadow-sm bg-body border" id="chartSettings_<?= $chartId ?>" style="z-index: 10; margin: 4px;">
                <div class="d-flex flex-wrap align-items-center justify-content-right gap-2">
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span class="text-muted">Range</span>
                        <select id="time_range_<?= $chartId ?>" class="form-select form-select-sm" style="width:auto;">
                            <option value="6h" <?= ($cfg->time_range ?? '') === '6h' ? 'selected' : '' ?>>Last 6 Hours</option>
                            <option value="12h" <?= ($cfg->time_range ?? '') === '12h' ? 'selected' : '' ?>>Last 12 Hours</option>
                            <option value="24h" <?= ($cfg->time_range ?? '24h') === '24h' ? 'selected' : '' ?>>Last 24 Hours</option>
                            <option value="7d" <?= ($cfg->time_range ?? '') === '7d' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30d" <?= ($cfg->time_range ?? '') === '30d' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="custom" <?= ($cfg->time_range ?? '') === 'custom' ? 'selected' : '' ?>>Custom range</option>
                        </select>
                    </label>
                    <div id="customRange_<?= $chartId ?>" class="d-none d-flex align-items-center gap-2 flex-wrap">
                        <label class="d-flex align-items-center gap-1 mb-0">
                            <span class="text-muted">Start (GMT+7)</span>
                            <input type="datetime-local" id="start_date_<?= $chartId ?>" class="form-control form-control-sm" style="width:auto;" title="GMT+7 (Bangkok)">
                        </label>
                        <label class="d-flex align-items-center gap-1 mb-0">
                            <span class="text-muted">End (GMT+7)</span>
                            <input type="datetime-local" id="end_date_<?= $chartId ?>" class="form-control form-control-sm" style="width:auto;" title="GMT+7 (Bangkok)">
                        </label>
                    </div>
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span class="text-muted">Interval</span>
                        <select id="time_interval_<?= $chartId ?>" class="form-select form-select-sm" style="width:auto;">
                            <option value="1m" <?= ($cfg->time_interval ?? '') === '1m' ? 'selected' : '' ?>>1m</option>
                            <option value="5m" <?= ($cfg->time_interval ?? '') === '5m' ? 'selected' : '' ?>>5m</option>
                            <option value="10m" <?= ($cfg->time_interval ?? '') === '10m' ? 'selected' : '' ?>>10m</option>
                            <option value="15m" <?= ($cfg->time_interval ?? '') === '15m' ? 'selected' : '' ?>>15m</option>
                            <option value="30m" <?= ($cfg->time_interval ?? '') === '30m' ? 'selected' : '' ?>>30m</option>
                            <option value="1h" <?= ($cfg->time_interval ?? '1h') === '1h' ? 'selected' : '' ?>>1h</option>
                            <option value="4h" <?= ($cfg->time_interval ?? '') === '4h' ? 'selected' : '' ?>>4h</option>
                            <option value="12h" <?= ($cfg->time_interval ?? '') === '12h' ? 'selected' : '' ?>>12h</option>
                            <option value="24h" <?= ($cfg->time_interval ?? '') === '24h' ? 'selected' : '' ?>>24h</option>
                        </select>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span class="text-muted">Limit</span>
                        <input type="number" id="limit_<?= $chartId ?>" class="form-control form-control-sm" style="width:4rem;" min="1" max="500" value="<?= (int)($cfg->limit ?? 24) ?>">
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span class="text-muted">Display</span>
                        <select id="display_<?= $chartId ?>" class="form-select form-select-sm" style="width:auto;">
                            <option value="min" <?= ($cfg->display ?? '') === 'min' ? 'selected' : '' ?>>Min</option>
                            <option value="max" <?= ($cfg->display ?? '') === 'max' ? 'selected' : '' ?>>Max</option>
                            <option value="avg" <?= ($cfg->display ?? 'avg') === 'avg' ? 'selected' : '' ?>>Avg</option>
                            <option value="first" <?= ($cfg->display ?? '') === 'first' ? 'selected' : '' ?>>First</option>
                            <option value="last" <?= ($cfg->display ?? '') === 'last' ? 'selected' : '' ?>>Last</option>
                        </select>
                    </label>
                    <label class="d-flex align-items-center gap-1 mb-0">
                        <span class="text-muted">Chart / Table</span>
                        <input type="range" id="chart_height_pct_<?= $chartId ?>" class="form-range" min="30" max="80" value="<?= $chartHeightPct ?>" style="width:6rem;">
                        <span id="chart_height_label_<?= $chartId ?>" class="text-muted text-nowrap"><?= $chartHeightPct ?>% / <?= $tableHeightPct ?>%</span>
                    </label>
                    <button type="button" class="btn btn-sm btn-primary" id="btnApply_<?= $chartId ?>">Apply</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnRefresh_<?= $chartId ?>">Refresh</button>
                    <hr class="my-2">
                    <button type="button" class="btn btn-sm btn-outline-success" id="btnExportExcel_<?= $chartId ?>" title="Export to CSV"><i class="bi bi-file-earmark-excel"></i> Export to CSV</button>
                </div>
            </div>
        </div>
        <!-- Table Area -->
        <div id="tableArea_<?= $chartId ?>" class="mt-1" style="flex: <?= $tableHeightPct ?> 1 0; min-height: 0; overflow-y: auto;">
            <table class="table table-sm table-hover table-striped align-middle mb-0 dashboard-table" id="<?= $tableId ?>">
            <thead>
                <tr>
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
        // DD/MM/YYYY HH:mm:ss
        const dd = String(date.getDate()).padStart(2, "0");
        const mm = String(date.getMonth() + 1).padStart(2, "0");
        const yyyy = date.getFullYear();
        const hh = String(date.getHours()).padStart(2, "0");
        const min = String(date.getMinutes()).padStart(2, "0");
        const ss = String(date.getSeconds()).padStart(2, "0");
        return `${dd}/${mm}/${yyyy} ${hh}:${min}`;
    }

    function toGMT7ForInput(date) {
        var s = date.toLocaleString('en-CA', {
            timeZone: 'Asia/Bangkok',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
        return s.replace(', ', 'T').replace(/\//g, '-').slice(0, 16);
    }

    function gmt7InputToUTC(value) {
        if (!value) return value;
        var d = new Date(value + '+07:00');
        return d.toISOString().slice(0, 16);
    }

    document.addEventListener("DOMContentLoaded", function() {

        const chartId = "<?= $chartId ?>";
        const ctx = document.getElementById(chartId).getContext("2d");
        const tableBody = document.querySelector("#<?= $tableId ?> tbody");
        const selectAllCheckbox<?= $chartId ?> = document.querySelector('.series-toggle[data-index="<?= $chartId ?>"]');

        const overlay = document.getElementById("overlay_<?= $chartId ?>")

        function applyChartTableRatio(pct) {
            pct = Math.max(30, Math.min(80, parseInt(pct, 10) || <?= $chartHeightPct ?>));
            const chartArea = document.getElementById('chartArea_<?= $chartId ?>');
            const tableArea = document.getElementById('tableArea_<?= $chartId ?>');
            const slider = document.getElementById('chart_height_pct_<?= $chartId ?>');
            const label = document.getElementById('chart_height_label_<?= $chartId ?>');
            if (chartArea) chartArea.style.flex = pct + ' 1 0';
            if (tableArea) tableArea.style.flex = (100 - pct) + ' 1 0';
            if (slider) slider.value = pct;
            if (label) label.textContent = pct + '% / ' + (100 - pct) + '%';
            if (typeof chart<?= $chartId ?> !== 'undefined') chart<?= $chartId ?>.resize();
        }

        let chart<?= $chartId ?> = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                spanGaps: 1000 * 60 * 60 * 24,
                pointRadius: 0.5,
                elements: {
                    line: {
                        tension: 0,
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'hour',
                            round: 'minute',
                            stepSize: 4,
                            displayFormats: {
                                hour: 'd/M-HH:mm'
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.08)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.55)',
                            source: 'auto',
                            maxRotation: 0,
                            minRotation: 0,
                            autoSkip: true,

                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: '',
                            color: 'rgba(255, 255, 255, 0.65)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.08)'
                        },
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.55)',
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
            var timeRangeVal = document.getElementById('time_range_<?= $chartId ?>').value;
            var rConfig = {
                time_range: timeRangeVal,
                time_interval: document.getElementById('time_interval_<?= $chartId ?>').value,
                limit: String(document.getElementById('limit_<?= $chartId ?>').value || 24),
                display: document.getElementById('display_<?= $chartId ?>').value,
            };
            if (timeRangeVal === 'custom') {
                var startEl = document.getElementById('start_date_<?= $chartId ?>');
                var endEl = document.getElementById('end_date_<?= $chartId ?>');
                if (startEl && startEl.value) rConfig.start_date = gmt7InputToUTC(startEl.value);
                if (endEl && endEl.value) rConfig.end_date = gmt7InputToUTC(endEl.value);
            }
            fetch('/dashboard/widget_data_dynamic/<?= $widget_id ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'rConfig=' + encodeURIComponent(JSON.stringify(rConfig))
                })
                .then(res => res.json())
                .then(data => {
                    chart<?= $chartId ?>.data.labels = data.labels;
                    chart<?= $chartId ?>.data.datasets = data.datasets;

                    if (rConfig.time_interval === '4h') {
                        chart<?= $chartId ?>.options.spanGaps = 1000 * 60 * 60 * 4;
                    } else if (rConfig.time_interval === '12h') {
                        chart<?= $chartId ?>.options.spanGaps = 1000 * 60 * 60 * 12;
                    } else if (rConfig.time_interval === '24h') {
                        chart<?= $chartId ?>.options.spanGaps = 1000 * 60 * 60 * 24;
                    } else {
                        chart<?= $chartId ?>.options.spanGaps = 1000 * 60 * 60;
                    }


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
                        xTitleText = startDate + ' to ' + endDate;
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

        function updateChartLabel() {
            var timeRangeLabels = {
                '6h': 'Last 6 Hours',
                '12h': 'Last 12 Hours',
                '24h': 'Last 24 Hours',
                '7d': 'Last 7 Days',
                '30d': 'Last 30 Days',
                'custom': 'Custom range'
            };
            var sel = document.getElementById('time_range_<?= $chartId ?>');
            var chartLabelEl = document.getElementById('chartLabel_<?= $chartId ?>');
            if (chartLabelEl && sel) chartLabelEl.textContent = (chartLabelEl.dataset.baseLabel || 'Line Chart') + ' (' + (timeRangeLabels[sel.value] || '') + ')';
        }

        function exportChartToExcel() {
            var ch = chart<?= $chartId ?>;
            var labels = ch.data.labels || [];
            var datasets = ch.data.datasets || [];
            if (labels.length === 0 && datasets.length === 0) {
                alert('No data to export');
                return;
            }
            var escapeCsv = function(v) {
                var s = String(v == null ? '' : v);
                if (/[,"\n\r]/.test(s)) return '"' + s.replace(/"/g, '""') + '"';
                return s;
            };
            var rows = [];
            var header = ['Timestamp'].concat(datasets.map(function(d) {
                return d.label || '';
            }));
            rows.push(header.map(escapeCsv).join(','));
            for (var i = 0; i < labels.length; i++) {
                var ts = labels[i];
                var dateStr = ts ? formatDate2(new Date(ts)) : '';
                var row = [dateStr].concat(datasets.map(function(d) {
                    return d.data[i] != null ? d.data[i] : '';
                }));
                rows.push(row.map(escapeCsv).join(','));
            }
            var csv = '\uFEFF' + rows.join('\r\n');
            var blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8'
            });
            var now = new Date();
            var dateStr = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0') + '_' + String(now.getHours()).padStart(2, '0') + String(now.getMinutes()).padStart(2, '0');
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'chart_export_' + dateStr + '.csv';
            a.click();
            URL.revokeObjectURL(a.href);
        }

        document.getElementById('btnToggleSettings_<?= $chartId ?>').addEventListener('click', function() {
            var el = document.getElementById('chartSettings_<?= $chartId ?>');
            el.classList.toggle('d-none');
            this.innerHTML = el.classList.contains('d-none') ? '<i class="bi bi-gear"></i>' : '<i class="bi bi-x"></i>';
        });
        document.getElementById('chart_height_pct_<?= $chartId ?>').addEventListener('input', function() {
            applyChartTableRatio(this.value);
        });
        document.getElementById('btnApply_<?= $chartId ?>').addEventListener('click', loadChartData);
        document.getElementById('btnRefresh_<?= $chartId ?>').addEventListener('click', loadChartData);
        document.getElementById('btnExportExcel_<?= $chartId ?>').addEventListener('click', exportChartToExcel);
        document.getElementById('time_range_<?= $chartId ?>').addEventListener('change', function() {
            var customRow = document.getElementById('customRange_<?= $chartId ?>');
            if (this.value === 'custom') {
                customRow.classList.remove('d-none');
                var startEl = document.getElementById('start_date_<?= $chartId ?>');
                var endEl = document.getElementById('end_date_<?= $chartId ?>');
                if (startEl && !startEl.value) {
                    var end = new Date();
                    var start = new Date(end.getTime() - 24 * 60 * 60 * 1000);
                    startEl.value = toGMT7ForInput(start);
                    endEl.value = toGMT7ForInput(end);
                }
            } else {
                customRow.classList.add('d-none');
            }
            updateChartLabel();
        });

        if (document.getElementById('time_range_<?= $chartId ?>').value === 'custom') {
            document.getElementById('customRange_<?= $chartId ?>').classList.remove('d-none');
        }

        applyChartTableRatio(<?= $chartHeightPct ?>);

        loadChartData();
        setInterval(loadChartData, 900000); // refresh every 15 minutes
    });
</script>