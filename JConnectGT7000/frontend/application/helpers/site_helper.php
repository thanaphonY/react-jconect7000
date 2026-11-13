<?php
defined('BASEPATH') OR exit('No direct script access allowed');

function site_current_user_id()
{
    $CI = get_instance();
    $details = $CI->session->get_userdata()['user_details'][0] ?? null;
    return $details ? (int) $details->ia_users_id : 0;
}

function site_is_regional()
{
    $CI = get_instance();
    if (isset($CI->site_access_regional)) {
        return $CI->site_access_regional;
    }
    $CI->site_access_regional = false;
    $userId = site_current_user_id();
    if ($userId < 1) {
        return false;
    }
    $column = $CI->db->query("SELECT 1 AS ok FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'ia_users' AND column_name = 'access_level'")->row();
    if (!$column) {
        return false;
    }
    $row = $CI->db->query('SELECT access_level FROM ia_users WHERE ia_users_id = ?', array($userId))->row();
    $CI->site_access_regional = $row && $row->access_level === 'regional';
    return $CI->site_access_regional;
}

function site_require_global()
{
    if (!site_is_regional()) {
        return;
    }
    $CI = get_instance();
    $CI->session->set_userdata('alert_msg', array(
        'type' => 'danger',
        'msg' => 'You do not have permission to open this page.',
    ));
    redirect(base_url() . 'home');
    exit;
}

function site_menu_allowed($menu)
{
    $regional = array('home', 'dashboard', 'site', 'alarmlogs', 'reports');
    if (site_is_regional()) {
        return in_array($menu, $regional, true);
    }
    if ($menu === 'home' || $menu === 'site' || $menu === 'api') {
        return true;
    }
    $permission = array(
        'dashboard' => 'dashboard',
        'devices' => 'devices',
        'devicetemplate' => 'devicetemplate',
        'notification' => 'notification',
        'alarmlogs' => 'alarmlogs',
        'eventlogs' => 'Eventlog',
        'reports' => 'reports',
        'users' => 'user',
        'setting' => 'setting',
        'profile' => 'profile',
    );
    if ($menu === 'profile') {
        return true;
    }
    if (!isset($permission[$menu])) {
        return false;
    }
    return CheckPermission($permission[$menu], 'own_read') || CheckPermission($permission[$menu], 'all_read');
}

function site_visible_device_ids()
{
    if (!site_is_regional()) {
        return null;
    }
    $CI = get_instance();
    if (!$CI->db->table_exists('site_nodes')) {
        return array();
    }
    $CI->load->model('Site_model');
    return $CI->Site_model->deviceIdsForCurrentUser();
}

function site_allowed_layout_ids()
{
    if (!site_is_regional()) {
        return null;
    }
    $CI = get_instance();
    if (!$CI->db->table_exists('site_nodes')) {
        return array();
    }
    $CI->load->model('Site_model');
    return $CI->Site_model->layoutIdsForCurrentUser();
}

function site_layout_allowed($layoutId)
{
    $ids = site_allowed_layout_ids();
    if ($ids === null) {
        return true;
    }
    return in_array((int) $layoutId, array_map('intval', $ids), true);
}

function site_location_label($nodeName, $location)
{
    $nodeName = trim((string) $nodeName);
    $location = trim((string) $location);
    if ($nodeName !== '' && $location !== '') {
        return $nodeName . ' — ' . $location;
    }
    return $nodeName !== '' ? $nodeName : $location;
}

function site_map_bounds_from_pins(array $pins)
{
    $bounds = null;
    foreach ($pins as $pin) {
        if (!isset($pin['latitude'], $pin['longitude'])) {
            continue;
        }
        $lat = (float) $pin['latitude'];
        $lng = (float) $pin['longitude'];
        if ($bounds === null) {
            $bounds = array($lat, $lat, $lng, $lng);
        } else {
            $bounds[0] = min($bounds[0], $lat);
            $bounds[1] = max($bounds[1], $lat);
            $bounds[2] = min($bounds[2], $lng);
            $bounds[3] = max($bounds[3], $lng);
        }
    }
    if ($bounds === null) {
        return null;
    }
    $latPad = max(0.01, ($bounds[1] - $bounds[0]) * 0.2);
    $lngPad = max(0.01, ($bounds[3] - $bounds[2]) * 0.2);
    $bounds[0] -= $latPad;
    $bounds[1] += $latPad;
    $bounds[2] -= $lngPad;
    $bounds[3] += $lngPad;
    return $bounds;
}

