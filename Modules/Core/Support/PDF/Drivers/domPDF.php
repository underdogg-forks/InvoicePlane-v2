<?php

namespace Modules\Core\Support\PDF\Drivers;

use Dompdf\Dompdf as DompdfEngine;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Modules\Core\Support\PDF\PDFAbstract;

class domPDF extends PDFAbstract
{
    public function save($html, $filename): void
    {
        file_put_contents($filename, $this->getOutput($html));
    }

    public function getOutput($html)
    {
        $pdf = $this->getPdf($html);

        return $pdf->output();
    }

    private function getPdf($html)
    {
        $workDir = storage_path('app/dompdf');
        File::ensureDirectoryExists($workDir);

        $options = new Options();

        $options->setTempDir($workDir);
        $options->setFontDir($workDir);
        $options->setFontCache($workDir);
        $options->setLogOutputFile($workDir . '/dompdf.log');
        // Remote fetching stays disabled: images must resolve to local paths.
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setIsFontSubsettingEnabled(true);

        $pdf = new DompdfEngine($options);

        $pdf->setPaper($this->paperSize, $this->paperOrientation);
        $pdf->loadHtml($html);

        $pdf->render();

        return $pdf;
    }
}
