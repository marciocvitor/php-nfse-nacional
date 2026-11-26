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

    public function testAliquotaIssEmitePAliqValidaContraSchema(): void
    {
        $xml = $this->montar()->setServico(['aliquota' => 5.0])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pAliq>5.00</pAliq>', $xml);
    }

    public function testAliquotaComQuatroCasasEhNormalizadaParaDuas(): void
    {
        // O ERP envia a alíquota com number_format(…, 4): "5.0000"
        $xml = $this->montar()->setServico(['aliquota' => '5.0000'])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pAliq>5.00</pAliq>', $xml);
    }

    public function testPAliqPodeSerInformadoPeloNomeDoXsd(): void
    {
        $xml = $this->montar()->setServico(['pAliq' => 3.5])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pAliq>3.50</pAliq>', $xml);
    }

    public function testSemAliquotaNaoEmitePAliq(): void
    {
        $xml = $this->montar()->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringNotContainsString('pAliq', $xml);
    }

    public function testAliquotaAcimaDeNoveVirgulaNoveNoveLancaErro(): void
    {
        // TSDec1V2 aceita no máximo 9,99
        $this->expectException(ValidationException::class);
        $this->montar()->setServico(['aliquota' => 10]);
    }

    public function testAliquotaNegativaOuNaoNumericaLancaErro(): void
    {
        $this->expectException(ValidationException::class);
        $this->montar()->setServico(['aliquota' => 'abc']);
    }

    public function testNovaAliquotaSubstituiAAnterior(): void
    {
        $make = $this->montar()->setServico(['aliquota' => 5.0]);
        $make->setServico(['aliquota' => 2.5]);
        $xml = $make->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pAliq>2.50</pAliq>', $xml);
        $this->assertStringNotContainsString('<pAliq>5.00</pAliq>', $xml);
    }

    public function testPAliqSubstituiAliquotaAnteriorPeloNomeDoXsd(): void
    {
        $make = $this->montar()->setServico(['aliquota' => 5.0]);
        $make->setServico(['pAliq' => 3.0]);
        $xml = $make->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringContainsString('<pAliq>3.00</pAliq>', $xml);
    }

    public function testChamadaSemAliquotaMantemAAliquotaAnterior(): void
    {
        $make = $this->montar()->setServico(['aliquota' => 4.0]);
        $make->setServico(['valorServicos' => 250.0]);
        $xml = $make->getDPSXML();

        $this->assertStringContainsString('<pAliq>4.00</pAliq>', $xml);
    }

    /** @return array<string, mixed> prestador completo, com endereço, telefone e e-mail */
    private function prestadorComEndereco(): array
    {
        return $this->prestador([
            'codigoMunicipio' => '3550308',
            'cep' => '01001-000',
            'endereco' => 'Rua do Prestador',
            'numero' => '10',
            'bairro' => 'Centro',
            'telefone' => '(11) 3333-4444',
            'email' => 'prestador@exemplo.com',
        ]);
    }

    private function prestadorElement(string $xml): \DOMElement
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $prest = $dom->getElementsByTagNameNS(Make::NAMESPACE, 'prest')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $prest);

        return $prest;
    }

    private function filhos(\DOMElement $pai, string $nome): int
    {
        return $pai->getElementsByTagNameNS(Make::NAMESPACE, $nome)->length;
    }

    // BUG 1 — tpEmit = 1: SEFIN rejeita xNome (E0121) e end (E0128) do prestador

    public function testTpEmitPrestadorNaoEmiteNomeNemEnderecoDoPrestador(): void
    {
        $xml = $this->montar(2, $this->prestadorComEndereco())->setTpEmit(1)->getDPSXML();
        $prest = $this->prestadorElement($xml);

        $this->assertSame(0, $this->filhos($prest, 'xNome'), 'xNome do prestador não pode ir na DPS (E0121)');
        $this->assertSame(0, $this->filhos($prest, 'end'), 'end do prestador não pode ir na DPS (E0128)');
        $this->assertStringNotContainsString('Empresa Teste LTDA', $prest->ownerDocument->saveXML($prest));
        $this->assertSchemaValido($xml);
    }

    public function testTpEmitPrestadorMantemTelefoneEEmailDoPrestador(): void
    {
        $xml = $this->montar(2, $this->prestadorComEndereco())->setTpEmit(1)->getDPSXML();
        $prest = $this->prestadorElement($xml);

        $this->assertSame(1, $this->filhos($prest, 'fone'));
        $this->assertSame(1, $this->filhos($prest, 'email'));
        $this->assertStringContainsString('<fone>1133334444</fone>', $xml);
        $this->assertStringContainsString('<email>prestador@exemplo.com</email>', $xml);
    }

    public function testTpEmitPrestadorNaoExigeRazaoSocialParaMontarOXml(): void
    {
        $prestador = $this->prestador();
        unset($prestador['razaoSocial']);

        $xml = (new Make())
            ->setTpAmb(2)
            ->setTpEmit(1)
            ->setCLocEmi('3550308')
            ->setIdentificacao(['numero' => '1', 'serie' => '1'])
            ->setPrestador($prestador)
            ->setTomador($this->tomador())
            ->setServico($this->servico())
            ->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertSame(0, $this->filhos($this->prestadorElement($xml), 'xNome'));
    }

    public function testTpEmitTomadorContinuaEmitindoNomeEEnderecoDoPrestador(): void
    {
        $xml = $this->montar(2, $this->prestadorComEndereco())->setTpEmit(2)->getDPSXML();
        $prest = $this->prestadorElement($xml);

        $this->assertSame(1, $this->filhos($prest, 'xNome'));
        $this->assertSame(1, $this->filhos($prest, 'end'));
        $this->assertStringContainsString('<xNome>Empresa Teste LTDA</xNome>', $prest->ownerDocument->saveXML($prest));
        $this->assertSchemaValido($xml);
    }

    public function testRazaoSocialDoPrestadorContinuaObrigatoriaQuandoOEmitenteNaoEOPrestador(): void
    {
        $prestador = $this->prestador();
        unset($prestador['razaoSocial']);

        $make = (new Make())
            ->setTpAmb(2)
            ->setTpEmit(2)
            ->setCLocEmi('3550308')
            ->setIdentificacao(['numero' => '1', 'serie' => '1'])
            ->setPrestador($prestador)
            ->setTomador($this->tomador())
            ->setServico($this->servico());

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Razão social do prestador');
        $make->getDPSXML();
    }

    public function testOrdemDeChamadaDeSetTpEmitNaoImporta(): void
    {
        // setTpEmit depois de setPrestador/setServico deve produzir o mesmo XML
        $make = (new Make())
            ->setTpAmb(2)
            ->setCLocEmi('3550308')
            ->setIdentificacao(['numero' => '1', 'serie' => '1'])
            ->setPrestador($this->prestadorComEndereco())
            ->setTomador($this->tomador())
            ->setServico($this->servico())
            ->setTpEmit(1);

        $this->assertSame(0, $this->filhos($this->prestadorElement($make->getDPSXML()), 'end'));
    }

    // BUG 3 — pAliq zero: SEFIN rejeita com E0625 quando não há retenção do ISSQN

    /** @return array<string, array{0: mixed}> */
    public static function aliquotasZero(): array
    {
        return [
            'inteiro zero' => [0],
            'float zero' => [0.0],
            'string 0' => ['0'],
            'string 0.00' => ['0.00'],
            'string 0.0000 (formato do ERP)' => ['0.0000'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('aliquotasZero')]
    public function testAliquotaZeroNaoEmitePAliq(mixed $aliquota): void
    {
        $xml = $this->montar(2, ['opSimpNac' => 3, 'regApTribSN' => 1], [], [
            'aliquota' => $aliquota,
            'issRetido' => 2,
        ])->getDPSXML();

        $this->assertStringNotContainsString('pAliq>', $xml);
        $this->assertStringContainsString('<tpRetISSQN>1</tpRetISSQN>', $xml);
        $this->assertSchemaValido($xml);
    }

    public function testPAliqZeroPeloNomeDoXsdTambemNaoEEmitido(): void
    {
        $xml = $this->montar()->setServico(['pAliq' => '0.00'])->getDPSXML();

        $this->assertStringNotContainsString('pAliq>', $xml);
    }

    public function testAliquotaPositivaPequenaAindaEEmitida(): void
    {
        $xml = $this->montar()->setServico(['aliquota' => '0.0100'])->getDPSXML();

        $this->assertStringContainsString('<pAliq>0.01</pAliq>', $xml);
    }

    public function testAliquotaZeroSubstituiAliquotaAnteriorEOmiteOGrupo(): void
    {
        $make = $this->montar()->setServico(['aliquota' => 5.0]);
        $make->setServico(['aliquota' => 0]);

        $this->assertStringNotContainsString('pAliq>', $make->getDPSXML());
    }

    public function testChavesDesconhecidasDoErpSaoIgnoradasSemErro(): void
    {
        // O ERP ainda envia estas chaves; a v2 não pode falhar por causa delas
        $xml = $this->montar()->setServico([
            'itemListaServico' => '01.07',
            'valorIss' => 50.0,
            'natOp' => 1,
        ])->getDPSXML();

        $this->assertSchemaValido($xml);
        $this->assertStringNotContainsString('valorIss', $xml);
        $this->assertStringNotContainsString('natOp', $xml);
        $this->assertStringNotContainsString('itemListaServico', $xml);
    }
}
