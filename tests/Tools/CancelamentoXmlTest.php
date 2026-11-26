<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Tools;

use NFSeNacional\Config\Config;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Tools\Tools;
use NFSeNacional\Validator\SchemaType;
use NFSeNacional\Validator\SchemaValidator;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Valida o XML do pedido de registro de evento (cancelamento) contra pedRegEvento_v1.01.xsd.
 */
class CancelamentoXmlTest extends TestCase
{
    /** Chave de 50 dígitos com a estrutura cMun(7) + tipo(1) + CNPJ(14) + restante. */
    private const CHAVE = '35503082' . '11222333000181' . '0000000000000000000000000001';

    private Tools $tools;

    protected function setUp(): void
    {
        $config = Config::fromArray([
            'cnpj' => '11222333000181',
            'im' => '12345',
            'razaoSocial' => 'Empresa Teste LTDA',
            'ambiente' => 2,
        ]);

        // Tools exige certificado no construtor; aqui só geramos o XML do evento.
        $this->tools = (new ReflectionClass(Tools::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(Tools::class, 'config'))->setValue($this->tools, $config);
    }

    /** @return array{xml: string, idEvento: string} */
    private function gerar(string $chave = self::CHAVE, string $motivo = 'Serviço não prestado conforme contrato', int $cMotivo = 2): array
    {
        $metodo = new ReflectionMethod(Tools::class, 'gerarXmlEventoCancelamento');
        $metodo->setAccessible(true);

        return $metodo->invoke($this->tools, $chave, $motivo, $cMotivo);
    }

    /**
     * O pedRegEvento gerado (inclusive o Id) é válido contra o XSD oficial, sem nenhum ajuste manual.
     */
    public function testEstruturaDoCancelamentoValidaContraSchemaOficial(): void
    {
        $xml = $this->gerar()['xml'];

        $schemas = new SchemaValidator(dirname(__DIR__, 2) . '/src/Schemas');
        $schemas->validate($xml, SchemaType::PEDIDO_REGISTRO_EVENTO);
        $this->addToAssertionCount(1);
    }

    // BUG 2 — Id com 62 caracteres era rejeitado pela SEFIN (E1235, TSIdPedRegEvt)

    public function testIdDoEventoTemCinquentaENoveCaracteres(): void
    {
        $id = $this->gerar()['idEvento'];

        $this->assertSame(59, strlen($id));
        $this->assertSame('PRE' . self::CHAVE . '101101', $id);
    }

    public function testIdDoEventoNaoTemSufixoDeNPedRegEvento(): void
    {
        $id = $this->gerar()['idEvento'];

        $this->assertStringEndsWith('101101', $id);
        $this->assertStringEndsNotWith('101101001', $id);
    }

    public function testIdDoEventoSegueOPadraoDoXsd(): void
    {
        $id = $this->gerar()['idEvento'];

        // TSIdPedRegEvt: PRE[0-9]{8}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{33}
        $this->assertMatchesRegularExpression('/^PRE[0-9]{8}(1[0-9]{14}|2[0-9A-Z]{14})[0-9]{33}$/', $id);
    }

    public function testIdDoXmlGeradoEOMesmoUsadoParaAssinar(): void
    {
        $resultado = $this->gerar();

        $dom = new \DOMDocument();
        $dom->loadXML($resultado['xml']);
        $inf = $dom->getElementsByTagNameNS('http://www.sped.fazenda.gov.br/nfse', 'infPedReg')->item(0);

        $this->assertSame($resultado['idEvento'], $inf->getAttribute('Id'));
    }

    public function testSchemaRejeitaIdAntigoDeSessentaEDoisCaracteres(): void
    {
        // Documenta a causa do E1235: o Id antigo (com nPedRegEvento) não passa no XSD
        $dom = new \DOMDocument();
        $dom->loadXML($this->gerar()['xml']);
        $inf = $dom->getElementsByTagNameNS('http://www.sped.fazenda.gov.br/nfse', 'infPedReg')->item(0);
        $inf->setAttribute('Id', 'PRE' . self::CHAVE . '101101' . '001');

        $schemas = new SchemaValidator(dirname(__DIR__, 2) . '/src/Schemas');

        $this->expectException(\Throwable::class);
        $schemas->validate($dom->saveXML(), SchemaType::PEDIDO_REGISTRO_EVENTO);
    }

    public function testCodigoDeMotivoInvalidoLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->gerar(self::CHAVE, 'Serviço não prestado conforme contrato', 5);
    }

    public function testMotivoCurtoDemaisLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->gerar(self::CHAVE, 'curto');
    }

    public function testChaveComTamanhoErradoLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->gerar('123');
    }
}
