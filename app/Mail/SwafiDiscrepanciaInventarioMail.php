<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiDiscrepanciaInventarioMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $reportedBy,
        public string $numeroActivo,
        public string $descripcionActivo,
        public string $fechaInventario,
        public string $estatusLocalizacion,
        public string $ubicacionRegistrada,
        public string $ubicacionVerificada,
        public string $observaciones,
        public int $evidenceCount,
        public string $detailUrl
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Discrepancia de inventario ' . $this->numeroActivo)
            ->view('emails.discrepancia-inventario')
            ->with([
                'recipientName' => $this->recipientName,
                'reportedBy' => $this->reportedBy,
                'numeroActivo' => $this->numeroActivo,
                'descripcionActivo' => $this->descripcionActivo,
                'fechaInventario' => $this->fechaInventario,
                'estatusLocalizacion' => $this->estatusLocalizacion,
                'ubicacionRegistrada' => $this->ubicacionRegistrada,
                'ubicacionVerificada' => $this->ubicacionVerificada,
                'observaciones' => $this->observaciones,
                'evidenceCount' => $this->evidenceCount,
                'detailUrl' => $this->detailUrl,
            ]);
    }
}