function site_map_bounds_from_config($config)
{
    if (!$config) {
        return null;
    }
    $cfg = is_object($config) ? $config : (object) $config;
    if (!isset($cfg->geo_bounds)) {
        return null;
    }
    $gb = $cfg->geo_bounds;
    if (is_array($gb)) {
        $gb = (object) $gb;
    }
    if (!is_object($gb) || !isset($gb->north, $gb->south, $gb->east, $gb->west)) {
        return null;
    }
    $south = (float) $gb->south;
    $north = (float) $gb->north;
    $west = (float) $gb->west;
    $east = (float) $gb->east;
    if ($north <= $south || $east <= $west) {
        return null;
    }
    return array($south, $north, $west, $east);
}

function site_map_resolve_bounds(array $pins, $config = null)
{
    $fromConfig = site_map_bounds_from_config($config);
    if ($fromConfig !== null) {
        return $fromConfig;
    }
    return site_map_bounds_from_pins($pins);
}

function site_map_project($latitude, $longitude, array $bounds)
{
    $latSpan = $bounds[1] - $bounds[0];
    $lngSpan = $bounds[3] - $bounds[2];
    if ($latSpan <= 0 || $lngSpan <= 0) {
        return array(500, 300);
    }
    $x = (($longitude - $bounds[2]) / $lngSpan) * 920 + 40;
    $y = (1 - (($latitude - $bounds[0]) / $latSpan)) * 520 + 40;
    return array(round($x, 1), round($y, 1));
}

function site_tree_flat_paths($nodes, $path = '')
{
    $out = array();
    foreach ($nodes as $item) {
        $node = $item['node'];
        $current = $path === '' ? $node->name : $path . ' › ' . $node->name;
        $out[] = array(
            'id' => (int) $node->id,
            'path' => $current,
            'type' => $node->node_type,
            'name' => $node->name,
        );
        if (!empty($item['children'])) {
            $out = array_merge($out, site_tree_flat_paths($item['children'], $current));
        }
    }
    return $out;
}

function site_select_location_options(array $tree, array $allowedTypes = array('Site', 'Building', 'Room'))
{
    $options = site_tree_flat_paths($tree);
    $filtered = array();
    foreach ($options as $option) {
        if (in_array($option['type'], $allowedTypes, true)) {
            $filtered[] = $option;
        }
    }
    usort($filtered, function ($a, $b) {
        return strcasecmp($a['path'], $b['path']);
    });
    return $filtered;
}

function site_map_reference_sites(array $tree, $excludeId = 0)
{
    $out = array();
    foreach (site_tree_collect($tree, array('Site')) as $row) {
        $node = $row['node'];
        if ($excludeId && (int) $node->id === (int) $excludeId) {
            continue;
        }
        if ($node->latitude === null || $node->longitude === null || $node->latitude === '' || $node->longitude === '') {
            continue;
        }
        $out[] = array(
            'name' => $node->name,
            'lat' => (float) $node->latitude,
            'lng' => (float) $node->longitude,
        );
    }
    return $out;
}

function site_map_form_bounds(array $referenceSites, $latitude = null, $longitude = null, $config = null)
{
    $fromConfig = site_map_bounds_from_config($config);
    if ($fromConfig !== null) {
        return $fromConfig;
    }
    $pins = array();
    foreach ($referenceSites as $site) {
        $pins[] = array('latitude' => $site['lat'], 'longitude' => $site['lng']);
    }
    if ($latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '') {
        $pins[] = array('latitude' => (float) $latitude, 'longitude' => (float) $longitude);
    }
    $bounds = site_map_bounds_from_pins($pins);
    if ($bounds !== null) {
        return $bounds;
    }
    // Default: Thailand mainland bounds (offline tiles cover this area).
    return array(5.55, 20.55, 97.25, 105.75);
}

function site_map_form_background_url($config)
{
    if (!$config) {
        return '';
    }
    $cfg = is_object($config) ? $config : json_decode($config);
    if (!is_object($cfg)) {
        return '';
    }
    $bg = trim($cfg->background ?? '');
    if ($bg === '') {
        return '';
    }
    $path = FCPATH . 'uploads/maps/' . $bg;
    if (!is_file($path)) {
        return '';
    }
    return base_url('uploads/maps/' . rawurlencode($bg));
}

function site_map_form_local_tiles_url()
{
    $root = FCPATH . 'assets/maps/tiles';
    if (!is_file($root . '/.source')) {
        return '';
    }
    $sample = glob($root . '/*/*/*.png');
    if (!$sample) {
        return '';
    }
    // Ocean tiles can be ~100 bytes; accept any PNG header.
    $fh = @fopen($sample[0], 'rb');
    $magic = $fh ? fread($fh, 4) : '';
    if ($fh) {
        fclose($fh);
    }
    if ($magic !== "\x89PNG") {
        return '';
    }
    return iaBase() . 'assets/maps/tiles/';
}

