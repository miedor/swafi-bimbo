<?php

namespace Tests\Unit;

use App\Services\CfdiValidationService;
use PHPUnit\Framework\TestCase;

class CfdiValidationServiceTest extends TestCase
{
    public function test_extracts_cfdi_40_core_fields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="4.0" Fecha="2026-06-25T10:30:00" SubTotal="602700.00" Total="602700.00" Moneda="MXN" TipoDeComprobante="I" MetodoPago="PUE" FormaPago="03" LugarExpedicion="06430" Sello="SELLO-DE-PRUEBA" Certificado="CERTIFICADO-DE-PRUEBA" NoCertificado="00001000000000000000">
  <cfdi:Emisor Rfc="ACM010101ABC" Nombre="ACME Industrial SA de CV"/>
  <cfdi:Receptor Rfc="BIM011108DJ5" Nombre="Bimbo SA de CV"/>
  <cfdi:Complemento>
    <tfd:TimbreFiscalDigital Version="1.1" UUID="A1B2C3D4-E5F6-7890-ABCD-000000000184"/>
  </cfdi:Complemento>
</cfdi:Comprobante>
XML;

        $result = (new CfdiValidationService())->extractFromString($xml);

        self::assertTrue($result['xml_bien_formado']);
        self::assertSame('4.0', $result['version_cfdi']);
        self::assertSame('A1B2C3D4-E5F6-7890-ABCD-000000000184', $result['uuid_cfdi']);
        self::assertSame('ACM010101ABC', $result['rfc_emisor']);
        self::assertSame('MXN', $result['moneda']);
        self::assertSame(602700.0, $result['total']);
        self::assertTrue($result['sello_presente']);
        self::assertTrue($result['certificado_presente']);
        self::assertTrue($result['timbre_presente']);
        self::assertSame([], $result['errors']);
    }

    public function test_rejects_malformed_xml_without_throwing(): void
    {
        $result = (new CfdiValidationService())->extractFromString('<cfdi:Comprobante>');

        self::assertFalse($result['xml_bien_formado']);
        self::assertNotEmpty($result['errors']);
    }

    public function test_does_not_resolve_external_entities(): void
    {
        $xml = <<<'XML'
<?xml version="1.0"?>
<!DOCTYPE foo [<!ENTITY xxe SYSTEM "file:///etc/passwd">]>
<Comprobante Version="4.0" Fecha="2026-06-25T10:30:00" Total="1.00" Moneda="MXN" Sello="x" Certificado="x">
  <Emisor Rfc="ACM010101ABC" Nombre="&xxe;"/>
  <Receptor Rfc="BIM011108DJ5"/>
</Comprobante>
XML;

        $result = (new CfdiValidationService())->extractFromString($xml);

        self::assertStringNotContainsString('root:', (string) ($result['nombre_emisor'] ?? ''));
    }
}
