<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

use NFSeNacional\Common\Response;
use NFSeNacional\Pdf\DanfseGeneratorNacional;
use NFSeNacional\Tests\Tools\DanfseXmlFixture;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Escape dos valores do XML no HTML, instância reutilizável, município do prestador
 * e rótulos das seções.
 */
class DanfseRevisaoTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf não instalado');
        }
    }

    private function chamar(DanfseGeneratorNacional $gerador, string $metodo, mixed ...$args): mixed
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod($metodo);
        $m->setAccessible(true);

        return $m->invoke($gerador, ...$args);
    }

    private function html(string $xml): string
    {
        $gerador = new DanfseGeneratorNacional();

        return $this->chamar($gerador, 'gerarHTML', $this->chamar($gerador, 'extrairDados', $xml));
    }

    private function xmlComEnderecoDoPrestador(string $cMun, string $uf): string
    {
        return str_replace(
            '<xNome>Empresa Teste LTDA</xNome>',
            '<xNome>Empresa Teste LTDA</xNome><enderNac><xLgr>Rua A</xLgr><nro>10</nro><xBairro>Centro</xBairro>'
            . "<cMun>{$cMun}</cMun><UF>{$uf}</UF><CEP>01001000</CEP></enderNac>",
            DanfseXmlFixture::NFSE
        );
    }

    // ---- escape -------------------------------------------------------------------------------

    public function testValoresDoXmlSaoEscapadosNoHtml(): void
    {
        $xml = str_replace(
            '<xNome>Cliente Teste</xNome>',
            '<xNome>&lt;img src="http://169.254.169.254/x.png"&gt; &amp; Cia</xNome>',
            DanfseXmlFixture::NFSE
        );

        $html = $this->html($xml);

        $this->assertStringNotContainsString('<img src="http://169.254.169.254', $html, 'tag do XML não vira HTML');
        $this->assertStringContainsString('&lt;img src=&quot;http://169.254.169.254/x.png&quot;&gt; &amp; Cia', $html);
    }

    public function testDescricaoDoServicoComMarcacaoEEscapada(): void
    {
        $xml = str_replace(
            '<xDescServ>Suporte tecnico em informatica</xDescServ>',
            '<xDescServ>Suporte &lt;b&gt;premium&lt;/b&gt;</xDescServ>',
            DanfseXmlFixture::NFSE
        );

        $this->assertStringContainsString('Suporte &lt;b&gt;premium&lt;/b&gt;', $this->html($xml));
    }

    // ---- instância reutilizável ------------------------------------------------------------

    public function testMesmaInstanciaGeraVariosPdfsDeUmaPagina(): void
    {
        $gerador = new DanfseGeneratorNacional();
        $response = new Response(sucesso: true, xml: DanfseXmlFixture::NFSE);

        $primeiro = $gerador->gerar($response);
        $segundo = $gerador->gerar($response);

        foreach ([$primeiro, $segundo] as $pdf) {
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertSame(1, preg_match_all('#/Type\s*/Page\b#', $pdf));
        }
    }

    public function testCancelamentoNaoVazaParaOProximoPdfDaMesmaInstancia(): void
    {
        $gerador = new DanfseGeneratorNacional();
        $response = new Response(sucesso: true, xml: DanfseXmlFixture::NFSE);

        $this->assertTrue(PdfConteudo::temMarcaCancelada($gerador->gerar($response, [], true)));
        $this->assertFalse(PdfConteudo::temMarcaCancelada($gerador->gerar($response)));
    }

    // ---- município do prestador -----------------------------------------------------------

    public function testMunicipioDoPrestadorUsaONomeQueAPropriaNfseTraz(): void
    {
        $dados = $this->chamar(
            new DanfseGeneratorNacional(),
            'extrairDados',
            $this->xmlComEnderecoDoPrestador('3550308', 'SP')
        );

        $this->assertSame('Sao Paulo - SP', $dados['prestador']['municipio']);
    }

    public function testMunicipioDoPrestadorSemNomeNaNfseMostraOCodigoIbge(): void
    {
        $dados = $this->chamar(
            new DanfseGeneratorNacional(),
            'extrairDados',
            $this->xmlComEnderecoDoPrestador('3106200', 'MG')
        );

        $this->assertSame('3106200 - MG', $dados['prestador']['municipio']);
    }

    public function testGeradorNaoDependeDeBancoDeDadosDoErp(): void
    {
        $fonte = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Pdf/DanfseGeneratorNacional.php');

        $this->assertStringNotContainsString('Db_conn', $fonte);
        $this->assertStringNotContainsString('SELECT', $fonte);
    }

    // ---- rótulos ------------------------------------------------------------------------------

    public function testRotulosDasSecoes(): void
    {
        $html = $this->html(DanfseXmlFixture::NFSE);

        $this->assertStringContainsString(
            '<strong>Descrição do Serviço:</strong> Suporte tecnico em informatica',
            $html
        );
        $this->assertStringContainsString('EMITENTE DA NFS-e (Prestador do Serviço)', $html);
        $this->assertStringContainsString('TOMADOR DO SERVIÇO', $html);
    }
}