function site_map_form_view(array $referenceSites, $latitude = null, $longitude = null)
{
    if ($latitude !== null && $longitude !== null && $latitude !== '' && $longitude !== '') {
        return array(
            'lat' => (float) $latitude,
            'lng' => (float) $longitude,
            'zoom' => 14,
        );
    }
    if ($referenceSites) {
        $minLat = $maxLat = $referenceSites[0]['lat'];
        $minLng = $maxLng = $referenceSites[0]['lng'];
        foreach ($referenceSites as $site) {
            $minLat = min($minLat, $site['lat']);
            $maxLat = max($maxLat, $site['lat']);
            $minLng = min($minLng, $site['lng']);
            $maxLng = max($maxLng, $site['lng']);
        }
        return array(
            'lat' => ($minLat + $maxLat) / 2,
            'lng' => ($minLng + $maxLng) / 2,
            'zoom' => 11,
        );
    }
    return array('lat' => 13.7563, 'lng' => 100.5018, 'zoom' => 11);
}

function site_form_parent_options(array $tree, array $parentRows)
{
    $pathById = site_tree_path_by_id($tree);
    $out = array();
    foreach ($parentRows as $parent) {
        $id = (int) $parent->id;
        $out[] = array(
            'id' => $id,
            'type' => $parent->node_type,
            'name' => $parent->name,
            'path' => isset($pathById[$id]) ? $pathById[$id] : ($parent->name . ' (' . $parent->node_type . ')'),
        );
    }
    usort($out, function ($a, $b) {
        return strcasecmp($a['path'], $b['path']);
    });
    return $out;
}

function site_channel_is_active($channel)
{
    if (!$channel || !isset($channel->is_active)) {
        return false;
    }
    $value = $channel->is_active;
    return $value === true || $value === 't' || $value === '1' || $value === 1;
}

function site_device_is_active($device)
{
    if (!$device || !isset($device->is_active)) {
        return true;
    }
    $value = $device->is_active;
    return $value === true || $value === 't' || $value === '1' || $value === 1;
}

function site_device_protocol($device)
{
    $config = $device->communication_config ?? null;
    if (is_string($config) && $config !== '') {
        $decoded = json_decode($config, true);
        $config = is_array($decoded) ? $decoded : array();
    }
    if (!is_array($config)) {
        $config = array();
    }
    $protocol = trim((string) ($config['protocol'] ?? ''));
    return $protocol !== '' ? strtolower($protocol) : '—';
}

function site_device_online($device)
{
    return site_device_is_active($device);
}

function site_channel_type_icon($type)
{
    $type = strtolower(trim((string) $type));
    $map = array(
        'line' => 'bi-chat-dots',
        'email' => 'bi-envelope',
        'sms' => 'bi-phone',
        'webhook' => 'bi-link-45deg',
        'telegram' => 'bi-send',
    );
    return isset($map[$type]) ? $map[$type] : 'bi-bell';
}

function site_node_type_badge_class($type)
{
    $map = array(
        'Main' => 'text-bg-primary',
        'Region' => 'text-bg-info',
        'Site' => 'text-bg-success',
        'Building' => 'text-bg-warning',
        'Room' => 'text-bg-secondary',
    );
    return isset($map[$type]) ? $map[$type] : 'text-bg-secondary';
}

function site_node_type_icon($type)
{
    $map = array(
        'Main' => 'bi-globe2',
        'Region' => 'bi-map',
        'Site' => 'bi-geo-alt-fill',
        'Building' => 'bi-building',
        'Room' => 'bi-door-open',
    );
    return isset($map[$type]) ? $map[$type] : 'bi-diagram-3';
}

function site_tree_ancestor_path($fullPath, $nodeName)
{
    $fullPath = (string) $fullPath;
    $nodeName = (string) $nodeName;
    if ($fullPath === '' || $fullPath === $nodeName) {
        return '';
    }
    $suffix = ' › ' . $nodeName;
    if (strlen($fullPath) >= strlen($suffix) && substr($fullPath, -strlen($suffix)) === $suffix) {
        return substr($fullPath, 0, -strlen($suffix));
    }
    return $fullPath;
}

function site_tree_path_by_id(array $nodes, $path = '')
{
    $map = array();
    foreach ($nodes as $item) {
        $node = $item['node'];
        $label = $path === '' ? $node->name : $path . ' › ' . $node->name;
        $map[(int) $node->id] = $label;
        if (!empty($item['children'])) {
            $map += site_tree_path_by_id($item['children'], $label);
        }
    }
    return $map;
}

