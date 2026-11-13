<?php
$cfg = $widget->config ?? (object)[];
$chartId = 'barchart_' . $widget_id;
$borderClass = widget_border_class($cfg, 'border rounded');
$bgClass = widget_bg_class($cfg);
$styleAttr = widget_style_attr($cfg, 'height: 100%; overflow: hidden');
?>
<div class="widget-barchart <?= $borderClass ?> <?= $bgClass ?> p-2 d-flex flex-column" style="<?= htmlspecialchars($styleAttr, ENT_QUOTES, 'UTF-8') ?>">
    <div class="small text-muted mb-1"><?= htmlspecialchars($cfg->label ?? 'Bar Chart') ?></div>
    <div style="flex: 1; min-height: 0;">
        <canvas id="<?= $chartId ?>"></canvas>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const canvas = document.getElementById('<?= $chartId ?>');
  if (!canvas || typeof Chart === 'undefined') return;

  const ctx = canvas.getContext('2d');
  const setpointLinePlugin = {
    id: 'setpointLine',
    afterDatasetsDraw(chart, args, pluginOptions) {
      if (!pluginOptions || pluginOptions.value === null || pluginOptions.value === undefined || !Number.isFinite(Number(pluginOptions.value))) return;
      const { ctx, chartArea, scales } = chart;
      const color = pluginOptions.color || '#f59e0b';
      const value = Number(pluginOptions.value);
      ctx.save();
      ctx.strokeStyle = color;
      ctx.lineWidth = 2;
      ctx.beginPath();
      if ((pluginOptions.indexAxis || 'y') === 'y') {
        const x = scales.x.getPixelForValue(value);
        ctx.moveTo(x, chartArea.top);
        ctx.lineTo(x, chartArea.bottom);
      } else {
        const y = scales.y.getPixelForValue(value);
        ctx.moveTo(chartArea.left, y);
        ctx.lineTo(chartArea.right, y);
      }
      ctx.stroke();
      ctx.restore();
    }
  };
  let chart = null;

  function buildChart(data) {
    if (chart) {
      chart.destroy();
      chart = null;
    }

    chart = new Chart(ctx, {
      type: data.chartType || 'bar',
      data: {
        labels: data.labels || [],
        datasets: (data.datasets || []).map((ds) => ({
          type: ds.type || 'bar',
          label: ds.label || '',
          indexAxis: data.indexAxis || 'y',
          data: ds.data || [],
          backgroundColor: ds.backgroundColor || [],
          borderColor: ds.borderColor || [],
          borderWidth: ds.borderWidth || 1,
          borderRadius: ds.borderRadius || 6,
          maxBarThickness: (data.indexAxis || 'y') === 'y' ? 24 : 30,
          unit: ds.unit || data.yUnit || ''
        }))
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: data.indexAxis || 'y',
        animation: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                const unit = context.dataset.unit || '';
                const raw = context.raw;
                const value = typeof raw === 'object'
                  ? (raw?.x ?? raw?.y ?? '')
                  : (raw ?? context.parsed?.x ?? context.parsed?.y ?? '');
                return `${context.label}: ${value}${unit ? ' ' + unit : ''}`;
              }
            }
          },
          setpointLine: {
            value: data.setpoint_value,
            color: data.setpoint_color || '#f59e0b',
            indexAxis: data.indexAxis || 'y'
          }
        },
        scales: {
          x: {
            min: data.indexAxis === 'y' && data.yMin !== null && data.yMin !== '' ? Number(data.yMin) : undefined,
            max: data.indexAxis === 'y' && data.yMax !== null && data.yMax !== '' ? Number(data.yMax) : undefined,
            ticks: {
              callback: function(value) {
                if ((data.indexAxis || 'y') === 'x') {
                  return this.chart?.data?.labels?.[value] ?? value;
                }
                const unit = this.chart?.data?.datasets?.[0]?.unit || '';
                return `${value}${unit ? ' ' + unit : ''}`;
              }
            }
          },
          y: {
            min: data.indexAxis === 'x' && data.yMin !== null && data.yMin !== '' ? Number(data.yMin) : undefined,
            max: data.indexAxis === 'x' && data.yMax !== null && data.yMax !== '' ? Number(data.yMax) : undefined
          }
        }
      },
      plugins: [setpointLinePlugin]
    });
  }

  function loadBarChartData() {
    fetch('/dashboard/widget_data/<?= $widget_id ?>?t=' + Date.now())
      .then(res => res.json())
      .then(data => {
        buildChart(data);
      })
      .catch(err => console.error('[bar_chart] load failed:', err));
  }

  loadBarChartData();
  setInterval(loadBarChartData, 15000);
});
</script>
