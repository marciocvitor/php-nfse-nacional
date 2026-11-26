<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

use NFSeNacional\Common\Response;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Pdf\DanfseGeneratorNacional;
use NFSeNacional\Tests\Tools\DanfseXmlFixture;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Cabeçalho no layout nacional (referência NFS-e 3275):
 *  - logo nacional e QR Code: automáticos (recursos da biblioteca);
 *  - brasão: opcional, informado por quem usa a biblioteca, validado pelo padrão
 *    PNG, até 300 x 300 px e até 60 KB.
 */
class DanfseCabecalhoBrasaoTest extends TestCase
{
    private const CHAVE = '35503082112223330001810000000000000000000000003275';

    /** @var string[] */
    private array $temporarios = [];

    protected function setUp(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf não instalado');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->temporarios as $arquivo) {
            if (is_file($arquivo)) {
                unlink($arquivo);
            }
        }
    }

    // ---- utilitários -------------------------------------------------------------------------

    private function pngBytes(int $largura, int $altura, bool $ruido = false): string
    {
        $im = imagecreatetruecolor($largura, $altura);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
        if ($ruido) {
            mt_srand(7);
            for ($y = 0; $y < $altura; $y++) {
                for ($x = 0; $x < $largura; $x++) {
                    imagesetpixel($im, $x, $y, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
                }
            }
        }
        ob_start();
        // Sem ruído, compressão máxima (arquivo pequeno); com ruído, o arquivo fica propositalmente grande
        imagepng($im, null, $ruido ? 0 : 9);

        return (string) ob_get_clean();
    }

    private function arquivo(string $conteudo, string $extensao = 'png'): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'brasao_') . '.' . $extensao;
        file_put_contents($caminho, $conteudo);
        $this->temporarios[] = $caminho;

        return $caminho;
    }

    private function dados(): array
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('extrairDados');
        $m->setAccessible(true);

        return $m->invoke(new DanfseGeneratorNacional(), DanfseXmlFixture::NFSE);
    }

    private function cabecalhoHtml(DanfseGeneratorNacional $gerador): string
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('renderizarCabecalho');
        $m->setAccessible(true);

        return $m->invoke($gerador, $this->dados());
    }

    private function gerarPdf(DanfseGeneratorNacional $gerador): string
    {
        return $gerador->gerar(new Response(sucesso: true, xml: DanfseXmlFixture::NFSE));
    }

    private function brasaoCarregado(DanfseGeneratorNacional $gerador): ?string
    {
        $p = (new ReflectionClass(DanfseGeneratorNacional::class))->getProperty('brasao');
        $p->setAccessible(true);

        return $p->getValue($gerador);
    }

    private function assertRecusado(string $brasao, string $trechoDaMensagem): void
    {
        $gerador = new DanfseGeneratorNacional();

        try {
            $gerador->setBrasao($brasao);
            $this->fail('Brasão fora do padrão deveria lançar ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString($trechoDaMensagem, $e->getMessage());
            $this->assertStringContainsString('Brasão inválido', $e->getMessage());
            $this->assertStringContainsString('Padrão esperado: PNG, no máximo 300 x 300 pixels (recomendado 192 x 192) e até 60 KB.', $e->getMessage());
        }

        $this->assertNull($this->brasaoCarregado($gerador), 'brasão inválido não pode ser carregado');
        // Sem quebrar a geração do DANFSe
        $this->assertStringStartsWith('%PDF', $this->gerarPdf($gerador));
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
        $html = $this->cabecalhoHtml(new DanfseGeneratorNacional());
        $logo = base64_encode((string) file_get_contents(dirname(__DIR__, 2) . '/src/Pdf/imagens/nfse-logo.png'));

        $this->assertStringContainsString($logo, $html, 'logo nacional carregado da pasta de imagens');
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'), 'logo + QR Code, sem brasão');
        $this->assertStringContainsString('DANFSe v1.0', $html);
        $this->assertStringContainsString('Documento Auxiliar da NFS-e', $html);
        $this->assertStringContainsString('Prefeitura Municipal de Sao Paulo', $html);
        $this->assertStringContainsString('leitura deste código QR', $html);
    }

    public function testQrCodeEReprodutivelParaAMesmaChave(): void
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('qrCodeConsulta');
        $m->setAccessible(true);
        $gerador = new DanfseGeneratorNacional();

        $primeiro = $m->invoke($gerador, self::CHAVE);
        for ($i = 0; $i < 10; $i++) {
            $this->assertSame($primeiro, $m->invoke($gerador, self::CHAVE));
        }
    }

    public function testQrCodeEGeradoAPartirDaChaveDeAcesso(): void
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('qrCodeConsulta');
        $m->setAccessible(true);
        $gerador = new DanfseGeneratorNacional();

        $uri = $m->invoke($gerador, self::CHAVE);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $info = getimagesizefromstring((string) base64_decode(substr($uri, strlen('data:image/png;base64,'))));
        $this->assertSame($info[0], $info[1], 'QR Code é quadrado');
        $this->assertGreaterThanOrEqual(80, $info[0]);

        $this->assertSame('', $m->invoke($gerador, ''), 'sem chave de acesso não há QR Code');
        $this->assertSame('', $m->invoke($gerador, '123'), 'chave fora do padrão (50 dígitos) não gera QR Code');
    }

    // ---- brasão válido -----------------------------------------------------------------------

    public function testSemBrasaoOLayoutSegueSemOBrasaoEGeraOPdf(): void
    {
        $gerador = new DanfseGeneratorNacional();
        $html = $this->cabecalhoHtml($gerador);

        $this->assertNull($this->brasaoCarregado($gerador));
        $this->assertSame(2, substr_count($html, 'data:image/png;base64,'));
        $pdf = $this->gerarPdf($gerador);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b(?!s)/', $pdf), 'continua com uma página');
    }

    public function testBrasaoValidoPassadoPeloRpApareceNoCabecalho(): void
    {
        $png = $this->pngBytes(192, 192);
        $gerador = new DanfseGeneratorNacional();
        $gerador->setBrasao($png);

        $html = $this->cabecalhoHtml($gerador);

        $this->assertSame($png, $this->brasaoCarregado($gerador));
        $this->assertSame(3, substr_count($html, 'data:image/png;base64,'), 'logo + brasão + QR Code');
        $this->assertStringContainsString(base64_encode($png), $html);

        $semBrasao = $this->gerarPdf(new DanfseGeneratorNacional());
        $comBrasao = $this->gerarPdf($gerador);
        $this->assertStringStartsWith('%PDF', $comBrasao);
        $this->assertGreaterThan(strlen($semBrasao), strlen($comBrasao), 'o PDF com brasão embute a imagem');
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b(?!s)/', $comBrasao), 'continua com uma página');
    }

    public function testBrasaoAceitoPorCaminhoConteudoBinarioEDataUri(): void
    {
        $png = $this->pngBytes(150, 180);

        foreach ([
            'caminho' => $this->arquivo($png),
            'binário' => $png,
            'data URI' => 'data:image/png;base64,' . base64_encode($png),
        ] as $forma => $origem) {
            $gerador = new DanfseGeneratorNacional();
            $gerador->setBrasao($origem);
            $this->assertSame($png, $this->brasaoCarregado($gerador), $forma);
        }
    }

    public function testBrasaoPeloConstrutor(): void
    {
        $png = $this->pngBytes(100, 100);

        $this->assertSame($png, $this->brasaoCarregado(new DanfseGeneratorNacional(['brasao' => $png])));
    }

    public function testBrasaoNoLimiteDoPadraoEAceito(): void
    {
        $gerador = new DanfseGeneratorNacional();
        $gerador->setBrasao($this->pngBytes(300, 300));

        $this->assertNotNull($this->brasaoCarregado($gerador));
    }

    public function testNullRemoveOBrasao(): void
    {
        $gerador = new DanfseGeneratorNacional(['brasao' => $this->pngBytes(100, 100)]);
        $gerador->setBrasao(null);

        $this->assertNull($this->brasaoCarregado($gerador));
    }

    // ---- brasão fora do padrão ---------------------------------------------------------------

    public function testBrasaoComDimensaoExcessivaEhRecusado(): void
    {
        $this->assertRecusado($this->pngBytes(400, 400), 'a imagem tem 400 x 400 pixels e excede o máximo de 300 x 300');
    }

    public function testBrasaoComApenasUmaDimensaoExcessivaEhRecusado(): void
    {
        $this->assertRecusado($this->pngBytes(100, 301), 'a imagem tem 100 x 301 pixels');
    }

    public function testBrasaoComArquivoGrandeDemaisEhRecusado(): void
    {
        $png = $this->pngBytes(250, 250, true);
        $this->assertGreaterThan(61440, strlen($png), 'pré-condição do teste');

        $this->assertRecusado($png, 'excede o limite de 60 KB');
    }

    public function testBrasaoEmJpegEhRecusadoPeloFormato(): void
    {
        $im = imagecreatetruecolor(100, 100);
        ob_start();
        imagejpeg($im);
        $jpeg = (string) ob_get_clean();

        $this->assertRecusado($jpeg, 'o formato não é PNG (recebido: JPEG)');
    }

    public function testBrasaoEmGifEhRecusadoPeloFormato(): void
    {
        $im = imagecreatetruecolor(100, 100);
        ob_start();
        imagegif($im);
        $gif = (string) ob_get_clean();

        $this->assertRecusado($gif, 'o formato não é PNG (recebido: GIF)');
    }

    public function testBrasaoCorrompidoEhRecusado(): void
    {
        $this->assertRecusado("\x89PNG\r\n\x1a\n" . str_repeat("\x00lixo", 40), 'corrompido ou incompleto');
    }

    public function testBrasaoPngTruncadoEhRecusado(): void
    {
        $png = $this->pngBytes(120, 120, true);

        $this->assertRecusado(substr($png, 0, (int) (strlen($png) / 2)), 'corrompido ou incompleto');
    }

    public function testBrasaoComArquivoInexistenteEhRecusado(): void
    {
        $this->assertRecusado(sys_get_temp_dir() . '/nao-existe-brasao.png', 'arquivo não encontrado');
    }

    public function testDataUriComTipoQueNaoEPngEhRecusado(): void
    {
        $this->assertRecusado('data:image/jpeg;base64,' . base64_encode('qualquer'), 'o formato não é PNG');
    }

    public function testDataUriSemBase64EhRecusado(): void
    {
        $this->assertRecusado('data:image/png,abc', 'deve estar em base64');
    }

    public function testBrasaoInvalidoNaoSubstituiOBrasaoValidoJaCarregado(): void
    {
        $valido = $this->pngBytes(120, 140);
        $gerador = new DanfseGeneratorNacional(['brasao' => $valido]);

        try {
            $gerador->setBrasao($this->pngBytes(500, 500));
            $this->fail('Deveria lançar ValidationException');
        } catch (ValidationException) {
        }

        $this->assertSame($valido, $this->brasaoCarregado($gerador));
        $this->assertStringStartsWith('%PDF', $this->gerarPdf($gerador));
    }

    public function testConstrutorComBrasaoInvalidoLancaMensagemClara(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Padrão esperado: PNG');

        new DanfseGeneratorNacional(['brasao' => $this->pngBytes(400, 400)]);
    }

    public function testPadraoDoBrasaoEstaDocumentadoEmConstantes(): void
    {
        $this->assertSame(300, DanfseGeneratorNacional::BRASAO_LARGURA_MAXIMA);
        $this->assertSame(300, DanfseGeneratorNacional::BRASAO_ALTURA_MAXIMA);
        $this->assertSame(192, DanfseGeneratorNacional::BRASAO_DIMENSAO_RECOMENDADA);
        $this->assertSame(61440, DanfseGeneratorNacional::BRASAO_TAMANHO_MAXIMO_BYTES);
        $this->assertSame(
            'PNG, no máximo 300 x 300 pixels (recomendado 192 x 192) e até 60 KB.',
            DanfseGeneratorNacional::padraoBrasao()
        );
    }
}
