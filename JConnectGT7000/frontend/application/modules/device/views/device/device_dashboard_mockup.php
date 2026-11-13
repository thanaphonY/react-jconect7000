<?php
/**
 * PEMON-style device Dashboard: KPI summary, DC / AC / IO panels and system status on one page.
 * Uses live latest_metrics when keys exist; otherwise sample values.
 */
$latest = isset($latest_metrics) && is_array($latest_metrics) ? $latest_metrics : [];

$numVal = function ($key) use ($latest) {
  if (!isset($latest[$key]) || !is_array($latest[$key])) {
    return null;
  }
  $value = $latest[$key]['value'] ?? null;
  return is_numeric($value) ? (float) $value : null;
};

$fmt = function ($value, $decimals = 1) {
  if ($value === null) {
    return null;
  }
  return number_format((float) $value, $decimals, '.', '');
};

// Live-or-simulated values come from device_display_helper so the Layout tab shows the same numbers.
$dcBars = [];
$acBars = [];
$dcLive = false;
$acLive = false;
for ($i = 1; $i <= 4; $i++) {
  $d = device_display_value($latest, "dc.string{$i}.voltage_v");
  $dcLive = $dcLive || $d['live'];
  $dcBars[] = ['index' => $i, 'value' => $d['value'], 'label' => 'S' . $i];
}
for ($i = 1; $i <= 6; $i++) {
  $d = device_display_value($latest, "ac.sys1.line{$i}.voltage_v");
  $acLive = $acLive || $d['live'];
  $acBars[] = ['index' => $i, 'value' => $d['value'], 'label' => 'L' . $i];
}

$temp = device_display_value($latest, 'io.sys1.temp1_c');
$hum = device_display_value($latest, 'io.sys1.hum1_pct');
$tempVal = $temp['value'];
$tempLive = $temp['live'];
$humVal = $hum['value'];
$humLive = $hum['live'];

$inputs = [];
$outputs = [];
for ($i = 1; $i <= 8; $i++) {
  $in = $numVal("io.sys1.in{$i}");
  $out = $numVal("io.sys1.out{$i}");
  $inputs[$i] = [
    'on' => $in === null ? true : ((int) $in === 0),
    'live' => $in !== null,
    'fault' => $in !== null && (int) $in === 1,
  ];
  $outputs[$i] = [
    'on' => $out !== null && (int) $out === 1,
    'live' => $out !== null,
    'present' => $i <= 4,
  ];
}

$dcMin = 0.0;
$dcMax = 60.0;
$acMin = 0.0;
$acMax = 260.0;
$barPct = function ($value, $min, $max) {
  $span = $max - $min;
  if ($span <= 0) {
    return 0;
  }
  $pct = (($value - $min) / $span) * 100;
  return max(4, min(100, $pct));
};

$stats = function (array $bars) {
  $values = array_column($bars, 'value');
  return [
    'avg' => array_sum($values) / max(1, count($values)),
    'min' => min($values),
    'max' => max($values),
  ];
};
$dcStats = $stats($dcBars);
$acStats = $stats($acBars);

$dcFaults = [
  '>>DC Sys1:No.1',
  'Cell High Float',
  'Cell Low Float',
  'Sys High Float',
  'Cell Low Disch',
  'Batt Discharge',
  '>>DC Sys2:No.2',
  'Cell High Float',
  'Sys High Float',
];
$acFaults = [
  '>>AC Sys1',
  'High AC Freq',
  'High AC Voltage',
  'High AC Current',
  'High AC Power',
  'Low AC Power',
  'Line 1 Fault',
  'Line 2 Fault',
  'Line 3 Fault',
  'Line 4 Fault',
];
$ioFaults = [
  '>>IO Sys1',
  'Room Temp Fault',
  'Humidity Fault',
];
$sysFaults = [
  'RST OK',
  'DCU Fail',
  '#1 Comm Err',
  'ACU Fail',
  'IOU OK',
  'SHT Fail',
  '#DC supply Fail',
];

