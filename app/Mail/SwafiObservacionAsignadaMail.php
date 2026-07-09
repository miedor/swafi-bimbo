<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiObservacionAsignadaMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $assignedName,
        public string $creatorName,
        public string $numeroActivo,
        public string $folioFactura,
        public string $tipoObservacion,
        public string $prioridad,
        public string $descripcion,
        public string $urlExpediente,
        public string $rolDestino
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Nueva observación asignada')
            ->view('emails.observacion-asignada')
            ->with([
                'assignedName' => $this->assignedName,
                'creatorName' => $this->creatorName,
                'numeroActivo' => $this->numeroActivo,
                'folioFactura' => $this->folioFactura,
                'tipoObservacion' => $this->tipoObservacion,
                'prioridad' => $this->prioridad,
                'descripcion' => $this->descripcion,
                'urlExpediente' => $this->urlExpediente,
                'rolDestino' => $this->rolDestino,
            ]);
    }
}
