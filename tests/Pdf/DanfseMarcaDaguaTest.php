<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

use NFSeNacional\Common\Response;
use NFSeNacional\Pdf\DanfseGeneratorNacional;
use NFSeNacional\Tests\Tools\DanfseXmlFixture;
use PHPUnit\Framework\TestCase;

/**
 * Marca d'água "CANCELADA" aplicada na montagem do PDF (mesmo mPDF, antes do WriteHTML).
 * O status de cancelamento não consta no XML autorizado; o chamador informa por parâmetro.
 */
class DanfseMarcaDaguaTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf não instalado');
        }
    }

    private function gerar(?bool $canceled = null): string
    {
        $response = new Response(sucesso: true, xml: DanfseXmlFixture::NFSE);
        $gerador = new DanfseGeneratorNacional();

        return $canceled === null
            ? $gerador->gerar($response)
            : $gerador->gerar($response, [], $canceled);
    }

    public function testSemOParametroNaoTemMarcaDagua(): void
    {
        $pdf = $this->gerar();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertFalse(PdfConteudo::temMarcaCancelada($pdf));
        $this->assertStringNotContainsString('0.984 0.000 0.000 rg', PdfConteudo::descomprimido($pdf));
    }

    public function testCanceledFalseNaoTemMarcaDagua(): void
    {
        $this->assertFalse(PdfConteudo::temMarcaCancelada($this->gerar(false)));
    }

    public function testCanceledTrueAplicaMarcaCanceladaVermelhaEmDiagonal(): void
    {
        $pdf = $this->gerar(true);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue(PdfConteudo::temMarcaCancelada($pdf), 'texto CANCELADA, cor #fb0000, 50° e opacidade 0,4');
    }

    public function testSemOParametroEOMesmoQueCanceledFalse(): void
    {
        // Compatibilidade: o conteúdo desenhado é idêntico ao de antes (as datas do Info não contam)
        $this->assertSame(
            PdfConteudo::descomprimido($this->gerar()),
            PdfConteudo::descomprimido($this->gerar(false))
        );
    }

    public function testMarcaDaguaNaoAddPaginas(): void
    {
        // A marca é desenhada na mesma página do DANFSe; o documento continua com uma página
        foreach ([false, true] as $canceled) {
            $pdf = $this->gerar($canceled);
            $paginas = preg_match_all('/\/Type\s*\/Page\b(?!s)/', $pdf);
            $this->assertSame(1, $paginas, 'canceled=' . var_export($canceled, true));
        }
    }

    public function testCadaInstanciaEIndependente(): void
    {
        $response = new Response(sucesso: true, xml: DanfseXmlFixture::NFSE);

        // Uma cancelada não contamina a seguinte
        $cancelada = (new DanfseGeneratorNacional())->gerar($response, [], true);
        $normal = (new DanfseGeneratorNacional())->gerar($response, [], false);

        $this->assertTrue(PdfConteudo::temMarcaCancelada($cancelada));
        $this->assertFalse(PdfConteudo::temMarcaCancelada($normal));
    }
}