// ">>Group" lines start a new group; every other line is an alarm in that group.
$groupFaults = function (array $lines) {
  $groups = [];
  $current = null;
  foreach ($lines as $line) {
    if (strpos($line, '>>') === 0) {
      $groups[] = ['title' => trim(substr($line, 2)), 'items' => []];
      $current = count($groups) - 1;
      continue;
    }
    if ($current === null) {
      $groups[] = ['title' => '', 'items' => []];
      $current = 0;
    }
    $groups[$current]['items'][] = $line;
  }
  return $groups;
};
$countFaults = function (array $lines) {
  return count(array_filter($lines, function ($line) {
    return strpos($line, '>>') !== 0;
  }));
};

$sysItems = array_map(function ($line) {
  return [
    'name' => preg_replace('/\s+(OK|Fail|Err)$/i', '', $line),
    'state' => preg_match('/(OK|Fail|Err)$/i', $line, $m) ? $m[1] : 'Fault',
    'ok' => (bool) preg_match('/\bOK$/i', $line),
  ];
}, $sysFaults);


$dcAlarmCount = $countFaults($dcFaults);
$acAlarmCount = $countFaults($acFaults);
$ioAlarmCount = $countFaults($ioFaults);
$sysAlarmCount = count(array_filter($sysItems, function ($item) {
  return !$item['ok'];
}));
$totalAlarms = $dcAlarmCount + $acAlarmCount + $ioAlarmCount + $sysAlarmCount;

$lastUpdate = null;
foreach ($latest as $key => $row) {
  if (!preg_match('/^(dc|ac|io)\./', (string) $key) || empty($row['timestamp'])) {
    continue;
  }
  $ts = strtotime((string) $row['timestamp']);
  if ($ts !== false && ($lastUpdate === null || $ts > $lastUpdate)) {
    $lastUpdate = $ts;
  }
}

// Mock 24h hourly trend that ends at the current (live or sample) value.
$trendEnd = $lastUpdate ?: time();
$trendEnd -= $trendEnd % 3600;
$trendLabels = [];
for ($k = 23; $k >= 0; $k--) {
  $trendLabels[] = date('H:i', $trendEnd - $k * 3600);
}
$mockTrend = function ($current, $amp, $noise, $phase, $seed, $decimals = 1, $clamp = null) {
  mt_srand($seed);
  $offset = $amp * sin(2 * M_PI * 23 / 24 + $phase);
  $points = [];
  for ($k = 0; $k < 24; $k++) {
    $v = $k === 23
      ? $current
      : $current + $amp * sin(2 * M_PI * $k / 24 + $phase) - $offset + $noise * (mt_rand() / mt_getrandmax() - 0.5);
    if ($clamp && $k !== 23) {
      $v = max($clamp[0], min($clamp[1], $v));
    }
    $points[] = round($v, $decimals);
  }
  mt_srand();
  return $points;
};
$dcTrend = [];
foreach ($dcBars as $bar) {
  $dcTrend[] = ['label' => 'String ' . $bar['index'], 'data' => $mockTrend($bar['value'], 0, 0, 0, 100 + $bar['index'])];
}
$acTrend = [];
foreach ($acBars as $bar) {
  $acTrend[] = ['label' => 'Line ' . $bar['index'], 'data' => $mockTrend($bar['value'], 3, 2, $bar['index'] * 0.6, 200 + $bar['index'], 0, [219, 230])];
}
$envTrend = [
  ['label' => 'Temperature (°C)', 'data' => $mockTrend($tempVal, 2.5, 0.6, -1.2, 301), 'axis' => 'y'],
  ['label' => 'Humidity (%)', 'data' => $mockTrend($humVal, 8, 3, 1.8, 302, 0), 'axis' => 'y1'],
];

