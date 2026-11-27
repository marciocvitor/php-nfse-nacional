<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as HttpResponse;
use NFSeNacional\Common\Logger;
use NFSeNacional\Config\Config;
use NFSeNacional\Exception\CommunicationException;
use NFSeNacional\Exception\DanfseIndisponivelException;
use NFSeNacional\Security\Certificate;
use NFSeNacional\Tests\Pdf\PdfConteudo;
use NFSeNacional\Tools\Tools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * BUG 4 — a API de DANFSe do ADN foi descontinuada em 03/08/2026. Falha de TLS/5xx deve ser tratada
 * como indisponibilidade, sem nova tentativa, permitindo cair no gerador local.
 */
class DanfseApiIndisponivelTest extends TestCase
{
    private const CHAVE = '35503082' . '11222333000181' . '0000000000000000000000000001';

    private const TLS_ERRO = 'cURL error 56: OpenSSL SSL_read: error:0A000410:SSL routines::'
        . 'sslv3 alert handshake failure, errno 10054';

    private ?string $pem = null;

    protected function tearDown(): void
    {
        if ($this->pem !== null && is_file($this->pem)) {
            unlink($this->pem);
        }
    }

    /**
     * @param array<int, mixed> $respostas respostas ou exceções da fila do MockHandler
     */
    private function criarTools(array $respostas, int $ambiente = 2): array
    {
        $mock = new MockHandler($respostas);
        $cliente = new Client(['handler' => HandlerStack::create($mock)]);

        // Certificado sem PFX: só o caminho do PEM é usado (o MockHandler ignora o mTLS)
        $this->pem = tempnam(sys_get_temp_dir(), 'nfse_test_pem_');
        file_put_contents($this->pem, 'nao-e-um-certificado-real');
        $certificado = (new ReflectionClass(Certificate::class))->newInstanceWithoutConstructor();
        $this->definir($certificado, 'pemFile', $this->pem);
        $this->definir($certificado, 'passphrase', '');

        $tools = (new ReflectionClass(Tools::class))->newInstanceWithoutConstructor();
        $this->definir($tools, 'config', Config::fromArray(['cnpj' => '11222333000181', 'ambiente' => $ambiente]));
        $this->definir($tools, 'certificate', $certificado);
        $this->definir($tools, 'logger', new Logger(sys_get_temp_dir() . '/nfse-nacional-testes'));
        $this->definir($tools, 'httpClient', $cliente);

        return [$tools, $mock];
    }

    private function definir(object $objeto, string $propriedade, mixed $valor): void
    {
        (new ReflectionProperty($objeto::class, $propriedade))->setValue($objeto, $valor);
    }

    private function erroTls(): ConnectException
    {
        $requisicao = new Request('GET', 'https://adn.producaorestrita.nfse.gov.br/danfse/x');

        return new ConnectException(self::TLS_ERRO, $requisicao);
    }

    public function testFalhaDeHandshakeTlsViraDanfseIndisponivel(): void
    {
        [$tools] = $this->criarTools([$this->erroTls()]);

        try {
            $tools->baixarDanfse(self::CHAVE);
            $this->fail('Deveria lançar DanfseIndisponivelException');
        } catch (DanfseIndisponivelException $e) {
            $this->assertStringContainsString('handshake failure', $e->getMessage());
            $this->assertStringContainsString('03/08/2026', $e->getMessage());
            $this->assertInstanceOf(ConnectException::class, $e->getPrevious());
        }
    }

    public function testExcecaoDeIndisponibilidadeContinuaSendoCommunicationException(): void
    {
        // Compatibilidade: quem já capturava CommunicationException segue funcionando
        [$tools] = $this->criarTools([$this->erroTls()]);

        $this->expectException(CommunicationException::class);
        $tools->baixarDanfse(self::CHAVE);
    }

    public function testNaoTentaDeNovoDepoisDaPrimeiraFalha(): void
    {
        [$tools, $mock] = $this->criarTools([$this->erroTls(), new HttpResponse(200, [], '%PDF-1.7 fake')]);

        try {
            $tools->baixarDanfse(self::CHAVE);
        } catch (DanfseIndisponivelException) {
        }
        $this->assertCount(1, $mock, 'a primeira chamada consome apenas uma resposta da fila');

        try {
            $tools->baixarDanfse(self::CHAVE);
            $this->fail('A segunda chamada deve falhar sem acessar a rede');
        } catch (DanfseIndisponivelException) {
        }

        $this->assertCount(1, $mock, 'a segunda chamada não pode ir à rede');
    }

