<?php
defined('BASEPATH') or exit('No direct script access allowed');
$title = 'รายงานบันทึก Alarm';
$period_label = $period_label ?? '';
$logs = $logs ?? [];
$col_seq = '6%';
$col_time = '16%';
$col_device = '18%';
$col_metric = '14%';
$col_severity = '10%';
$col_message = '36%';
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
            font-size: 13pt;
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
                <th width="<?= $col_device ?>" bgcolor="#2F72B6" style="width: <?= $col_device ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>อุปกรณ์</b></th>
                <th width="<?= $col_metric ?>" bgcolor="#2F72B6" style="width: <?= $col_metric ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>เมตริก</b></th>
                <th width="<?= $col_severity ?>" bgcolor="#2F72B6" style="width: <?= $col_severity ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>ระดับ</b></th>
                <th width="<?= $col_message ?>" bgcolor="#2F72B6" style="width: <?= $col_message ?>; background-color: #2f72b6; color: #ffffff; text-align: center;"><b>รายละเอียด</b></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $index => $log): ?>
            <?php $created = !empty($log->created_at) ? date('d/m/Y H:i', strtotime($log->created_at)) : '-'; ?>
            <tr<?= $index % 2 === 1 ? ' bgcolor="#f9f9f9"' : '' ?> nobr="true">
                <td width="<?= $col_seq ?>" style="width: <?= $col_seq ?>; text-align: center;"><?= $index + 1 ?></td>
                <td width="<?= $col_time ?>" style="width: <?= $col_time ?>; text-align: center;"><?= htmlspecialchars($created) ?></td>
                <td width="<?= $col_device ?>" style="width: <?= $col_device ?>; text-align: left;"><?= htmlspecialchars($log->device_name ?? '-') ?></td>
                <td width="<?= $col_metric ?>" style="width: <?= $col_metric ?>; text-align: center;"><?= htmlspecialchars($log->metric_key ?? '-') ?></td>
                <td width="<?= $col_severity ?>" style="width: <?= $col_severity ?>; text-align: center;"><?= htmlspecialchars(ucfirst($log->severity ?? '-')) ?></td>
                <td width="<?= $col_message ?>" style="width: <?= $col_message ?>; text-align: left;"><?= htmlspecialchars($log->message ?? '-') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 20px; background-color: #fff3cd; border: 1px solid #ffeaa7;">
        <p style="color: #d68910; margin: 0;">ไม่พบข้อมูล Alarm ในช่วงเวลาที่กำหนด</p>
    </div>
    <?php endif; ?>

    <br /><br />
    <div class="footer-note">
        <strong>หมายเหตุ:</strong> รายการเรียงตามเวลาล่าสุดก่อน
    </div>
</body>
</html>
