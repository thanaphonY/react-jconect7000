<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Site_model extends CI_Model
{
    public function ranks()
    {
        return array('Main' => 0, 'Region' => 1, 'Site' => 2, 'Building' => 3, 'Room' => 4);
    }

    public function ready()
    {
        return $this->db->table_exists('site_nodes');
    }

    public function allRows()
    {
        if (!$this->ready()) {
            return array();
        }
        return $this->db->order_by('sort_order', 'ASC')->order_by('name', 'ASC')->get('site_nodes')->result();
    }

    public function node($id)
    {
        return $this->db->get_where('site_nodes', array('id' => (int) $id))->row();
    }

    public function byParent()
    {
        $grouped = array();
        foreach ($this->allRows() as $row) {
            $parent = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $grouped[$parent][] = $row;
        }
        return $grouped;
    }

    public function descendantIds($id, $rows = null)
    {
        if ($rows === null) {
            $rows = $this->allRows();
        }
        $children = array();
        foreach ($rows as $row) {
            $parent = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $children[$parent][] = (int) $row->id;
        }
        $seen = array();
        $stack = array((int) $id);
        while ($stack) {
            $current = array_pop($stack);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            foreach ($children[$current] ?? array() as $child) {
                $stack[] = $child;
            }
        }
        return array_map('intval', array_keys($seen));
    }

    public function assignedIds($userId)
    {
        if (!$this->db->table_exists('user_site_scopes')) {
            return array();
        }
        $rows = $this->db->select('site_node_id')->get_where('user_site_scopes', array('ia_users_id' => (int) $userId))->result();
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row->site_node_id;
        }
        return $ids;
    }

    public function visibleIds($userId = null)
    {
        $rows = $this->db->query('SELECT id, parent_id FROM site_nodes')->result();
        if (!site_is_regional()) {
            $ids = array();
            foreach ($rows as $row) {
                $ids[] = (int) $row->id;
            }
            return $ids;
        }
        if ($userId === null) {
            $userId = site_current_user_id();
        }
        $assigned = $this->db->query('SELECT site_node_id FROM user_site_scopes WHERE ia_users_id = ?', array((int) $userId))->result();
        $visible = array();
        foreach ($assigned as $row) {
            foreach ($this->descendantIds((int) $row->site_node_id, $rows) as $id) {
                $visible[$id] = true;
            }
        }
        return array_map('intval', array_keys($visible));
    }

    public function visibleRows()
    {
        $allowed = array_flip($this->visibleIds());
        $rows = array();
        foreach ($this->allRows() as $row) {
            if (isset($allowed[(int) $row->id])) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    public function canSee($id)
    {
        return in_array((int) $id, $this->visibleIds(), true);
    }

    public function tree()
    {
        $rows = $this->visibleRows();
        $byParent = array();
        foreach ($rows as $row) {
            $parent = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $byParent[$parent][] = $row;
        }
        $allowed = array();
        foreach ($rows as $row) {
            $allowed[(int) $row->id] = true;
        }
        $walk = function ($parentId) use (&$walk, $byParent, $allowed) {
            $nodes = array();
            foreach ($byParent[$parentId] ?? array() as $row) {
                $nodes[] = array('node' => $row, 'children' => $walk((int) $row->id));
            }
            return $nodes;
        };
        $roots = $walk(0);
        foreach ($byParent as $parentId => $ignored) {
            if ($parentId !== 0 && !isset($allowed[$parentId])) {
                foreach ($walk($parentId) as $node) {
                    $roots[] = $node;
                }
            }
        }
        return $roots;
    }

    public function validatePlacement($type, $parentId, $selfId = null)
    {
        $ranks = $this->ranks();
        if (!isset($ranks[$type])) {
            throw new RuntimeException('Node type must be Main, Region, Site, Building, or Room.');
        }
        $parentId = $parentId ? (int) $parentId : 0;
        $selfId = $selfId ? (int) $selfId : 0;
        if ($type === 'Main') {
            if ($parentId) {
                throw new RuntimeException('Main is a root and cannot have a parent.');
            }
        } elseif (!$parentId) {
            throw new RuntimeException('Only Main can be a root.');
        }
        if ($parentId) {
            $parent = $this->node($parentId);
            if (!$parent) {
                throw new RuntimeException('Parent node was not found.');
            }
            if (!isset($ranks[$parent->node_type]) || $ranks[$parent->node_type] >= $ranks[$type]) {
                throw new RuntimeException('That parent is not allowed. Order is Main, Region, Site, Building, Room. Levels can be skipped, but not reversed.');
            }
            if ($selfId && ($parentId === $selfId || in_array($parentId, $this->descendantIds($selfId), true))) {
                throw new RuntimeException('A node cannot be moved under itself.');
            }
        }
        if ($selfId) {
            $current = $this->node($selfId);
            if (!$current) {
                throw new RuntimeException('Node was not found.');
            }
            if ($current->node_type === 'Main' && $type !== 'Main') {
                $mains = $this->db->where('node_type', 'Main')->count_all_results('site_nodes');
                if ($mains < 2) {
                    throw new RuntimeException('The system must keep at least one Main.');
                }
            }
            foreach ($this->descendantIds($selfId) as $id) {
                if ($id === $selfId) {
                    continue;
                }
                $child = $this->node($id);
                if ($child && isset($ranks[$child->node_type]) && $ranks[$child->node_type] <= $ranks[$type]) {
                    throw new RuntimeException('Existing children would reverse the node order.');
                }
            }
        }
    }

    public function saveNode($input, $id = null)
    {
        $type = trim((string) ($input['node_type'] ?? ''));
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Name is required.');
        }
        $parentId = (int) ($input['parent_id'] ?? 0);
        $selfId = $id ? (int) $id : 0;
        if ($selfId && !$this->canSee($selfId)) {
            throw new RuntimeException('You cannot edit this node.');
        }
        if (site_is_regional()) {
            if ($type === 'Main') {
                throw new RuntimeException('A regional user cannot create a Main.');
            }
            if (!$this->canSee($parentId)) {
                throw new RuntimeException('You can only add nodes inside your branch.');
            }
        }
        $this->validatePlacement($type, $parentId, $selfId ?: null);

        $latitude = null;
        $longitude = null;
        if ($type === 'Site') {
            $lat = trim((string) ($input['latitude'] ?? ''));
            $lng = trim((string) ($input['longitude'] ?? ''));
            if ($lat !== '' || $lng !== '') {
                if (!is_numeric($lat) || !is_numeric($lng)) {
                    throw new RuntimeException('Site coordinates must be numbers.');
                }
                $latitude = (float) $lat;
                $longitude = (float) $lng;
            }
        }
        $layoutId = (int) ($input['dashboard_layout_id'] ?? 0);

        $row = array(
            'parent_id' => $parentId ?: null,
            'name' => $name,
            'node_type' => $type,
            'sort_order' => (int) ($input['sort_order'] ?? 0),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'dashboard_layout_id' => $layoutId ?: null,
            'updated_at' => date('c'),
        );

        $this->db->trans_begin();
        try {
            if ($selfId) {
                $this->db->where('id', $selfId)->update('site_nodes', $row);
            } else {
                $row['created_at'] = date('c');
                $this->db->insert('site_nodes', $row);
                $selfId = (int) $this->db->insert_id();
            }
            if (!site_is_regional()) {
                $this->setChannels($selfId, $input['channel_ids'] ?? array());
            }
            $this->setDevices($selfId, $input['device_ids'] ?? array());
            if ($this->db->trans_status() === false) {
                throw new RuntimeException('Could not save the node.');
            }
            $this->db->trans_commit();
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        }
        return $selfId;
    }

    public function deleteNode($id)
    {
        $id = (int) $id;
        $node = $this->node($id);
        if (!$node || !$this->canSee($id)) {
            throw new RuntimeException('Node was not found.');
        }
        $children = $this->db->where('parent_id', $id)->count_all_results('site_nodes');
        if ($children > 0) {
            throw new RuntimeException('Remove child nodes before deleting this one.');
        }
        if ($node->node_type === 'Main') {
            $mains = $this->db->where('node_type', 'Main')->count_all_results('site_nodes');
            if ($mains < 2) {
                throw new RuntimeException('The system must keep at least one Main.');
            }
        }
        $this->db->where('id', $id)->delete('site_nodes');
    }

    public function setDevices($nodeId, $deviceIds)
    {
        $nodeId = (int) $nodeId;
        $wanted = array();
        foreach ((array) $deviceIds as $deviceId) {
            $deviceId = (int) $deviceId;
            if ($deviceId > 0) {
                $wanted[$deviceId] = true;
            }
        }
        $bindable = array();
        foreach ($this->bindableDevices($nodeId) as $device) {
            $bindable[(int) $device->id] = true;
        }
        foreach (array_keys($wanted) as $deviceId) {
            if (!isset($bindable[$deviceId])) {
                throw new RuntimeException('That device cannot be attached to this node.');
            }
        }
        $current = $this->db->select('id')->get_where('devices', array('site_node_id' => $nodeId, 'is_deleted' => false))->result();
        foreach ($current as $device) {
            if (!isset($wanted[(int) $device->id])) {
                $this->db->where('id', (int) $device->id)->update('devices', array('site_node_id' => null));
            }
        }
        foreach (array_keys($wanted) as $deviceId) {
            $this->db->where('id', $deviceId)->update('devices', array('site_node_id' => $nodeId));
        }
    }

    public function bindableDevices($nodeId)
    {
        $this->db->select('id, name, location, site_node_id, is_active');
        $this->db->from('devices');
        $this->db->where('is_deleted', false);
        if (site_is_regional()) {
            $ids = $this->visibleIds();
            $this->db->group_start();
            $this->db->where('site_node_id IS NULL', null, false);
            if ($ids) {
                $this->db->or_where_in('site_node_id', $ids);
            }
            $this->db->group_end();
        }
        $this->db->order_by('name', 'ASC');
        $rows = $this->db->get()->result();
        return array_values(array_filter($rows, 'site_device_is_active'));
    }

    public function devicesOn($nodeId)
    {
        return $this->db->select('id, name, location, site_node_id, is_active, communication_config, updated_at')
            ->where('site_node_id', (int) $nodeId)
            ->where('is_deleted', false)
            ->order_by('name', 'ASC')
            ->get('devices')
            ->result();
    }

    /** Devices bound to this node or any descendant (Site → Building → Room). */
    public function devicesUnder($nodeId)
    {
        $ids = $this->descendantIds((int) $nodeId);
        if (!$ids || !$this->db->field_exists('site_node_id', 'devices')) {
            return array();
        }
        return $this->db->select('d.id, d.name, d.location, d.site_node_id, d.is_active, d.communication_config, d.updated_at, d.image, t.image AS template_image')
            ->from('devices d')
            ->join('device_templates t', 't.id = d.device_template_id', 'left')
            ->where_in('d.site_node_id', $ids)
            ->where('d.is_deleted', false)
            ->order_by('d.name', 'ASC')
            ->get()
            ->result();
    }

    /** Active alarm counts keyed by device id. */
    public function activeAlarmCountByDevice(array $deviceIds)
    {
        $deviceIds = array_values(array_filter(array_map('intval', $deviceIds)));
        $out = array();
        if (!$deviceIds || !$this->db->table_exists('alarm_state')) {
            return $out;
        }
        $rows = $this->db->query(
            'SELECT device_id, COUNT(*) AS n FROM alarm_state WHERE active = true AND device_id IN ('
            . implode(',', $deviceIds)
            . ') GROUP BY device_id'
        )->result();
        foreach ($rows as $row) {
            $out[(int) $row->device_id] = (int) $row->n;
        }
        return $out;
    }

    public function setChannels($nodeId, $channelIds)
    {
        if (!$this->db->table_exists('notification_channel_nodes')) {
            return;
        }
        $nodeId = (int) $nodeId;
        $this->db->where('site_node_id', $nodeId)->delete('notification_channel_nodes');
        foreach ((array) $channelIds as $channelId) {
            $channelId = (int) $channelId;
            if ($channelId > 0) {
                $this->db->insert('notification_channel_nodes', array(
                    'channel_id' => $channelId,
                    'site_node_id' => $nodeId,
                ));
            }
        }
    }

    public function channelIds($nodeId)
    {
        if (!$this->db->table_exists('notification_channel_nodes')) {
            return array();
        }
        $rows = $this->db->select('channel_id')->get_where('notification_channel_nodes', array('site_node_id' => (int) $nodeId))->result();
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row->channel_id;
        }
        return $ids;
    }

    public function channels()
    {
        if (!$this->db->table_exists('notification_channels')) {
            return array();
        }
        return $this->db->where('is_deleted', false)->order_by('name', 'ASC')->get('notification_channels')->result();
    }

    public function layouts()
    {
        if (!$this->db->table_exists('dashboard_layouts')) {
            return array();
        }
        return $this->db->select('id, name')->order_by('name', 'ASC')->get('dashboard_layouts')->result();
    }

    public function deviceIdsForCurrentUser()
    {
        $nodeIds = $this->visibleIds();
        if (!$nodeIds) {
            return array();
        }
        $rows = $this->db->query('SELECT id FROM devices WHERE is_deleted = false AND site_node_id IN (' . implode(',', array_map('intval', $nodeIds)) . ')')->result();
        $ids = array();
        foreach ($rows as $row) {
            $ids[] = (int) $row->id;
        }
        return $ids;
    }

    public function layoutIdsForCurrentUser()
    {
        $ids = array();
        foreach ($this->visibleRows() as $row) {
            if (!empty($row->dashboard_layout_id)) {
                $ids[(int) $row->dashboard_layout_id] = true;
            }
        }
        return array_map('intval', array_keys($ids));
    }

    public function alarmByNode()
    {
        $ranks = array('critical' => 3, 'warning' => 2, 'info' => 1);
        $summary = array();
        if (!$this->db->table_exists('alarm_state') || !$this->db->field_exists('site_node_id', 'devices')) {
            return $summary;
        }
        $rows = $this->db->query("
            SELECT d.site_node_id, lower(a.severity) AS severity, COUNT(*) AS n
            FROM alarm_state a
            JOIN devices d ON d.id = a.device_id
            WHERE a.active = true AND d.site_node_id IS NOT NULL AND d.is_deleted = false
            GROUP BY d.site_node_id, lower(a.severity)
        ")->result();
        $direct = array();
        foreach ($rows as $row) {
            $nodeId = (int) $row->site_node_id;
            $severity = $this->normalizeSeverity($row->severity);
            if (!isset($direct[$nodeId])) {
                $direct[$nodeId] = array('count' => 0, 'rank' => 0, 'severity' => '');
            }
            $direct[$nodeId]['count'] += (int) $row->n;
            $rank = $ranks[$severity] ?? 0;
            if ($rank > $direct[$nodeId]['rank']) {
                $direct[$nodeId]['rank'] = $rank;
                $direct[$nodeId]['severity'] = $severity;
            }
        }
        $byId = array();
        foreach ($this->allRows() as $node) {
            $byId[(int) $node->id] = $node;
        }
        foreach ($direct as $nodeId => $info) {
            $current = $nodeId;
            $guard = 0;
            while ($current && isset($byId[$current]) && $guard < 30) {
                if (!isset($summary[$current])) {
                    $summary[$current] = array('count' => 0, 'rank' => 0, 'severity' => '');
                }
                $summary[$current]['count'] += $info['count'];
                if ($info['rank'] > $summary[$current]['rank']) {
                    $summary[$current]['rank'] = $info['rank'];
                    $summary[$current]['severity'] = $info['severity'];
                }
                $current = $byId[$current]->parent_id ? (int) $byId[$current]->parent_id : 0;
                $guard++;
            }
        }
        return $summary;
    }

    public function deviceCountByNode()
    {
        $summary = array();
        if (!$this->db->field_exists('site_node_id', 'devices')) {
            return $summary;
        }
        $rows = $this->db->query('
            SELECT site_node_id, COUNT(*) AS n
            FROM devices
            WHERE is_deleted = false AND site_node_id IS NOT NULL
            GROUP BY site_node_id
        ')->result();
        $direct = array();
        foreach ($rows as $row) {
            $direct[(int) $row->site_node_id] = (int) $row->n;
        }
        $byId = array();
        foreach ($this->allRows() as $node) {
            $byId[(int) $node->id] = $node;
        }
        foreach ($direct as $nodeId => $n) {
            $current = $nodeId;
            $guard = 0;
            while ($current && isset($byId[$current]) && $guard < 30) {
                if (!isset($summary[$current])) {
                    $summary[$current] = 0;
                }
                $summary[$current] += $n;
                $current = $byId[$current]->parent_id ? (int) $byId[$current]->parent_id : 0;
                $guard++;
            }
        }
        return $summary;
    }

    public function normalizeSeverity($severity)
    {
        $severity = strtolower(trim((string) $severity));
        if (in_array($severity, array('critical', 'crit'), true)) {
            return 'critical';
        }
        if (in_array($severity, array('warning', 'warn'), true)) {
            return 'warning';
        }
        if (in_array($severity, array('info', 'information'), true)) {
            return 'info';
        }
        return $severity === '' ? '' : 'info';
    }

    public function pinColor($severity)
    {
        $colors = array(
            'critical' => '#dc3545',
            'warning' => '#ffc107',
            'info' => '#0d6efd',
            '' => '#198754',
        );
        return $colors[$severity] ?? $colors[''];
    }

    public function usersForScope()
    {
        $this->db->select('ia_users_id, name, email, user_type, access_level');
        $this->db->from('ia_users');
        $this->db->where('is_deleted', '0');
        $this->db->order_by('name', 'ASC');
        return $this->db->get()->result();
    }

    public function saveUserScope($userId, $level, $nodeIds)
    {
        $userId = (int) $userId;
        $user = $this->db->get_where('ia_users', array('ia_users_id' => $userId))->row();
        if (!$user) {
            throw new RuntimeException('User was not found.');
        }
        $level = $level === 'regional' ? 'regional' : 'global';
        $clean = array();
        $known = array();
        foreach ($this->allRows() as $row) {
            $known[(int) $row->id] = true;
        }
        foreach ((array) $nodeIds as $nodeId) {
            $nodeId = (int) $nodeId;
            if ($nodeId > 0 && isset($known[$nodeId])) {
                $clean[$nodeId] = true;
            }
        }
        $this->db->trans_begin();
        $this->db->where('ia_users_id', $userId)->update('ia_users', array('access_level' => $level));
        $this->db->where('ia_users_id', $userId)->delete('user_site_scopes');
        if ($level === 'regional') {
            foreach (array_keys($clean) as $nodeId) {
                $this->db->insert('user_site_scopes', array(
                    'ia_users_id' => $userId,
                    'site_node_id' => $nodeId,
                ));
            }
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            throw new RuntimeException('Could not save the user scope.');
        }
        $this->db->trans_commit();
    }

    public function homeSummary(array $alarms)
    {
        $onlineSeconds = 15 * 60;
        $cutoff = time() - $onlineSeconds;
        $lastSeen = $this->lastSeenUnixByNode();
        $rows = $this->visibleRows();
        $children = array();
        foreach ($rows as $row) {
            $parent = $row->parent_id === null ? 0 : (int) $row->parent_id;
            $children[$parent][] = (int) $row->id;
        }
        $sites = array();
        $online = 0;
        $offline = 0;
        $alarmSites = 0;
        foreach (site_tree_collect($this->tree(), array('Site')) as $item) {
            $node = $item['node'];
            $id = (int) $node->id;
            $info = isset($alarms[$id]) ? $alarms[$id] : array('count' => 0, 'severity' => '');
            $alarmCount = (int) $info['count'];
            $seen = isset($lastSeen[$id]) ? $lastSeen[$id] : 0;
            $stack = $children[$id] ?? array();
            $guard = 0;
            while ($stack && $guard < 500) {
                $current = array_pop($stack);
                $guard++;
                if (isset($lastSeen[$current]) && $lastSeen[$current] > $seen) {
                    $seen = $lastSeen[$current];
                }
                foreach ($children[$current] ?? array() as $child) {
                    $stack[] = $child;
                }
            }
            $isOnline = $seen >= $cutoff;
            if ($isOnline) {
                $online++;
            } else {
                $offline++;
            }
            if ($alarmCount > 0) {
                $alarmSites++;
            }
            $sites[] = array(
                'id' => $id,
                'name' => $node->name,
                'path' => $item['parent_path'],
                'online' => $isOnline,
                'alarms' => $alarmCount,
                'severity' => $info['severity'],
            );
        }
        return array(
            'total' => count($sites),
            'online' => $online,
            'offline' => $offline,
            'alarm' => $alarmSites,
            'online_minutes' => (int) ($onlineSeconds / 60),
            'sites' => $sites,
        );
    }

    private function lastSeenUnixByNode()
    {
        $seen = array();
        if (!$this->db->table_exists('device_data') || !$this->db->field_exists('site_node_id', 'devices')) {
            return $seen;
        }
        $rows = $this->db->query('
            SELECT d.site_node_id, EXTRACT(EPOCH FROM MAX(dd.timestamp)) AS seen
            FROM devices d
            JOIN device_data dd ON dd.device_id = d.id
            WHERE d.is_deleted = false AND d.site_node_id IS NOT NULL
            GROUP BY d.site_node_id
        ')->result();
        foreach ($rows as $row) {
            $seen[(int) $row->site_node_id] = (int) $row->seen;
        }
        return $seen;
    }

    public function homeMapWidget()
    {
        $id = (int) getSetting('home_site_map_widget_id');
        if ($id > 0) {
            $row = $this->db->get_where('widgets', array('id' => $id))->row();
            if ($row && $row->widget_type === 'map_view') {
                return $row;
            }
        }
        return $this->db->where('widget_type', 'map_view')->order_by('id', 'ASC')->limit(1)->get('widgets')->row();
    }
}
