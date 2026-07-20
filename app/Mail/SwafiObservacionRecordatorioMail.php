<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiObservacionRecordatorioMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $assignedName,
        public string $numeroActivo,
        public string $folioFactura,
        public string $tipoObservacion,
        public string $prioridad,
        public string $descripcion,
        public string $fechaCompromiso,
        public string $estadoPlazo,
        public string $urlExpediente
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Recordatorio de observación: ' . $this->estadoPlazo)
            ->view('emails.observacion-recordatorio')
            ->with([
                'assignedName' => $this->assignedName,
                'numeroActivo' => $this->numeroActivo,
                'folioFactura' => $this->folioFactura,
                'tipoObservacion' => $this->tipoObservacion,
                'prioridad' => $this->prioridad,
                'descripcion' => $this->descripcion,
                'fechaCompromiso' => $this->fechaCompromiso,
                'estadoPlazo' => $this->estadoPlazo,
                'urlExpediente' => $this->urlExpediente,
            ]);
    }
}
