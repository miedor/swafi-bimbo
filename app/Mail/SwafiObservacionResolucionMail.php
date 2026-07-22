<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiObservacionResolucionMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $assignedName,
        public string $validatedBy,
        public string $decision,
        public string $numeroActivo,
        public string $folioFactura,
        public string $tipoObservacion,
        public string $descripcion,
        public string $respuestaAtencion,
        public string $comentarioValidacion,
        public string $urlExpediente
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Resolución de observación: '.$this->decision)
            ->view('emails.observacion-resolucion')
            ->with([
                'assignedName' => $this->assignedName,
                'validatedBy' => $this->validatedBy,
                'decision' => $this->decision,
                'numeroActivo' => $this->numeroActivo,
                'folioFactura' => $this->folioFactura,
                'tipoObservacion' => $this->tipoObservacion,
                'descripcion' => $this->descripcion,
                'respuestaAtencion' => $this->respuestaAtencion,
                'comentarioValidacion' => $this->comentarioValidacion,
                'urlExpediente' => $this->urlExpediente,
            ]);
    }
}