function site_tree_collect($nodes, $types = null, $path = '')
{
    $out = array();
    foreach ($nodes as $item) {
        $node = $item['node'];
        $label = $path === '' ? $node->name : $path . ' › ' . $node->name;
        if ($types === null || in_array($node->node_type, $types, true)) {
            $ancestor = site_tree_ancestor_path($label, $node->name);
            $out[] = array(
                'node' => $node,
                'path' => $label,
                'parent_path' => $ancestor,
                'search' => strtolower($label . ' ' . $ancestor . ' ' . $node->node_type . ' ' . $node->name),
            );
        }
        if (!empty($item['children'])) {
            $out = array_merge($out, site_tree_collect($item['children'], $types, $label));
        }
    }
    return $out;
}

function site_tree_count_nodes($nodes)
{
    $counts = array('total' => 0, 'Site' => 0, 'Region' => 0, 'Building' => 0, 'Room' => 0, 'Main' => 0);
    foreach (site_tree_collect($nodes, null) as $row) {
        $type = $row['node']->node_type;
        $counts['total']++;
        if (isset($counts[$type])) {
            $counts[$type]++;
        }
    }
    return $counts;
}

function site_tree_row_actions($node)
{
    $type = $node->node_type;
    $id = (int) $node->id;
    echo '<div class="dropdown site-tree-actions-dropdown">';
    echo '<button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" data-bs-popper-config="' . htmlspecialchars('{"strategy":"fixed"}', ENT_QUOTES, 'UTF-8') . '" aria-expanded="false" onclick="event.stopPropagation();">Actions</button>';
    echo '<ul class="dropdown-menu dropdown-menu-end">';
    echo '<li><a class="dropdown-item" href="' . base_url('site/form/' . $id) . '"><i class="bi bi-pencil me-1"></i> Edit</a></li>';
    if ($type !== 'Room') {
        echo '<li><a class="dropdown-item" href="' . base_url('site/form?parent=' . $id) . '"><i class="bi bi-plus-lg me-1"></i> Add child</a></li>';
    }
    if ($type === 'Site') {
        echo '<li><a class="dropdown-item" href="' . base_url('site/view/' . $id) . '"><i class="bi bi-box-arrow-up-right me-1"></i> Open site</a></li>';
    }
    echo '<li><hr class="dropdown-divider"></li>';
    echo '<li><form method="post" action="' . base_url('site/delete/' . $id) . '" onsubmit="return confirm(\'Delete this node?\');">';
    echo '<button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-1"></i> Delete</button>';
    echo '</form></li>';
    echo '</ul></div>';
}

function site_alarm_badge_class($severity)
{
    $severity = strtolower(trim((string) $severity));
    if ($severity === 'critical') {
        return 'text-bg-danger';
    }
    if ($severity === 'warning') {
        return 'text-bg-warning text-dark';
    }
    if ($severity === 'info') {
        return 'text-bg-info';
    }
    return 'text-bg-secondary';
}

function site_tree_row_main($node, $parentPath = '', $alarmInfo = null, $deviceCount = 0)
{
    $type = $node->node_type;
    $count = is_array($alarmInfo) ? (int) ($alarmInfo['count'] ?? 0) : 0;
    $severity = is_array($alarmInfo) ? ($alarmInfo['severity'] ?? '') : '';
    $deviceCount = (int) $deviceCount;
    echo '<div class="site-tree-main">';
    echo '<div class="d-flex flex-wrap align-items-center gap-2">';
    echo '<i class="site-tree-icon bi ' . site_node_type_icon($type) . '" aria-hidden="true"></i>';
    echo '<span class="site-tree-name">' . html_escape($node->name) . '</span>';
    echo '<span class="badge ' . site_node_type_badge_class($type) . '">' . html_escape($type) . '</span>';
    if ($deviceCount > 0) {
        echo '<span class="badge text-bg-secondary site-tree-device-badge" title="Devices in this branch">';
        echo '<i class="bi bi-hdd-network me-1" aria-hidden="true"></i>';
        echo (int) $deviceCount . ' device' . ($deviceCount === 1 ? '' : 's');
        echo '</span>';
    }
    if ($count > 0) {
        echo '<span class="badge ' . site_alarm_badge_class($severity) . ' site-tree-alarm-badge" title="Active alarms in this branch">';
        echo '<i class="bi bi-bell me-1" aria-hidden="true"></i>';
        echo (int) $count . ' active alarm' . ($count === 1 ? '' : 's');
        echo '</span>';
    }
    echo '</div>';
    echo '</div>';
}

