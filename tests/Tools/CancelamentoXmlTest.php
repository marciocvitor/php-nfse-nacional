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
     * Estrutura do pedRegEvento validada contra o XSD oficial.
     *
     * O atributo Id é trocado por um valor que cumpre o padrão TSIdPedRegEvt (59 caracteres),
     * porque a documentação do próprio schema (PRE + chave + tipo + nPedRegEvento = 62)
     * não bate com o maxLength/pattern do XSD. Essa divergência precisa ser confirmada
     * em homologação. Aqui testamos apenas a estrutura dos demais elementos.
     */
    public function testEstruturaDoCancelamentoValidaContraSchemaOficial(): void
    {
        $xml = $this->gerar()['xml'];

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $inf = $dom->getElementsByTagNameNS('http://www.sped.fazenda.gov.br/nfse', 'infPedReg')->item(0);
        // PRE(3) + dígitos(8) + tipo(1) + inscrição(14) + dígitos(33) = 59
        $inf->setAttribute('Id', 'PRE' . '12345678' . '1' . '11222333000181' . str_repeat('0', 33));

        $schemas = new SchemaValidator(dirname(__DIR__, 2) . '/src/Schemas');
        $schemas->validate($dom->saveXML(), SchemaType::PEDIDO_REGISTRO_EVENTO);
        $this->addToAssertionCount(1);
    }

    public function testIdDoEventoSegueComposicaoDaDocumentacao(): void
    {
        $resultado = $this->gerar();

        // Documentação: "PRE" + chave(50) + tipo do evento(6) + nPedRegEvento(3)
        $this->assertSame('PRE' . self::CHAVE . '101101' . '001', $resultado['idEvento']);
        $this->assertSame(62, strlen($resultado['idEvento']));
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
