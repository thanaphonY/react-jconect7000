<?php
class Api_client_model extends CI_Model
{
    public function ready()
    {
        return $this->db->table_exists('api_clients');
    }

    public function listClients()
    {
        if (!$this->ready()) {
            return array();
        }
        $rows = $this->db->query("
            SELECT c.*, n.name AS node_name, n.node_type
            FROM api_clients c
            LEFT JOIN site_nodes n ON n.id = c.site_node_id
            ORDER BY c.created_at DESC, c.id DESC
        ")->result();
        $counts = $this->currentCounts();
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $row->count_hour = $counts[$id]['hour'] ?? 0;
            $row->count_day = $counts[$id]['day'] ?? 0;
            $row->count_month = $counts[$id]['month'] ?? 0;
            $row->recent_logs = $this->recentLogs($id);
        }
        return $rows;
    }

    public function nodeOptions()
    {
        if (!$this->db->table_exists('site_nodes')) {
            return array();
        }
        return $this->db->query("
            SELECT id, name, node_type
            FROM site_nodes
            ORDER BY node_type, name
        ")->result();
    }

    public function create($name, $siteNodeId, $expiresAt, $rateLimit, $userId)
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->db->insert('api_clients', array(
            'name' => $name,
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 8),
            'site_node_id' => $siteNodeId > 0 ? $siteNodeId : null,
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'rate_limit_per_minute' => $rateLimit,
            'created_by' => $userId > 0 ? $userId : null,
        ));
        return $token;
    }

    public function updateLimits($id, $expiresAt, $rateLimit)
    {
        $this->db->where('id', (int) $id)->update('api_clients', array(
            'expires_at' => $expiresAt !== '' ? $expiresAt : null,
            'rate_limit_per_minute' => $rateLimit,
        ));
    }

    public function revoke($id)
    {
        $this->db->where('id', (int) $id)->update('api_clients', array('active' => false));
    }

    private function currentCounts()
    {
        if (!$this->db->table_exists('api_request_counts')) {
            return array();
        }
        $rows = $this->db->query("
            SELECT api_client_id, period, request_count
            FROM api_request_counts
            WHERE (period = 'hour' AND period_start = (date_trunc('hour', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok'))
               OR (period = 'day' AND period_start = (date_trunc('day', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok'))
               OR (period = 'month' AND period_start = (date_trunc('month', now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok'))
        ")->result();
        $out = array();
        foreach ($rows as $row) {
            $out[(int) $row->api_client_id][$row->period] = (int) $row->request_count;
        }
        return $out;
    }

    private function recentLogs($clientId)
    {
        if (!$this->db->table_exists('api_request_logs')) {
            return array();
        }
        return $this->db->query("
            SELECT created_at, method, path, zone_id, site_id, device_id, status_code, error_code, client_ip
            FROM api_request_logs
            WHERE api_client_id = ?
            ORDER BY created_at DESC
            LIMIT 20
        ", array((int) $clientId))->result();
    }
}
