<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiSolicitudTrasladoMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $approverName,
        public string $requestedBy,
        public string $requestUuid,
        public string $numeroActivo,
        public string $descripcionActivo,
        public string $originLocation,
        public string $destinationLocation,
        public string $movementDate,
        public string $reason,
        public string $destinationResponsible,
        public string $reviewUrl
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Traslado pendiente de aprobación ' . $this->numeroActivo)
            ->view('emails.solicitud-traslado')
            ->with([
                'approverName' => $this->approverName,
                'requestedBy' => $this->requestedBy,
                'requestUuid' => $this->requestUuid,
                'numeroActivo' => $this->numeroActivo,
                'descripcionActivo' => $this->descripcionActivo,
                'originLocation' => $this->originLocation,
                'destinationLocation' => $this->destinationLocation,
                'movementDate' => $this->movementDate,
                'reason' => $this->reason,
                'destinationResponsible' => $this->destinationResponsible,
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
