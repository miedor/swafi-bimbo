<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiScheduledReportMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $reportName,
        public string $reportType,
        public string $generatedAt,
        public int $rowCount,
        public string $fileName,
        public string $mimeType,
        public string $contents
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Reporte programado: ' . $this->reportName)
            ->view('emails.reporte-programado')
            ->with([
                'reportName' => $this->reportName,
                'reportType' => $this->reportType,
                'generatedAt' => $this->generatedAt,
                'rowCount' => $this->rowCount,
            ])
            ->attachData(
                $this->contents,
                $this->fileName,
                ['mime' => $this->mimeType]
            );
    }
}
