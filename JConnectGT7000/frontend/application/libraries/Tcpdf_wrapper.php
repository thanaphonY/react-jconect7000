<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Wrapper around third_party TCPDF.
 * The class name must not be Tcpdf: PHP class names are case-insensitive,
 * so a class named Tcpdf collides with class TCPDF.
 */
class Tcpdf_wrapper
{
    private $pdf;

    public function __construct()
    {
        if (!class_exists('TCPDF', false)) {
            require_once APPPATH . 'third_party/tcpdf/tcpdf.php';
        }
        $this->pdf = new TCPDF();
    }

    public function create($title = 'Report', $author = 'System', $subject = 'Report')
    {
        $this->pdf = new TCPDF();
        $this->pdf->SetCreator('Report System');
        $this->pdf->SetAuthor($author);
        $this->pdf->SetTitle($title);
        $this->pdf->SetSubject($subject);
        $this->pdf->SetHeaderData('', 0, $title, 'Generated on ' . date('Y-m-d H:i:s'));
        $this->pdf->setHeaderFont(array('helvetica', '', 10));
        $this->pdf->setFooterFont(array('helvetica', '', 8));
        $this->pdf->SetDefaultMonospacedFont('courier');
        $this->pdf->SetMargins(15, 27, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(true, 25);
        $this->pdf->setImageScale(1.25);
        $this->pdf->setFontSubsetting(true);
        return $this;
    }

    public function add_page($orientation = 'P', $format = 'A4')
    {
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetAutoPageBreak(true, 12);
        $this->pdf->AddPage($orientation, $format);
        return $this;
    }

    public function write_html($html, $ln = true, $fill = false, $reseth = true, $cell = false, $align = '')
    {
        $this->pdf->writeHTML($html, $ln, $fill, $reseth, $cell, $align);
        return $this;
    }

    /**
     * Draw the report table with fixed column positions.
     * HTML rowspan/colspan in TCPDF shifts the Time header down beside Min/Max/Average.
     */
    public function write_report_table($title, array $cols, array $rows, $pivotLayout, $footer)
    {
        $pdf = $this->pdf;
        $pdf->SetFont('thsarabunnew', 'B', 14);
        $pdf->SetTextColor(30, 58, 95);
        $pdf->SetDrawColor(30, 58, 95);
        // This font has no bold file, so stroke the glyphs to make the title heavier.
        $pdf->setTextRenderingMode(0.25, true, false);
        $margins = $pdf->getMargins();
        $pageW = $pdf->getPageWidth() - $margins['left'] - $margins['right'];
        $pdf->Cell($pageW, 8, $title, 0, 1, 'C', false);
        $pdf->setTextRenderingMode(0, true, false);
        $pdf->Ln(1);
        $pdf->setCellPaddings(0.6, 0.2, 0.6, 0.2);

        $colCount = max(1, count($cols));
        $timeFirst = isset($cols[0]['key']) && $cols[0]['key'] === 'timestamp';
        $timeW = $timeFirst ? min(42, $pageW * 0.22) : ($pageW / $colCount);
        $valueCount = $timeFirst ? max(1, $colCount - 1) : $colCount;
        $valueW = $timeFirst ? ($pageW - $timeW) / $valueCount : $timeW;
        $headerH = 6.5;
        $groups = (is_array($pivotLayout) && !empty($pivotLayout['groups'])) ? $pivotLayout['groups'] : null;
        $timeLabel = (is_array($pivotLayout) && !empty($pivotLayout['time_label']))
            ? $pivotLayout['time_label']
            : ($timeFirst ? ($cols[0]['label'] ?? 'Time') : '');

        $drawHeader = function () use ($pdf, $margins, $pageW, $timeFirst, $timeW, $valueW, $headerH, $groups, $timeLabel, $cols) {
            if ($pdf->GetY() + ($headerH * 2) > $pdf->getPageHeight() - $margins['bottom']) {
                $pdf->AddPage();
            }
            $x0 = $margins['left'];
            $y0 = $pdf->GetY();
            $pdf->SetDrawColor(186, 198, 210);
            $pdf->SetLineWidth(0.15);
            $pdf->SetFillColor(30, 58, 95);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('thsarabunnew', 'B', 11);

            if ($groups) {
                $pdf->SetXY($x0, $y0);
                $pdf->Cell($timeW, $headerH * 2, $timeLabel, 1, 0, 'C', true, '', 0, false, 'T', 'M');
                $x = $x0 + $timeW;
                foreach ($groups as $group) {
                    $span = max(1, count($group['subs']));
                    $pdf->SetXY($x, $y0);
                    $pdf->Cell($valueW * $span, $headerH, $group['header'], 1, 0, 'C', true, '', 0, false, 'T', 'M');
                    $x += $valueW * $span;
                }
                $pdf->SetFont('thsarabunnew', 'B', 10);
                $x = $x0 + $timeW;
                $y1 = $y0 + $headerH;
                foreach ($groups as $group) {
                    foreach ($group['subs'] as $sub) {
                        $pdf->SetXY($x, $y1);
                        $pdf->Cell($valueW, $headerH, $sub, 1, 0, 'C', true, '', 0, false, 'T', 'M');
                        $x += $valueW;
                    }
                }
                $pdf->SetXY($x0, $y0 + ($headerH * 2));
            } else {
                $x = $x0;
                foreach ($cols as $index => $col) {
                    $w = ($timeFirst && $index === 0) ? $timeW : $valueW;
                    $pdf->MultiCell($w, 8, $col['label'], 1, 'C', true, 0, $x, $y0, true, 0, false, true, 8, 'M');
                    $x += $w;
                }
                $pdf->SetXY($x0, $y0 + 8);
            }
        };

        $drawHeader();
        $pdf->SetFont('thsarabunnew', '', 10);
        $pdf->SetTextColor(36, 48, 64);
        $pdf->SetDrawColor(186, 198, 210);
        $rowH = 7.2;
        foreach ($rows as $index => $row) {
            if ($pdf->GetY() + $rowH > $pdf->getPageHeight() - $margins['bottom']) {
                $pdf->AddPage();
                $drawHeader();
                $pdf->SetFont('thsarabunnew', '', 10);
                $pdf->SetTextColor(36, 48, 64);
                $pdf->SetDrawColor(186, 198, 210);
            }
            $fill = ($index % 2 === 0) ? [255, 255, 255] : [244, 247, 251];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $x = $margins['left'];
            $y = $pdf->GetY();
            foreach ($cols as $colIndex => $col) {
                $w = ($timeFirst && $colIndex === 0) ? $timeW : $valueW;
                $value = isset($row[$col['key']]) ? $row[$col['key']] : '';
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $text = trim((string) $value);
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $rowH, $text, 1, 0, 'C', true, '', 0, false, 'T', 'M');
                $x += $w;
            }
            $pdf->SetXY($margins['left'], $y + $rowH);
        }

        // setPage() restores that page's auto-break (12mm). A cell inside that zone
        // is moved to the next page at the top margin, and the last page gains a blank page.
        $pageCount = $pdf->getNumPages();
        $numW = 28;
        for ($page = 1; $page <= $pageCount; $page++) {
            $pdf->setPage($page);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetFont('thsarabunnew', '', 9);
            $pdf->SetTextColor(107, 114, 128);
            $pdf->SetXY($margins['left'], $pdf->getPageHeight() - 10);
            $pdf->Cell($pageW - $numW, 4, $footer, 0, 0, 'L', false, '', 1, true);
            $pdf->Cell($numW, 4, 'Page ' . $page . '/' . $pageCount, 0, 0, 'R', false, '', 0, true);
        }
        return $this;
    }

    public function set_font($family, $style = '', $size = 12)
    {
        $this->pdf->SetFont($family, $style, $size);
        return $this;
    }

    public function save($file_path)
    {
        $this->pdf->Output($file_path, 'F');
        return $file_path;
    }
}
