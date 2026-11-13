<?php
class LineDriver {
    public function send($config, $message) {
        $access_token = $config['access_token'] ?? null;
        $user_id = $config['user_id'] ?? null;

        if (!$access_token || !$user_id) {
            return ['success' => false, 'error' => 'Missing access_token or user_id'];
        }

        $headers = [
            "Authorization: Bearer {$access_token}",
            "Content-Type: application/json"
        ];

        $body = json_encode([
            'to' => $user_id,
            'messages' => [[
                'type' => 'text',
                'text' => $message
            ]]
        ]);

        $ch = curl_init("https://api.line.me/v2/bot/message/push");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return $error ? ['success' => false, 'error' => $error] : ['success' => true, 'response' => $response];
    }
}
?>
