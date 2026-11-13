<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(dirname(__FILE__) . '/Dompdf/autoload.inc.php');
use Dompdf\Dompdf as DOMPDF;

class Pdf
{
    protected function ci()
	{
		return get_instance();
	}
    function createPDF($html, $filename='', $download=TRUE, $paper='A4', $orientation='portrait'){
        $tmp = sys_get_temp_dir();

        $dompdf = new DOMPDF([
            'logOutputFile' => '',
            // authorize DomPdf to download fonts and other Internet assets
            'isRemoteEnabled' => true,
            // all directories must exist and not end with /
            'fontDir' => $tmp,
            'fontCache' => $tmp,
            'tempDir' => $tmp,
            'chroot' => $tmp,
        ]);
        $dompdf->load_html($html,'UTF-8');
        $dompdf->set_paper($paper, $orientation);
        $dompdf->render();
        if($download)
            $dompdf->stream($filename.'.pdf', array('Attachment' => 1));
        else
            $dompdf->stream($filename.'.pdf', array('Attachment' => 0));
    }
}
?>