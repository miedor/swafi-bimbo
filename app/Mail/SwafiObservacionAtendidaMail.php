<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiObservacionAtendidaMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $reviewerName,
        public string $attendedBy,
        public string $numeroActivo,
        public string $folioFactura,
        public string $tipoObservacion,
        public string $prioridad,
        public string $descripcion,
        public string $respuestaAtencion,
        public string $fechaAtencion,
        public string $urlExpediente
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Observación atendida pendiente de validación')
            ->view('emails.observacion-atendida')
            ->with([
                'reviewerName' => $this->reviewerName,
                'attendedBy' => $this->attendedBy,
                'numeroActivo' => $this->numeroActivo,
                'folioFactura' => $this->folioFactura,
                'tipoObservacion' => $this->tipoObservacion,
                'prioridad' => $this->prioridad,
                'descripcion' => $this->descripcion,
                'respuestaAtencion' => $this->respuestaAtencion,
                'fechaAtencion' => $this->fechaAtencion,
                'urlExpediente' => $this->urlExpediente,
            ]);
    }
}