function site_render_admin_tree($nodes, $depth = 0, $parentPath = '', $alarms = array(), $devices = array())
{
    foreach ($nodes as $item) {
        $node = $item['node'];
        $type = $node->node_type;
        $hasChildren = !empty($item['children']);
        $pathLabel = $parentPath === '' ? $node->name : $parentPath . ' › ' . $node->name;
        $search = strtolower(str_replace(' › ', ' ', $pathLabel) . ' ' . $type);
        $alarmInfo = isset($alarms[(int) $node->id]) ? $alarms[(int) $node->id] : array('count' => 0, 'severity' => '');
        $deviceCount = isset($devices[(int) $node->id]) ? (int) $devices[(int) $node->id] : 0;
        echo '<li class="site-tree-item" data-node-id="' . (int) $node->id . '" data-node-type="' . html_escape($type) . '" data-search="' . html_escape($search) . '">';
        if ($hasChildren) {
            $open = $depth < 1 ? ' open' : '';
            echo '<details class="site-tree-branch"' . $open . '>';
            echo '<summary class="site-tree-row site-tree-summary">';
            site_tree_row_main($node, $parentPath, $alarmInfo, $deviceCount);
            site_tree_row_actions($node);
            echo '</summary>';
            echo '<ul class="site-tree-children">';
            site_render_admin_tree($item['children'], $depth + 1, $pathLabel, $alarms, $devices);
            echo '</ul>';
            echo '</details>';
        } else {
            echo '<div class="site-tree-row">';
            site_tree_row_main($node, $parentPath, $alarmInfo, $deviceCount);
            site_tree_row_actions($node);
            echo '</div>';
        }
        echo '</li>';
    }
}

/**
 * Compact read-only hierarchy for Home View Site panel (no action menus / alarm badges).
 */
function site_render_home_tree($nodes, $depth = 0, $parentPath = '')
{
    if ($depth === 0) {
        echo '<ul class="site-home-tree-root">';
    }
    foreach ($nodes as $item) {
        $node = $item['node'];
        $type = $node->node_type;
        $children = !empty($item['children']) ? $item['children'] : array();
        $hasChildren = !empty($children);
        $childCount = count($children);
        $pathLabel = $parentPath === '' ? $node->name : $parentPath . ' › ' . $node->name;
        $search = strtolower(str_replace(' › ', ' ', $pathLabel) . ' ' . $type);
        $isSite = ($type === 'Site');
        $href = $isSite ? base_url('site/view/' . (int) $node->id) : '';

        echo '<li class="site-home-tree-item" data-node-id="' . (int) $node->id . '" data-node-type="' . html_escape($type) . '" data-search="' . html_escape($search) . '">';
        if ($hasChildren) {
            $open = $depth < 1 ? ' open' : '';
            echo '<details class="site-home-tree-branch"' . $open . '>';
            echo '<summary class="site-home-tree-row">';
            echo '<i class="site-home-tree-icon bi ' . site_node_type_icon($type) . '" aria-hidden="true"></i>';
            if ($isSite) {
                echo '<a class="site-home-tree-name site-home-tree-name-link" href="' . $href . '" onclick="event.stopPropagation();">' . html_escape($node->name) . '</a>';
            } else {
                echo '<span class="site-home-tree-name">' . html_escape($node->name) . '</span>';
            }
            echo '<span class="site-home-tree-meta">' . html_escape($type);
            if ($childCount > 0) {
                echo ' · ' . (int) $childCount;
            }
            echo '</span>';
            echo '</summary>';
            echo '<ul class="site-home-tree-children">';
            site_render_home_tree($children, $depth + 1, $pathLabel);
            echo '</ul>';
            echo '</details>';
        } elseif ($isSite) {
            echo '<a class="site-home-tree-row site-home-tree-link" href="' . $href . '">';
            echo '<i class="site-home-tree-icon bi ' . site_node_type_icon($type) . '" aria-hidden="true"></i>';
            echo '<span class="site-home-tree-name">' . html_escape($node->name) . '</span>';
            echo '<span class="site-home-tree-meta">Site</span>';
            echo '</a>';
        } else {
            echo '<div class="site-home-tree-row">';
            echo '<i class="site-home-tree-icon bi ' . site_node_type_icon($type) . '" aria-hidden="true"></i>';
            echo '<span class="site-home-tree-name">' . html_escape($node->name) . '</span>';
            echo '<span class="site-home-tree-meta">' . html_escape($type) . '</span>';
            echo '</div>';
        }
        echo '</li>';
    }
    if ($depth === 0) {
        echo '</ul>';
    }
}
