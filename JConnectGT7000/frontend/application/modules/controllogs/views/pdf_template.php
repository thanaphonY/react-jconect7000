<?php
defined('BASEPATH') or exit('No direct script access allowed');
$title = 'รายงานบันทึกการควบคุม';
$period_label = $period_label ?? '';
$logs = $logs ?? [];
$col_seq = '5%';
$col_time = '13%';
$col_command = '14%';
$col_device = '13%';
$col_group = '12%';
$col_source = '12%';
$col_value = '8%';
$col_result = '10%';
$col_user = '13%';

$formatValue = function ($value) {
    $v = strtolower(trim((string) $value));
    if (in_array($v, ['1', 'on', 'true'], true)) {
        return 'ON';
    }
    if (in_array($v, ['0', 'off', 'false'], true)) {
        return 'OFF';
    }
    return $value === null || $value === '' ? '-' : (string) $value;
};
$formatSource = function ($log) {
    $source = (string) ($log->source ?? '-');
    $extra = [];
    if (!empty($log->sequence_name)) {
        $extra[] = $log->sequence_name;
    }
    if (!empty($log->rule_name)) {
        $extra[] = $log->rule_name;
    }
    return $extra ? $source . ' (' . implode(' / ', $extra) . ')' : $source;
};
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body {
            font-family: "thsarabunnew", "Sarabun", "freeserif", sans-serif;
            margin: 0;
            font-size: 16pt;
            line-height: 1;
        }
        .header-title {
            color: #2c3e50;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            border-bottom: 2px solid #2682de;
            padding-bottom: 6px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 12pt;
            line-height: 1.2;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #b0b0b0;
            padding: 5px;
        }
        .data-table th {
            background-color: #2f72b6;
            color: #ffffff;
            font-weight: bold;
            text-align: center;
        }
        .footer-note {
            margin-top: 12px;
            padding: 8px;
            background-color: #e8f4f8;
            border-left: 4px solid #3498db;
            font-size: 14pt;
        }
    </style>
</head>
<body>
    <div class="header-title">
        <?= htmlspecialchars($title) ?>
        <?php if ($period_label !== ''): ?>
            <div style="text-align: center; margin-top: 4px; font-size: 16pt; color: #2c3e50; font-weight: bold;">
                <?= htmlspecialchars($period_label) ?>
            </div>
        <?php endif; ?>
    </div>

    <br />

    <?php if (!empty($logs)): ?>
    <table class="data-table" cellpadding="5" cellspacing="0" border="1">
        <thead>
            <tr>
                <th width="<?= $col_seq ?>" bgcolor="#2F72B6" style="width: <?= $col_seq ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>ลำดับ</b></th>
                <th width="<?= $col_time ?>" bgcolor="#2F72B6" style="width: <?= $col_time ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>วันที่ เวลา</b></th>
                <th width="<?= $col_command ?>" bgcolor="#2F72B6" style="width: <?= $col_command ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>คำสั่ง</b></th>
                <th width="<?= $col_device ?>" bgcolor="#2F72B6" style="width: <?= $col_device ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>อุปกรณ์</b></th>
                <th width="<?= $col_group ?>" bgcolor="#2F72B6" style="width: <?= $col_group ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>กลุ่ม</b></th>
                <th width="<?= $col_source ?>" bgcolor="#2F72B6" style="width: <?= $col_source ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>แหล่งที่มา</b></th>
                <th width="<?= $col_value ?>" bgcolor="#2F72B6" style="width: <?= $col_value ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>ค่า</b></th>
                <th width="<?= $col_result ?>" bgcolor="#2F72B6" style="width: <?= $col_result ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>ผลลัพธ์</b></th>
                <th width="<?= $col_user ?>" bgcolor="#2F72B6" style="width: <?= $col_user ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>ผู้ใช้</b></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $index => $log): ?>
            <?php $created = !empty($log->created_at) ? date('d/m/Y H:i', strtotime($log->created_at)) : '-'; ?>
            <tr<?= $index % 2 === 1 ? ' bgcolor="#f9f9f9"' : '' ?> nobr="true">
                <td width="<?= $col_seq ?>" style="width: <?= $col_seq ?>; text-align: center;"><?= $index + 1 ?></td>
                <td width="<?= $col_time ?>" style="width: <?= $col_time ?>; text-align: center;"><?= htmlspecialchars($created) ?></td>
                <td width="<?= $col_command ?>" style="width: <?= $col_command ?>; text-align: left;"><?= htmlspecialchars($log->command_name ?: ('#' . ($log->command_id ?? ''))) ?></td>
                <td width="<?= $col_device ?>" style="width: <?= $col_device ?>; text-align: left;"><?= htmlspecialchars($log->device_name ?? '-') ?></td>
                <td width="<?= $col_group ?>" style="width: <?= $col_group ?>; text-align: left;"><?= htmlspecialchars($log->group_name ?: 'ยังไม่จัดกลุ่ม') ?></td>
                <td width="<?= $col_source ?>" style="width: <?= $col_source ?>; text-align: left;"><?= htmlspecialchars($formatSource($log)) ?></td>
                <td width="<?= $col_value ?>" style="width: <?= $col_value ?>; text-align: center;"><?= htmlspecialchars($formatValue($log->requested_value ?? '')) ?></td>
                <td width="<?= $col_result ?>" style="width: <?= $col_result ?>; text-align: center;"><?= htmlspecialchars($log->result ?? '-') ?></td>
                <td width="<?= $col_user ?>" style="width: <?= $col_user ?>; text-align: left;"><?= htmlspecialchars($log->user_name ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 20px; background-color: #fff3cd; border: 1px solid #ffeaa7;">
        <p style="color: #d68910; margin: 0;">ไม่พบข้อมูลการควบคุมในช่วงเวลาที่กำหนด</p>
    </div>
    <?php endif; ?>

    <br /><br />
    <div class="footer-note">
        <strong>หมายเหตุ:</strong> รายการเรียงตามเวลาล่าสุดก่อน
    </div>
</body>
</html>
