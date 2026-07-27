<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SwafiResolucionTrasladoMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $requesterName,
        public string $requestUuid,
        public string $numeroActivo,
        public string $descripcionActivo,
        public string $status,
        public string $statusLabel,
        public string $originLocation,
        public string $destinationLocation,
        public string $resolvedBy,
        public string $resolvedAt,
        public string $resolutionComment,
        public bool $movementApplied,
        public string $reviewUrl
    ) {
    }

    public function build()
    {
        return $this
            ->subject('SWAFI | Traslado '.$this->statusLabel.' '.$this->numeroActivo)
            ->view('emails.resolucion-traslado')
            ->with([
                'requesterName' => $this->requesterName,
                'requestUuid' => $this->requestUuid,
                'numeroActivo' => $this->numeroActivo,
                'descripcionActivo' => $this->descripcionActivo,
                'status' => $this->status,
                'statusLabel' => $this->statusLabel,
                'originLocation' => $this->originLocation,
                'destinationLocation' => $this->destinationLocation,
                'resolvedBy' => $this->resolvedBy,
                'resolvedAt' => $this->resolvedAt,
                'resolutionComment' => $this->resolutionComment,
                'movementApplied' => $this->movementApplied,
                'reviewUrl' => $this->reviewUrl,
            ]);
    }
}