    public function testRespostaCincoXxViraDanfseIndisponivel(): void
    {
        [$tools] = $this->criarTools([new HttpResponse(503, [], 'No server is available to handle this request.')]);

        $this->expectException(DanfseIndisponivelException::class);
        $tools->baixarDanfse(self::CHAVE);
    }

    public function testRespostaCincoXxTambemEvitaNovasTentativas(): void
    {
        [$tools, $mock] = $this->criarTools([new HttpResponse(502), new HttpResponse(200, [], '%PDF-1.7 fake')]);

        try {
            $tools->baixarDanfse(self::CHAVE);
        } catch (DanfseIndisponivelException) {
        }

        $this->expectException(DanfseIndisponivelException::class);
        try {
            $tools->baixarDanfse(self::CHAVE);
        } finally {
            $this->assertCount(1, $mock);
        }
    }

    public function testNotaNaoEncontradaNaoMarcaApiComoIndisponivel(): void
    {
        [$tools, $mock] = $this->criarTools([
            new HttpResponse(404, [], '{"mensagem":"NFS-e nao encontrada"}'),
            new HttpResponse(200, [], '%PDF-1.7 fake'),
        ]);

        try {
            $tools->baixarDanfse(self::CHAVE);
            $this->fail('404 deveria lançar CommunicationException');
        } catch (DanfseIndisponivelException) {
            $this->fail('404 não é indisponibilidade da API');
        } catch (CommunicationException $e) {
            $this->assertStringContainsString('NFS-e nao encontrada', $e->getMessage());
            $this->assertSame(404, $e->getCode());
        }

        $this->assertStringStartsWith('%PDF', $tools->baixarDanfse(self::CHAVE));
        $this->assertCount(0, $mock);
    }

    public function testPdfValidoContinuaSendoRetornado(): void
    {
        $pdf = new HttpResponse(200, ['Content-Type' => 'application/pdf'], '%PDF-1.7 conteudo');
        [$tools] = $this->criarTools([$pdf]);

        $this->assertSame('%PDF-1.7 conteudo', $tools->baixarDanfse(self::CHAVE));
    }

    public function testObterDanfseUsaApiQuandoDisponivel(): void
    {
        [$tools] = $this->criarTools([new HttpResponse(200, [], '%PDF-1.7 oficial')]);

        $resultado = $tools->obterDanfse(self::CHAVE, '<NFSe/>');

        $this->assertSame('api', $resultado['origem']);
        $this->assertSame('%PDF-1.7 oficial', $resultado['pdf']);
    }

    public function testObterDanfseSemXmlEApiIndisponivelLancaErroClaro(): void
    {
        [$tools] = $this->criarTools([$this->erroTls()]);

        $this->expectException(\InvalidArgumentException::class);
        $tools->obterDanfse(self::CHAVE, '');
    }

    public function testObterDanfseCaiNoGeradorLocalSemTentarDeNovo(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf não instalado');
        }

        [$tools, $mock] = $this->criarTools([$this->erroTls(), new HttpResponse(200, [], '%PDF-1.7 fake')]);

        $primeiro = $tools->obterDanfse(self::CHAVE, DanfseXmlFixture::NFSE);
        $segundo = $tools->obterDanfse(self::CHAVE, DanfseXmlFixture::NFSE);

        $this->assertSame('local', $primeiro['origem']);
        $this->assertStringStartsWith('%PDF', $primeiro['pdf']);
        $this->assertSame('local', $segundo['origem']);
        $this->assertCount(1, $mock, 'a API só pode ser consultada uma vez');
    }

    public function testObterDanfseEncaminhaCanceledParaOGeradorLocal(): void
    {
        if (!class_exists(\Mpdf\Mpdf::class)) {
            $this->markTestSkipped('mpdf/mpdf não instalado');
        }

        [$tools] = $this->criarTools([$this->erroTls()]);

        $cancelada = $tools->obterDanfse(self::CHAVE, DanfseXmlFixture::NFSE, [], true);
        $normal = $tools->obterDanfse(self::CHAVE, DanfseXmlFixture::NFSE);
        $normalFalse = $tools->obterDanfse(self::CHAVE, DanfseXmlFixture::NFSE, [], false);

        $this->assertSame('local', $cancelada['origem']);
        $this->assertTrue(PdfConteudo::temMarcaCancelada($cancelada['pdf']));
        $this->assertFalse(PdfConteudo::temMarcaCancelada($normal['pdf']), 'padrão é não cancelada');
        $this->assertFalse(PdfConteudo::temMarcaCancelada($normalFalse['pdf']));
    }
}