$sourceTag = function ($live) {
  return $live
    ? '<span class="ddm-tag ddm-tag--live">live</span>'
    : '';
};
?>
<style>
  #device-dashboard-mockup {
    --ddm-border: var(--bs-border-color-translucent);
    --ddm-surface: var(--bs-body-bg);
    --ddm-subtle: var(--bs-tertiary-bg);
    --ddm-muted: var(--bs-secondary-color);
    --ddm-accent: var(--bs-primary);
    --ddm-ok: var(--bs-success);
    --ddm-fault: var(--bs-danger);
    --ddm-warn: var(--bs-warning);
    --ddm-radius: 0.65rem;
  }

  /* Header strip */
  #device-dashboard-mockup .ddm-top {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 0.75rem;
    margin-bottom: 0.6rem;
  }
  #device-dashboard-mockup .ddm-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.3rem;
  }
  #device-dashboard-mockup .ddm-overall {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
  }
  #device-dashboard-mockup .ddm-overall__dot {
    width: 0.7rem;
    height: 0.7rem;
    border-radius: 50%;
    background: var(--ddm-ok);
    box-shadow: 0 0 0 4px rgba(var(--bs-success-rgb), 0.18);
  }
  #device-dashboard-mockup .ddm-overall--alarm .ddm-overall__dot {
    background: var(--ddm-fault);
    box-shadow: 0 0 0 4px rgba(var(--bs-danger-rgb), 0.18);
  }
  #device-dashboard-mockup .ddm-overall__sub {
    font-weight: 400;
    font-size: 0.82rem;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.2rem 0.6rem;
    border: 1px solid var(--ddm-border);
    border-radius: 999px;
    font-size: 0.76rem;
    font-weight: 600;
    background: var(--ddm-subtle);
  }
  #device-dashboard-mockup .ddm-chip i {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-chip--ok i { background: var(--ddm-ok); }
  #device-dashboard-mockup .ddm-chip--fault i { background: var(--ddm-fault); }
  #device-dashboard-mockup .ddm-chip--fault { color: var(--ddm-fault); }

  /* KPI tiles */
  #device-dashboard-mockup .ddm-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap: 0.75rem;
    margin-bottom: 0.75rem;
  }
  #device-dashboard-mockup .ddm-kpi {
    position: relative;
    padding: 0.5rem 0.9rem;
    border: 1px solid var(--ddm-border);
    border-radius: var(--ddm-radius);
    background: var(--ddm-surface);
    min-width: 0;
  }
  #device-dashboard-mockup .ddm-kpi__label {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-kpi__value {
    margin-top: 0.1rem;
    font-size: 1.4rem;
    font-weight: 700;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
  }
  #device-dashboard-mockup .ddm-kpi__unit {
    margin-left: 0.2rem;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-kpi__meta {
    margin-top: 0.15rem;
    font-size: 0.74rem;
    color: var(--ddm-muted);
    font-variant-numeric: tabular-nums;
  }
  #device-dashboard-mockup .ddm-kpi--alarm .ddm-kpi__value { color: var(--ddm-fault); }
  #device-dashboard-mockup .ddm-kpi--ok .ddm-kpi__value { color: var(--ddm-ok); }

  #device-dashboard-mockup .ddm-tag {
    padding: 0.05rem 0.4rem;
    border-radius: 0.3rem;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: lowercase;
    letter-spacing: 0;
    color: var(--ddm-muted);
    background: var(--ddm-subtle);
  }
  #device-dashboard-mockup .ddm-tag--live {
    color: var(--ddm-ok);
    background: rgba(var(--bs-success-rgb), 0.12);
  }

  /* Panels */
  #device-dashboard-mockup .ddm-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    border: 1px solid var(--ddm-border);
    border-radius: var(--ddm-radius);
    background: var(--ddm-surface);
    overflow: hidden;
  }
  #device-dashboard-mockup .ddm-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.55rem 0.9rem;
    border-bottom: 1px solid var(--ddm-border);
  }
  #device-dashboard-mockup .ddm-panel__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0;
    font-size: 0.95rem;
    font-weight: 650;
  }
  #device-dashboard-mockup .ddm-panel__title small {
    font-weight: 400;
    font-size: 0.78rem;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-panel__body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
    padding: 0.6rem 0.9rem;
  }
  #device-dashboard-mockup .ddm-panel__foot {
    margin-top: auto;
    border-top: 1px solid var(--ddm-border);
    background: var(--ddm-subtle);
  }
  #device-dashboard-mockup .ddm-count {
    min-width: 1.5rem;
    padding: 0.1rem 0.5rem;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-align: center;
    color: #fff;
    background: var(--ddm-fault);
  }
  #device-dashboard-mockup .ddm-count--zero {
    color: var(--ddm-ok);
    background: rgba(var(--bs-success-rgb), 0.12);
  }

  /* Bar chart */
  #device-dashboard-mockup .ddm-chart {
    position: relative;
    display: grid;
    flex: 1 1 0;
    grid-template-columns: 2.5rem 1fr;
    grid-template-rows: minmax(0, 1fr) auto;
    column-gap: 0.5rem;
    min-height: 96px;
  }
  #device-dashboard-mockup .ddm-axis {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    font-size: 0.7rem;
    text-align: right;
    color: var(--ddm-muted);
    font-variant-numeric: tabular-nums;
    transform: translateY(-0.4em);
  }
  #device-dashboard-mockup .ddm-plot {
    position: relative;
    min-height: 0;
    background-image: linear-gradient(var(--ddm-border) 1px, transparent 1px);
    background-size: 100% 50%;
    border-bottom: 1px solid var(--ddm-border);
  }
  #device-dashboard-mockup .ddm-bars {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: flex-end;
    justify-content: space-around;
    gap: 0.5rem;
    padding: 0 0.25rem;
  }
  #device-dashboard-mockup .ddm-bar {
    flex: 1 1 0;
    max-width: 56px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
  }
  #device-dashboard-mockup .ddm-bar__value {
    margin-bottom: 0.25rem;
    font-size: 0.72rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
  #device-dashboard-mockup .ddm-bar__fill {
    width: 100%;
    min-height: 4px;
    border-radius: 0.3rem 0.3rem 0 0;
    background: var(--ddm-accent);
    opacity: 0.85;
  }
  #device-dashboard-mockup .ddm-labels {
    display: flex;
    justify-content: space-around;
    gap: 0.5rem;
    padding: 0.35rem 0.25rem 0;
    grid-column: 2;
  }
  #device-dashboard-mockup .ddm-labels span {
    flex: 1 1 0;
    max-width: 56px;
    font-size: 0.72rem;
    text-align: center;
    color: var(--ddm-muted);
  }

  /* Alarm list */
  #device-dashboard-mockup .ddm-alarms {
    height: 64px;
    overflow: auto;
    padding: 0.45rem 0.9rem 0.5rem;
  }
  #device-dashboard-mockup .ddm-alarms__group {
    margin: 0.25rem 0 0.25rem;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-alarms__group:first-child { margin-top: 0; }
  #device-dashboard-mockup .ddm-alarms ul {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin: 0 0 0.15rem;
    padding: 0;
    list-style: none;
    gap: 0.25rem;
  }
  #device-dashboard-mockup .ddm-alarm {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.12rem 0.5rem;
    border-radius: 0.35rem;
    font-size: 0.75rem;
    color: var(--bs-danger-text-emphasis);
    background: var(--bs-danger-bg-subtle);
  }
  #device-dashboard-mockup .ddm-alarm::before {
    content: "";
    width: 0.4rem;
    height: 0.4rem;
    border-radius: 50%;
    background: var(--ddm-fault);
  }
  #device-dashboard-mockup .ddm-alarm--ok {
    color: var(--bs-success-text-emphasis);
    background: var(--bs-success-bg-subtle);
  }
  #device-dashboard-mockup .ddm-alarm--ok::before { background: var(--ddm-ok); }
  #device-dashboard-mockup .ddm-empty {
    font-size: 0.8rem;
    color: var(--ddm-muted);
  }

  /* Environment */

  /* IO grid */
  #device-dashboard-mockup .ddm-io-title {
    margin: 0 0 0.3rem;
    font-size: 0.72rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-io {
    display: grid;
    grid-template-columns: repeat(8, minmax(0, 1fr));
    gap: 0.3rem;
    margin-bottom: 0.6rem;
  }
  #device-dashboard-mockup .ddm-io__cell {
    padding: 0.35rem 0.15rem;
    border: 1px solid var(--ddm-border);
    border-radius: 0.4rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-io__cell--on {
    border-color: rgba(var(--bs-success-rgb), 0.45);
    background: rgba(var(--bs-success-rgb), 0.1);
    color: var(--ddm-ok);
  }
  #device-dashboard-mockup .ddm-io__cell--fault {
    border-color: rgba(var(--bs-danger-rgb), 0.5);
    background: rgba(var(--bs-danger-rgb), 0.1);
    color: var(--ddm-fault);
  }
  #device-dashboard-mockup .ddm-io__cell--empty {
    border-style: dashed;
    opacity: 0.5;
  }
  #device-dashboard-mockup .ddm-legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.9rem;
    font-size: 0.72rem;
    color: var(--ddm-muted);
  }
  #device-dashboard-mockup .ddm-legend i {
    display: inline-block;
    width: 0.6rem;
    height: 0.6rem;
    margin-right: 0.3rem;
    border-radius: 0.15rem;
    border: 1px solid var(--ddm-border);
    vertical-align: -1px;
  }

  /* Trend line charts */
  #device-dashboard-mockup .ddm-trend {
    display: flex;
    flex: 1.4 1 0;
    flex-direction: column;
    min-height: 140px;
    margin-top: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px dashed var(--ddm-border);
  }
  #device-dashboard-mockup .ddm-trend__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.2rem;
  }
  #device-dashboard-mockup .ddm-trend__canvas {
    position: relative;
    flex: 1 1 auto;
    min-height: 0;
  }
  #device-dashboard-mockup .ddm-trend--first {
    margin-top: 0;
    padding-top: 0;
    border-top: 0;
  }
  #device-dashboard-mockup .ddm-io-block {
    margin-top: 0.5rem;
    padding-top: 0.45rem;
    border-top: 1px dashed var(--ddm-border);
  }

  @media (max-width: 575.98px) {
    #device-dashboard-mockup .ddm-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    #device-dashboard-mockup .ddm-kpi__value { font-size: 1.35rem; }
  }
