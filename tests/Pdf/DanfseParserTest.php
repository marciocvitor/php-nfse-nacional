<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

use NFSeNacional\Pdf\DanfseGeneratorNacional;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Garante que o DANFSe lê do XML autorizado os campos de ISSQN e do emitente.
 * Usa instância sem construtor para não depender do mPDF.
 */
class DanfseParserTest extends TestCase
{
    private const XML = <<<XML
<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
  <infNFSe Id="NFS35503082111222333000181000000000000000000000001">
    <xLocEmi>Itapoa</xLocEmi>
    <xLocPrestacao>Itapoa</xLocPrestacao>
    <nNFSe>4660</nNFSe>
    <xTribNac>Servico de teste</xTribNac>
    <emit>
      <CNPJ>11222333000181</CNPJ>
      <xNome>Empresa Teste LTDA</xNome>
    </emit>
    <valores>
      <vLiq>363.00</vLiq>
      <vBC>363.00</vBC>
      <pAliqAplic>5.00</pAliqAplic>
      <vISSQN>18.15</vISSQN>
    </valores>
  </infNFSe>
  <DPS versao="1.01">
    <infDPS Id="DPS355030821122233300018100001000000000000001">
      <tpAmb>2</tpAmb>
      <dhEmi>2026-11-26T18:25:58-03:00</dhEmi>
      <serie>1</serie>
      <nDPS>4660</nDPS>
      <dCompet>2026-11-26</dCompet>
      <prest>
        <CNPJ>11222333000181</CNPJ>
        <IM>12345</IM>
        <xNome>Empresa Teste LTDA</xNome>
      </prest>
      <toma>
        <CPF>12345678909</CPF>
        <xNome>Cliente Teste</xNome>
      </toma>
      <serv>
        <locPrest><cLocPrestacao>3106200</cLocPrestacao></locPrest>
        <cServ><cTribNac>010701</cTribNac><xDescServ>Servico de teste</xDescServ></cServ>
      </serv>
      <valores>
        <vServPrest><vServ>363.00</vServ></vServPrest>
        <trib>
          <tribMun>
            <tribISSQN>1</tribISSQN>
            <tpRetISSQN>2</tpRetISSQN>
            <pAliq>5.00</pAliq>
          </tribMun>
        </trib>
      </valores>
    </infDPS>
  </DPS>
</NFSe>
XML;

    private DanfseGeneratorNacional $gerador;

    protected function setUp(): void
    {
        $ref = new ReflectionClass(DanfseGeneratorNacional::class);
        $this->gerador = $ref->newInstanceWithoutConstructor();
    }

    /** @return array<string, mixed> */
    private function extrair(string $xml): array
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('extrairDados');
        $m->setAccessible(true);

        return $m->invoke($this->gerador, $xml);
    }

    private function renderTributacaoMunicipal(array $dados): string
    {
        $m = (new ReflectionClass(DanfseGeneratorNacional::class))->getMethod('renderizarTributacaoMunicipal');
        $m->setAccessible(true);

        return $m->invoke($this->gerador, $dados);
    }

    public function testLeValoresDeIssqnDoXmlAutorizado(): void
    {
        $dados = $this->extrair(self::XML);
        $tribMun = $dados['tributacao']['tribMun'];

        $this->assertSame(5.0, $tribMun['aliquota']);
        $this->assertSame(363.0, $tribMun['bcIssqn']);
        $this->assertSame(18.15, $tribMun['issApurado']);
        $this->assertSame(363.0, $dados['valorTotal']);
    }

    public function testAliquotaUsaPAliqDaDpsQuandoNaoHaPAliqAplic(): void
    {
        $xml = str_replace('<pAliqAplic>5.00</pAliqAplic>', '', self::XML);
        $dados = $this->extrair($xml);

        $this->assertSame(5.0, $dados['tributacao']['tribMun']['aliquota']);
    }

    public function testImDoEmitenteUsaIMDaDpsQuandoEmitNaoTem(): void
    {
        // IM é opcional em infNFSe/emit; o XML tem IM apenas na DPS (prest)
        $dados = $this->extrair(self::XML);

        $this->assertSame('12345', $dados['prestador']['im']);
    }

    public function testRetencaoIssqnSimQuandoRetidoPeloTomador(): void
    {
        // tpRetISSQN = 2 (retido pelo tomador) deve aparecer como "Sim"
        $html = $this->renderTributacaoMunicipal($this->extrair(self::XML));

        $this->assertStringContainsString('5,00%', $html);
        $this->assertStringContainsString('18,15', $html);
        $this->assertMatchesRegularExpression('/<td class="text-center">Sim<\/td>/', $html);
    }

    public function testRetencaoIssqnNaoQuandoNaoRetido(): void
    {
        $xml = str_replace('<tpRetISSQN>2</tpRetISSQN>', '<tpRetISSQN>1</tpRetISSQN>', self::XML);
        $html = $this->renderTributacaoMunicipal($this->extrair($xml));

        $this->assertMatchesRegularExpression('/<td class="text-center">Não<\/td>/', $html);
    }
}
