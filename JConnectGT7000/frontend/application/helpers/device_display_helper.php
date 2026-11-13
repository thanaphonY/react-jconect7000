<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Display values shared by the device Dashboard and Layout tabs.
 *
 * PEMON readings are not all scaled yet (e.g. temp 2517, AC 2418.5 V). For the metrics below a live
 * value is shown only when it falls in a plausible range; otherwise a fixed simulated value is used,
 * so both tabs (and the Layout live refresh) always show the same numbers.
 */

/** key => [min, max, simulated value, fallback key] */
function device_display_rules()
{
    static $rules = null;
    if ($rules !== null) {
        return $rules;
    }

    $rules = array();
    // VB1 meter on the unit is ~38–60 V.
    $dcVoltage = array(48.2, 47.9, 48.5, 47.6);
    $dcCurrent = array(18.6, 17.9, 18.2, 17.5);
    foreach ($dcVoltage as $i => $sample) {
        $n = $i + 1;
        $rules["dc.string{$n}.voltage_v"] = array(20, 80, $sample, "dc.string{$n}.vstr_v");
        $rules["dc.string{$n}.current_a"] = array(-300, 300, $dcCurrent[$i], null);
    }
    // VAC meter on the unit is ~170–270 V.
    $acVoltage = array(224, 227, 222, 229, 221, 226);
    $acCurrent = array(12.4, 11.8, 13.1, 12.0, 11.5, 12.7);
    foreach ($acVoltage as $i => $sample) {
        $n = $i + 1;
        $rules["ac.sys1.line{$n}.voltage_v"] = array(100, 300, $sample, null);
        $rules["ac.sys1.line{$n}.current_a"] = array(0, 200, $acCurrent[$i], null);
        $rules["ac.sys1.line{$n}.frequency_hz"] = array(45, 65, 50.0, null);
    }
    $rules['io.sys1.temp1_c'] = array(-20, 80, 27.4, null);
    $rules['io.sys1.hum1_pct'] = array(0, 100, 48, null);

    return $rules;
}

/** Raw numeric value from get_latest_by_device() rows, or null. */
function device_display_raw(array $latest, $key)
{
    if (!isset($latest[$key]) || !is_array($latest[$key])) {
        return null;
    }
    $value = $latest[$key]['value'] ?? null;
    return is_numeric($value) ? (float) $value : null;
}

/** ['value' => float, 'live' => bool] for a ruled key, or null when the key has no rule. */
function device_display_value(array $latest, $key)
{
    $rules = device_display_rules();
    if (!isset($rules[$key])) {
        return null;
    }
    list($min, $max, $sample, $fallbackKey) = $rules[$key];

    $raw = device_display_raw($latest, $key);
    if ($raw === null && $fallbackKey) {
        $raw = device_display_raw($latest, $fallbackKey);
    }
    if ($raw !== null && $raw >= $min && $raw <= $max) {
        return array('value' => $raw, 'live' => true);
    }
    return array('value' => (float) $sample, 'live' => false);
}

/** key => value for every latest metric, with ruled keys replaced by their display value. */
function device_display_values(array $latest)
{
    $values = array();
    foreach ($latest as $key => $row) {
        if (!is_array($row)) {
            continue;
        }
        if ($row['value'] !== null) {
            $values[$key] = is_numeric($row['value']) ? (float) $row['value'] : $row['value'];
        } elseif ($row['value_text'] !== null) {
            $values[$key] = $row['value_text'];
        } elseif ($row['value_bool'] !== null) {
            $values[$key] = $row['value_bool'] === true || $row['value_bool'] === 't' ? 1 : 0;
        }
    }
    foreach (array_keys(device_display_rules()) as $key) {
        $values[$key] = device_display_value($latest, $key)['value'];
    }
    return $values;
}
