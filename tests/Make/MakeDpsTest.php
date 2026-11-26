<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Make;

use DOMDocument;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Make\Make;
use NFSeNacional\Validator\SchemaType;
use NFSeNacional\Validator\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Garante que a DPS gerada é válida contra o schema oficial DPS_v1.01.xsd (NT 009).
 */
class MakeDpsTest extends TestCase
{
    private SchemaValidator $schemas;

    protected function setUp(): void
    {
        $this->schemas = new SchemaValidator(dirname(__DIR__, 2) . '/src/Schemas');
    }

    /** @return array<string, mixed> */
    private function prestador(array $extra = []): array
    {
        return array_merge([
            'cnpj' => '11222333000181',
            'im' => '12345',
            'razaoSocial' => 'Empresa Teste LTDA',
            'opSimpNac' => 3,
            'regApTribSN' => 1,
            'regEspTrib' => 0,
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function tomador(array $extra = []): array
    {
        return array_merge([
            'cpfCnpj' => '12345678909',
            'razaoSocial' => 'Cliente Teste',
        ], $extra);
    }

    /** @return array<string, mixed> */
    private function servico(array $extra = []): array
    {
        return array_merge([
            'cTribNac' => '010701',
            'discriminacao' => 'Serviço de teste',
            'valorServicos' => 100.00,
            'issRetido' => 2,
            'codigoMunicipio' => '3550308',
        ], $extra);
    }

    private function montar(int $tpAmb = 2, array $prestador = [], array $tomador = [], array $servico = []): Make
    {
        return (new Make())
            ->setTpAmb($tpAmb)
            ->setCLocEmi('3550308')
            ->setIdentificacao(['numero' => '1', 'serie' => '1'])
            ->setPrestador($this->prestador($prestador))
            ->setTomador($this->tomador($tomador))
            ->setServico($this->servico($servico));
    }

    private function assertSchemaValido(string $xml): void
    {
        $this->schemas->validate($xml, SchemaType::DPS);
        $this->addToAssertionCount(1);
    }

    public function testDpsValidaContraSchemaOficialEmHomologacao(): void
    {
        $this->assertSchemaValido($this->montar(2)->getDPSXML());
    }

    public function testDpsValidaContraSchemaOficialEmProducao(): void
    {
        $this->assertSchemaValido($this->montar(1)->getDPSXML());
    }

    public function testRaizUsaNamespaceOficialEVersao101(): void
    {
        $xml = $this->montar()->getDPSXML();
        $dom = new DOMDocument();
        $dom->loadXML($xml);

        $this->assertSame('DPS', $dom->documentElement->localName);
        $this->assertSame(Make::NAMESPACE, $dom->documentElement->namespaceURI);
        $this->assertSame('1.01', $dom->documentElement->getAttribute('versao'));
    }

    public function testIdDpsTemQuarentaECincoCaracteresComCnpjNumerico(): void
    {
        $make = $this->montar();
        $make->getDPSXML();

        $id = $make->getInfDpsId();
        $this->assertSame(45, strlen($id));
        // DPS + cLocEmi + tipo(2=CNPJ) + CNPJ(14) + série(5) + nDPS(15)
        $this->assertSame('DPS' . '3550308' . '2' . '11222333000181' . '00001' . '000000000000001', $id);
    }

    public function testCnpjAlfanumericoEhAceitoEGeraIdComLetrasMaiusculas(): void
    {
        $make = $this->montar(2, ['cnpj' => '12.ABC.345/0001-A1']);
        $xml = $make->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<CNPJ>12ABC3450001A1</CNPJ>', $xml);
        $this->assertSame('DPS35503082' . '12ABC3450001A1' . '00001' . '000000000000001', $make->getInfDpsId());
    }

    public function testCnpjInvalidoLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->montar(2, ['cnpj' => '123']);
    }

    public function testTomadorCpfComEnderecoCompletoValida(): void
    {
        $xml = $this->montar(2, [], [
            'email' => 'cliente@exemplo.com',
            'telefone' => '(11) 98888-7777',
            'codigoMunicipio' => '3550308',
            'cep' => '01001-000',
            'endereco' => 'Rua Exemplo',
            'numero' => '100',
            'bairro' => 'Centro',
        ])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<CPF>12345678909</CPF>', $xml);
        $this->assertStringContainsString('<CEP>01001000</CEP>', $xml);
    }

    public function testEnderecoIncompletoLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->montar(2, [], ['codigoMunicipio' => '3550308', 'endereco' => 'Rua X']);
    }

    public function testServicoSemCTribNacLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->montar(2, [], [], ['cTribNac' => '']);
    }

    public function testServicoComPisCofinsEValidaContraSchema(): void
    {
        $xml = $this->montar(2, [], [], [
            'piscofins' => ['CST' => '01', 'pAliqPis' => 0.65, 'pAliqCofins' => 3.0],
        ])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<tribFed>', $xml);
    }

    public function testTotTribSnInformadoUsaPTotTribSN(): void
    {
        $xml = $this->montar()->setPTotTribSN('10.51')->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pTotTribSN>10.51</pTotTribSN>', $xml);
    }

    public function testSemTotTribSnUsaIndTotTrib(): void
    {
        $xml = $this->montar()->getDPSXML();

        $this->assertStringContainsString('<indTotTrib>0</indTotTrib>', $xml);
        $this->assertStringNotContainsString('pTotTribSN', $xml);
    }

    public function testSerieUnicaNaoEhMaisAceita(): void
    {
        // O schema só aceita série numérica (TSSerieDPS)
        $this->expectException(ValidationException::class);
        $make = (new Make())
            ->setCLocEmi('3550308')
            ->setIdentificacao(['numero' => '1', 'serie' => 'UNICA'])
            ->setPrestador($this->prestador())
            ->setTomador($this->tomador())
            ->setServico($this->servico());
        $make->getDPSXML();
    }
}