</style>

<?php
$renderAlarms = function (array $lines) use ($groupFaults) {
  $groups = $groupFaults($lines);
  if (empty($groups)) {
    echo '<div class="ddm-empty">No active alarms</div>';
    return;
  }
  foreach ($groups as $group) {
    if ($group['title'] !== '') {
      echo '<div class="ddm-alarms__group">' . htmlspecialchars($group['title']) . '</div>';
    }
    if (empty($group['items'])) {
      echo '<div class="ddm-empty">No active alarms</div>';
      continue;
    }
    echo '<ul>';
    foreach ($group['items'] as $item) {
      echo '<li class="ddm-alarm">' . htmlspecialchars($item) . '</li>';
    }
    echo '</ul>';
  }
};

$renderChart = function (array $bars, $min, $max, $decimals) use ($barPct, $fmt) {
  ?>
  <div class="ddm-chart">
    <div class="ddm-axis">
      <span><?= htmlspecialchars($fmt($max, 0)) ?></span>
      <span><?= htmlspecialchars($fmt(($min + $max) / 2, 0)) ?></span>
      <span><?= htmlspecialchars($fmt($min, 0)) ?></span>
    </div>
    <div class="ddm-plot">
      <div class="ddm-bars">
        <?php foreach ($bars as $bar) { ?>
          <div class="ddm-bar" title="<?= htmlspecialchars($bar['label'] . ': ' . $fmt($bar['value'], $decimals) . ' V') ?>">
            <div class="ddm-bar__value"><?= htmlspecialchars($fmt($bar['value'], $decimals)) ?></div>
            <div class="ddm-bar__fill" style="height: <?= (float) $barPct($bar['value'], $min, $max) ?>%"></div>
          </div>
        <?php } ?>
      </div>
    </div>
    <div class="ddm-labels">
      <?php foreach ($bars as $bar) { ?>
        <span><?= htmlspecialchars($bar['label']) ?></span>
      <?php } ?>
    </div>
  </div>
  <?php
};

