<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Tools;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as HttpResponse;
use NFSeNacional\Common\Logger;
use NFSeNacional\Config\Config;
use NFSeNacional\Exception\CommunicationException;
use NFSeNacional\Security\Certificate;
use NFSeNacional\Tools\Tools;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Verificação TLS, formatação dos erros da API e leitura do número da DPS para o log.
 */
class ComunicacaoApiTest extends TestCase
{
    private const CHAVE = '35503082' . '11222333000181' . '0000000000000000000000000001';

    private ?string $pem = null;

    protected function tearDown(): void
    {
        if ($this->pem !== null && is_file($this->pem)) {
            unlink($this->pem);
        }
    }

    /**
     * @param array<int, mixed> $respostas
     * @param array<string, mixed> $config
     * @return array{0: Tools, 1: MockHandler}
     */
    private function criarTools(array $respostas, array $config = []): array
    {
        $mock = new MockHandler($respostas);

        $this->pem = tempnam(sys_get_temp_dir(), 'nfse_test_pem_');
        file_put_contents($this->pem, 'nao-e-um-certificado-real');
        $certificado = (new ReflectionClass(Certificate::class))->newInstanceWithoutConstructor();
        $this->definir($certificado, 'pemFile', $this->pem);
        $this->definir($certificado, 'passphrase', '');

        $tools = (new ReflectionClass(Tools::class))->newInstanceWithoutConstructor();
        $this->definir($tools, 'config', Config::fromArray($config + ['cnpj' => '11222333000181', 'ambiente' => 2]));
        $this->definir($tools, 'certificate', $certificado);
        $this->definir($tools, 'logger', new Logger(sys_get_temp_dir() . '/nfse-nacional-testes'));
        $this->definir($tools, 'httpClient', new Client(['handler' => HandlerStack::create($mock)]));

        return [$tools, $mock];
    }

    private function definir(object $objeto, string $propriedade, mixed $valor): void
    {
        (new ReflectionProperty($objeto::class, $propriedade))->setValue($objeto, $valor);
    }

    private function chamar(Tools $tools, string $metodo, mixed ...$args): mixed
    {
        $m = new ReflectionMethod(Tools::class, $metodo);
        $m->setAccessible(true);

        return $m->invoke($tools, ...$args);
    }

    private function respostaComErros(int $status, array $erros): HttpResponse
    {
        $corpo = (string) json_encode(['erros' => $erros]);

        return new HttpResponse($status, ['Content-Type' => 'application/json'], $corpo);
    }

    // ---- verificação TLS --------------------------------------------------------------------

    public function testVerificacaoTlsFicaLigadaPorPadraoInclusiveEmHomologacao(): void
    {
        $this->assertTrue(Config::fromArray(['ambiente' => 2])->verificarSsl);
        $this->assertTrue(Config::fromArray(['ambiente' => 1])->verificarSsl);
        $this->assertFalse(Config::fromArray(['ambiente' => 2, 'verificarSsl' => false])->verificarSsl);
    }

    public function testRequisicoesUsamAVerificacaoTlsDaConfiguracao(): void
    {
        [$tools, $mock] = $this->criarTools([new HttpResponse(200, [], '%PDF-1.7 ok')]);
        $tools->baixarDanfse(self::CHAVE);
        $this->assertTrue($mock->getLastOptions()['verify']);

        [$tools, $mock] = $this->criarTools([new HttpResponse(200, [], '%PDF-1.7 ok')], ['verificarSsl' => false]);
        $tools->baixarDanfse(self::CHAVE);
        $this->assertFalse($mock->getLastOptions()['verify']);
    }

    // ---- erros da API -----------------------------------------------------------------------

    public function testErrosEstruturadosDaApiDeEventosViramMensagemLimpa(): void
    {
        [$tools] = $this->criarTools([
            $this->respostaComErros(400, [
                ['Codigo' => 'E1235', 'Descricao' => 'Falha no schema', 'Complemento' => 'Id'],
            ]),
        ]);

        try {
            $this->chamar($tools, 'callRestEvento', 'POST', '/nfse/' . self::CHAVE . '/eventos', '<x/>');
            $this->fail('Deveria lançar CommunicationException');
        } catch (CommunicationException $e) {
            $this->assertSame('Resposta da API: [E1235] Falha no schema - Id', $e->getMessage());
        }
    }

    public function testErroSemCodigoNaApiDeEventosTrazDetalhesTecnicos(): void
    {
        [$tools] = $this->criarTools([new HttpResponse(503, [], 'Service Unavailable')]);

        try {
            $this->chamar($tools, 'callRestEvento', 'POST', '/nfse/' . self::CHAVE . '/eventos', '<x/>');
            $this->fail('Deveria lançar CommunicationException');
        } catch (CommunicationException $e) {
            $mensagem = $e->getMessage();
            $this->assertStringContainsString('Falha na comunicação com a API de Eventos (Homologação)', $mensagem);
            $this->assertStringContainsString('HTTP Status: 503', $mensagem);
            $this->assertStringContainsString('=== XML ENVIADO (antes do GZIP) ===', $mensagem);
        }
    }

    public function testFormatacaoDosErrosComOrientacoesParaErrosComuns(): void
    {
        [$tools] = $this->criarTools([]);

        $resultado = $this->chamar($tools, 'formatarErrosApi', [
            ['Codigo' => 'E0715', 'Descricao' => 'Certificado inválido'],
            ['Codigo' => 'E0999', 'Descricao' => 'Outro', 'Complemento' => 'detalhe'],
        ], true);

        $this->assertTrue($resultado['estruturado']);
        $this->assertStringStartsWith('[E0715] Certificado inválido', $resultado['texto']);
        $this->assertStringContainsString('Erro de validação do certificado digital', $resultado['texto']);
        $this->assertStringContainsString("\n[E0999] Outro - detalhe", $resultado['texto']);
    }

    public function testErroDeSchemaRng9997GanhaExplicacaoLegivel(): void
    {
        [$tools] = $this->criarTools([]);

        $resultado = $this->chamar($tools, 'formatarErrosApi', [[
            'Codigo' => 'RNG9997',
            'Descricao' => 'Schema',
            'Complemento' => "The 'xNome' element is invalid - The value '' is invalid according to its datatype",
        ]]);

        $this->assertStringStartsWith('[RNG9997] Erro de validação do XML', $resultado['texto']);
        $this->assertStringContainsString("O campo 'Nome/Razão Social do Tomador' está vazio", $resultado['texto']);
    }

    public function testListaDeErrosSemCodigoNemDescricaoNaoEEstruturada(): void
    {
        [$tools] = $this->criarTools([]);

        $this->assertFalse($this->chamar($tools, 'formatarErrosApi', [['Outro' => 'x'], 'texto solto'])['estruturado']);
    }

    // ---- log da emissão ---------------------------------------------------------------------

    public function testNumeroDaDpsParaOLogVemDoNdps(): void
    {
        [$tools] = $this->criarTools([]);
        $xml = '<DPS xmlns="http://www.sped.fazenda.gov.br/nfse"><infDPS><nDPS>4582</nDPS></infDPS></DPS>';

        $this->assertSame('4582', $this->chamar($tools, 'extractDpsNumber', $xml));
        $this->assertSame('N/A', $this->chamar($tools, 'extractDpsNumber', ''));
        $this->assertSame('N/A', $this->chamar($tools, 'extractDpsNumber', 'não é xml'));
    }
}
