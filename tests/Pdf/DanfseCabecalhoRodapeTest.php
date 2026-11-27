<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

use NFSeNacional\Common\Response;
use NFSeNacional\Pdf\DanfseGeneratorNacional;
use NFSeNacional\Tests\Tools\DanfseXmlFixture;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cabeçalho (logo, DANFSe v2.0, município, ambientes, QR Code) e rodapé (faixa de assinatura)
 * no padrão do DANFSe de referência do portal nacional.
 */
class DanfseCabecalhoRodapeTest extends TestCase
{
    private const CHAVE = '35503082112223330001810000000000000000000000003275';

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

    private function dados(string $xml = DanfseXmlFixture::NFSE): array
    {
        return $this->chamar(new DanfseGeneratorNacional(), 'extrairDados', $xml);
    }

    private function cabecalho(?array $dados = null): string
    {
        return $this->chamar(new DanfseGeneratorNacional(), 'renderizarCabecalho', $dados ?? $this->dados());
    }

    private function rodape(?array $dados = null): string
    {
        return $this->chamar(new DanfseGeneratorNacional(), 'renderizarRodape', $dados ?? $this->dados());
    }

    // ---- logo e QR automáticos ---------------------------------------------------------------

    public function testLogoNacionalEmbutidoNaBibliotecaEUmPngTransparente(): void
    {
        $arquivo = dirname(__DIR__, 2) . '/src/Pdf/imagens/nfse-logo.png';

        $this->assertFileExists($arquivo);
        $info = getimagesize($arquivo);
        $this->assertSame('image/png', $info['mime']);
        $this->assertSame([540, 107], [$info[0], $info[1]]);
        $this->assertSame(6, ord(file_get_contents($arquivo)[25]), 'PNG com canal alfa (fundo transparente)');
    }

    public function testCabecalhoTemLogoEQrCodeSemPrecisarDeParametro(): void
    {
        $html = $this->cabecalho();
        $logo = base64_encode((string) file_get_contents(dirname(__DIR__, 2) . '/src/Pdf/imagens/nfse-logo.png'));

        $this->assertStringContainsString($logo, $html, 'logo nacional carregado da pasta de imagens');
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'), 'somente logo e QR Code (sem brasão)');
        $this->assertStringContainsString('leitura deste código QR', $html);
    }

    public function testQrCodeEGeradoAPartirDaChaveDeAcesso(): void
    {
        $uri = $this->chamar(new DanfseGeneratorNacional(), 'qrCodeConsulta', self::CHAVE);

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $info = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/png;base64,'))));
        $this->assertSame($info[0], $info[1], 'QR Code quadrado');
        $this->assertGreaterThanOrEqual(80, $info[0]);
    }

    public function testQrCodeVazioParaChaveInvalidaOuAusente(): void
    {
        $gerador = new DanfseGeneratorNacional();

        $this->assertSame('', $this->chamar($gerador, 'qrCodeConsulta', ''));
        $this->assertSame('', $this->chamar($gerador, 'qrCodeConsulta', '123'));
    }

    public function testQrCodeEReprodutivelParaAMesmaChave(): void
    {
        $gerador = new DanfseGeneratorNacional();
        $primeiro = $this->chamar($gerador, 'qrCodeConsulta', self::CHAVE);

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($primeiro, $this->chamar($gerador, 'qrCodeConsulta', self::CHAVE));
        }
    }

    // ---- cabeçalho: título, município e ambientes --------------------------------------------

    public function testCabecalhoTemTituloMunicipioEAmbientesDoXml(): void
    {
        $dados = $this->dados();
        $html = $this->cabecalho($dados);

        $this->assertSame('Sao Paulo', $dados['municipioEmissor']);
        $this->assertSame('2', $dados['ambienteGerador']);
        $this->assertSame('2', $dados['tipoAmbiente']);

        $this->assertStringContainsString('DANFSe v2.0', $html);
        $this->assertStringContainsString('Documento Auxiliar da NFS-e', $html);
        $this->assertStringContainsString('Município: Sao Paulo', $html);
        $this->assertStringContainsString('Ambiente Gerador: 2', $html);
        $this->assertStringContainsString('Tipo de Ambiente: 2', $html);
    }

    public function testCabecalhoMostraUfAoLadoDoMunicipioQuandoHaUfNoXml(): void
    {
        $dados = $this->dados();
        $dados['ufEmissor'] = 'MG';
        $dados['municipioEmissor'] = 'Belo Horizonte';

        $this->assertStringContainsString('Município: Belo Horizonte - MG', $this->cabecalho($dados));
    }

    public function testUfDoMunicipioVemDoEnderecoDoEmitenteNoXml(): void
    {
        $xml = str_replace('<emit>', '<emit><enderNac><xLgr>Rua A</xLgr><UF>MG</UF></enderNac>', DanfseXmlFixture::NFSE);
        $this->assertNotSame(DanfseXmlFixture::NFSE, $xml, 'o fixture deve conter <emit>');

        $dados = $this->dados($xml);

        $this->assertSame('MG', $dados['ufEmissor']);
        $this->assertStringContainsString('Município: Sao Paulo - MG', $this->cabecalho($dados));
    }

    public function testCabecalhoUsaTracoQuandoOsDadosNaoVemNoXml(): void
    {
        $html = $this->cabecalho(['chaveAcesso' => self::CHAVE]);

        $this->assertStringContainsString('Município: -', $html);
        $this->assertStringContainsString('Ambiente Gerador: -', $html);
        $this->assertStringContainsString('Tipo de Ambiente: -', $html);
    }

    public function testCabecalhoNaoTemMaisBrasaoNemPrefeituraMunicipal(): void
    {
        $html = $this->cabecalho();

        $this->assertStringNotContainsString('Prefeitura Municipal', $html);
        $this->assertFalse(method_exists(DanfseGeneratorNacional::class, 'setBrasao'), 'a opção de brasão foi removida');
    }

    // ---- rodapé: faixa de cientificação e assinatura -----------------------------------------

    public function testRodapeTemAFaixaDeCientificacaoAssinaturaEChave(): void
    {
        $html = $this->rodape();

        $this->assertStringContainsString('DATA CIENTIFICAÇÃO:', $html);
        $this->assertStringContainsString('IDENTIFICAÇÃO E ASSINATURA', $html);
        $this->assertStringContainsString('Nº NFS-e / CHAVE NFS-e', $html);
        $this->assertStringContainsString('3275 / ' . self::CHAVE, $html);
    }

    public function testRodapeNaoTemMaisOsTextosAntigos(): void
    {
        $html = $this->rodape();

        $this->assertStringNotContainsString('Código de Verificação', $html);
        $this->assertStringNotContainsString('representação gráfica', $html);
    }

    // ---- PDF completo ------------------------------------------------------------------------

    public function testPdfSaiEmUmaPaginaComCabecalhoERodape(): void
    {
        $pdf = (new DanfseGeneratorNacional())->gerar(new Response(sucesso: true, xml: DanfseXmlFixture::NFSE));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $pdf), 'o DANFSe deve ocupar uma única página');
    }

    public function testInformacoesComplementaresTemALinhaDaLei12741(): void
    {
        $html = $this->chamar(new DanfseGeneratorNacional(), 'renderizarInformacoesComplementares', $this->dados());

        $this->assertStringContainsString('Totais aproximados dos Tributos cfe. Lei n° 12.741/2012: Federais: -; Estaduais: -; Municipais: -;', $html);
    }
}