$renderTrend = function ($id, $title, $first = false) {
  ?>
  <div class="ddm-trend<?= $first ? ' ddm-trend--first' : '' ?>">
    <div class="ddm-trend__head">
      <div class="ddm-io-title mb-0"><?= htmlspecialchars($title) ?></div>
    </div>
    <div class="ddm-trend__canvas"><canvas id="<?= htmlspecialchars($id) ?>"></canvas></div>
  </div>
  <?php
};

$renderCount = function ($count) {
  $cls = $count > 0 ? 'ddm-count' : 'ddm-count ddm-count--zero';
  return '<span class="' . $cls . '" title="Active alarms">' . (int) $count . '</span>';
};
?>

<div id="device-dashboard-mockup">
  <!-- Overall status + SYS -->
  <div class="ddm-top">
    <div class="ddm-overall <?= $totalAlarms > 0 ? 'ddm-overall--alarm' : '' ?>">
      <span class="ddm-overall__dot"></span>
      <span><?= $totalAlarms > 0 ? $totalAlarms . ' active alarms' : 'All systems normal' ?></span>
      <span class="ddm-overall__sub">
        · Last update <?= $lastUpdate ? htmlspecialchars(date('d/m/Y H:i:s', $lastUpdate)) : '—' ?>
      </span>
    </div>
    <div class="ddm-chips" title="System">
      <?php foreach ($sysItems as $item) { ?>
        <span class="ddm-chip <?= $item['ok'] ? 'ddm-chip--ok' : 'ddm-chip--fault' ?>"><i></i><?= htmlspecialchars($item['name']) ?> · <?= htmlspecialchars($item['state']) ?></span>
      <?php } ?>
    </div>
  </div>

  <!-- KPIs -->
  <div class="ddm-kpis">
    <div class="ddm-kpi">
      <div class="ddm-kpi__label">DC String avg <?= $sourceTag($dcLive) ?></div>
      <div class="ddm-kpi__value"><?= htmlspecialchars($fmt($dcStats['avg'])) ?><span class="ddm-kpi__unit">V</span></div>
      <div class="ddm-kpi__meta"><?= htmlspecialchars($fmt($dcStats['min'])) ?> – <?= htmlspecialchars($fmt($dcStats['max'])) ?> V</div>
    </div>
    <div class="ddm-kpi">
      <div class="ddm-kpi__label">AC Line avg <?= $sourceTag($acLive) ?></div>
      <div class="ddm-kpi__value"><?= htmlspecialchars($fmt($acStats['avg'], 0)) ?><span class="ddm-kpi__unit">V</span></div>
      <div class="ddm-kpi__meta"><?= htmlspecialchars($fmt($acStats['min'], 0)) ?> – <?= htmlspecialchars($fmt($acStats['max'], 0)) ?> V</div>
    </div>
    <div class="ddm-kpi">
      <div class="ddm-kpi__label">Temperature <?= $sourceTag($tempLive) ?></div>
      <div class="ddm-kpi__value"><?= htmlspecialchars($fmt($tempVal)) ?><span class="ddm-kpi__unit">°C</span></div>
      <div class="ddm-kpi__meta">Room · IO Sys1</div>
    </div>
    <div class="ddm-kpi">
      <div class="ddm-kpi__label">Humidity <?= $sourceTag($humLive) ?></div>
      <div class="ddm-kpi__value"><?= htmlspecialchars($fmt($humVal, 0)) ?><span class="ddm-kpi__unit">%</span></div>
      <div class="ddm-kpi__meta">Room · IO Sys1</div>
    </div>
    <div class="ddm-kpi <?= $totalAlarms > 0 ? 'ddm-kpi--alarm' : 'ddm-kpi--ok' ?>">
      <div class="ddm-kpi__label">Active alarms</div>
      <div class="ddm-kpi__value"><?= (int) $totalAlarms ?></div>
      <div class="ddm-kpi__meta">DC <?= $dcAlarmCount ?> · AC <?= $acAlarmCount ?> · IO <?= $ioAlarmCount ?> · SYS <?= $sysAlarmCount ?></div>
    </div>
  </div>

  <!-- DC + AC + IO -->
  <div class="row g-3 ddm-row">
    <div class="col-lg-4">
      <section class="ddm-panel" data-ddm-pane="dc">
        <div class="ddm-panel__head">
          <h6 class="ddm-panel__title">DC <small>VB1 · String voltage (V)</small></h6>
          <?= $sourceTag($dcLive) ?>
        </div>
        <div class="ddm-panel__body">
          <?php $renderChart($dcBars, $dcMin, $dcMax, 1); ?>
          <?php $renderTrend('ddm-trend-dc', 'String voltage · last 24h'); ?>
        </div>
        <div class="ddm-panel__foot">
          <div class="ddm-panel__head">
            <h6 class="ddm-panel__title">Alarms</h6>
            <?= $renderCount($dcAlarmCount) ?>
          </div>
          <div class="ddm-alarms"><?php $renderAlarms($dcFaults); ?></div>
        </div>
      </section>
    </div>

    <div class="col-lg-4">
      <section class="ddm-panel" data-ddm-pane="ac">
        <div class="ddm-panel__head">
          <h6 class="ddm-panel__title">AC <small>ACU1 · Line voltage (V)</small></h6>
          <?= $sourceTag($acLive) ?>
        </div>
        <div class="ddm-panel__body">
          <?php $renderChart($acBars, $acMin, $acMax, 0); ?>
          <?php $renderTrend('ddm-trend-ac', 'Line voltage · last 24h'); ?>
        </div>
        <div class="ddm-panel__foot">
          <div class="ddm-panel__head">
            <h6 class="ddm-panel__title">Alarms</h6>
            <?= $renderCount($acAlarmCount) ?>
          </div>
          <div class="ddm-alarms"><?php $renderAlarms($acFaults); ?></div>
        </div>
      </section>
    </div>

    <div class="col-lg-4">
      <section class="ddm-panel" data-ddm-pane="io">
        <div class="ddm-panel__head">
          <h6 class="ddm-panel__title">IO <small>IOU · Environment &amp; dry contacts</small></h6>
          <?= $sourceTag($tempLive || $humLive) ?>
        </div>
        <div class="ddm-panel__body">
          <?php $renderTrend('ddm-trend-env', 'Temperature & humidity · last 24h', true); ?>

          <div class="ddm-io-block">
          <div class="ddm-io-title">Inputs</div>
          <div class="ddm-io">
            <?php for ($i = 1; $i <= 8; $i++) {
              if (!empty($inputs[$i]['fault'])) {
                $cls = 'ddm-io__cell--fault';
                $state = 'Alarm';
              } elseif (!empty($inputs[$i]['on'])) {
                $cls = 'ddm-io__cell--on';
                $state = 'Normal';
              } else {
                $cls = '';
                $state = 'Off';
              }
              ?>
              <div class="ddm-io__cell <?= $cls ?>" title="<?= $state ?>">IN<?= $i ?></div>
            <?php } ?>
          </div>

          <div class="ddm-io-title">Outputs</div>
          <div class="ddm-io">
            <?php for ($i = 1; $i <= 8; $i++) {
              if (empty($outputs[$i]['present'])) {
                echo '<div class="ddm-io__cell ddm-io__cell--empty" title="N/A">OUT' . $i . '</div>';
                continue;
              }
              $on = !empty($outputs[$i]['on']);
              ?>
              <div class="ddm-io__cell <?= $on ? 'ddm-io__cell--on' : '' ?>" title="<?= $on ? 'On' : 'Off' ?>">OUT<?= $i ?></div>
            <?php } ?>
          </div>

          <div class="ddm-legend">
            <span><i style="background: rgba(var(--bs-success-rgb), 0.25)"></i>Normal / On</span>
            <span><i style="background: rgba(var(--bs-danger-rgb), 0.25)"></i>Alarm</span>
            <span><i></i>Off</span>
            <span><i style="border-style: dashed"></i>Not fitted</span>
          </div>
          </div>
        </div>
        <div class="ddm-panel__foot">
          <div class="ddm-panel__head">
            <h6 class="ddm-panel__title">Alarms</h6>
            <?= $renderCount($ioAlarmCount) ?>
          </div>
          <div class="ddm-alarms"><?php $renderAlarms($ioFaults); ?></div>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
(function () {
  const root = document.getElementById('device-dashboard-mockup');
  if (!root || typeof Chart === 'undefined') return;

  const labels = <?= json_encode($trendLabels) ?>;
  const trends = {
    dc: <?= json_encode($dcTrend) ?>,
    ac: <?= json_encode($acTrend) ?>,
    env: <?= json_encode($envTrend) ?>
  };
  const palette = ['#2f6fed', '#16a394', '#e8833a', '#9b59d0', '#d6457a', '#8a9a12'];

  function themeColors() {
    const css = getComputedStyle(root);
    return {
      grid: css.getPropertyValue('--bs-border-color-translucent').trim() || 'rgba(0,0,0,.1)',
      text: css.getPropertyValue('--bs-secondary-color').trim() || '#6c757d'
    };
  }

  function axis(colors, extra) {
    return Object.assign({
      grid: { color: colors.grid },
      border: { display: false },
      ticks: { color: colors.text, font: { size: 11 }, maxTicksLimit: 6 }
    }, extra || {});
  }

  function buildOptions(colors, unit, scales) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      animation: false,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        legend: {
          position: 'bottom',
          labels: { color: colors.text, boxWidth: 8, boxHeight: 8, padding: 10, usePointStyle: true, font: { size: 11 } }
        },
        tooltip: {
          callbacks: {
            label: function (ctx) {
              const u = ctx.dataset.unit || unit;
              return ' ' + ctx.dataset.label + ': ' + ctx.formattedValue + (u ? ' ' + u : '');
            }
          }
        }
      },
      scales: scales(colors)
    };
  }

  function dataset(series, i, extra) {
    return Object.assign({
      label: series.label,
      data: series.data,
      borderColor: palette[i % palette.length],
      backgroundColor: palette[i % palette.length],
      borderWidth: 2,
      tension: 0.35,
      pointRadius: 0,
      pointHoverRadius: 4
    }, extra || {});
  }

  const xAxis = function (colors) {
    return axis(colors, { grid: { display: false }, ticks: { color: colors.text, font: { size: 11 }, maxTicksLimit: 7, maxRotation: 0 } });
  };

  const configs = [
    {
      id: 'ddm-trend-dc',
      unit: 'V',
      datasets: trends.dc.map(function (s, i) { return dataset(s, i); }),
      scales: function (c) { return { x: xAxis(c), y: axis(c, { min: <?= json_encode($dcMin) ?>, max: <?= json_encode($dcMax) ?>, title: { display: true, text: 'V', color: c.text } }) }; }
    },
    {
      id: 'ddm-trend-ac',
      unit: 'V',
      datasets: trends.ac.map(function (s, i) { return dataset(s, i); }),
      scales: function (c) { return { x: xAxis(c), y: axis(c, { min: <?= json_encode($acMin) ?>, max: <?= json_encode($acMax) ?>, title: { display: true, text: 'V', color: c.text } }) }; }
    },
    {
      id: 'ddm-trend-env',
      unit: '',
      datasets: trends.env.map(function (s, i) {
        return dataset(s, i === 0 ? 2 : 0, { yAxisID: s.axis, unit: i === 0 ? '°C' : '%' });
      }),
      scales: function (c) {
        return {
          x: xAxis(c),
          y: axis(c, { position: 'left', min: 0, max: 40, title: { display: true, text: '°C', color: c.text } }),
          y1: axis(c, { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false }, title: { display: true, text: '%', color: c.text } })
        };
      }
    }
  ];

  const charts = [];
  configs.forEach(function (cfg) {
    const canvas = document.getElementById(cfg.id);
    if (!canvas) return;
    charts.push({
      cfg: cfg,
      chart: new Chart(canvas, {
        type: 'line',
        data: { labels: labels, datasets: cfg.datasets },
        options: buildOptions(themeColors(), cfg.unit, cfg.scales)
      })
    });
  });

  // Grow the DC/AC/IO row to the bottom of the screen, then 25% taller than that.
  const heightScale = 1.25;
  const row = root.querySelector('.ddm-row');
  const panels = row ? row.querySelectorAll('.ddm-panel') : [];
  function fitHeight() {
    if (!row || !row.offsetParent || window.innerWidth < 992) {
      panels.forEach(function (p) { p.style.height = ''; });
      return;
    }
    panels.forEach(function (p) { p.style.height = 'auto'; });
    const natural = row.offsetHeight;
    const below = document.documentElement.scrollHeight - (row.getBoundingClientRect().bottom + window.scrollY);
    const avail = window.innerHeight - row.getBoundingClientRect().top - window.scrollY - below;
    const target = Math.round(Math.max(natural, avail) * heightScale);
    panels.forEach(function (p) { p.style.height = target + 'px'; });
  }
  fitHeight();
  window.addEventListener('resize', fitHeight);
  const tabBtn = document.getElementById('tab-dashboard-btn');
  if (tabBtn) tabBtn.addEventListener('shown.bs.tab', fitHeight);

  // Follow the app's light/dark toggle (data-bs-theme on <html>).
  new MutationObserver(function () {
    const colors = themeColors();
    charts.forEach(function (item) {
      item.chart.options = buildOptions(colors, item.cfg.unit, item.cfg.scales);
      item.chart.update();
    });
  }).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
})();
</script>
