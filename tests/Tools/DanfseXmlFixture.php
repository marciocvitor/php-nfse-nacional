<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Tools;

/**
 * XML de NFS-e autorizada com a estrutura real devolvida pela SEFIN Nacional:
 * a DPS vem aninhada em NFSe/infNFSe/DPS/infDPS (não como irmã de infNFSe).
 * Dados fictícios.
 */
final class DanfseXmlFixture
{
    public const NFSE = <<<XML
<NFSe xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
  <infNFSe Id="NFS35503082111222333000181000000000000000000000001">
    <xLocEmi>Sao Paulo</xLocEmi>
    <xLocPrestacao>Sao Paulo</xLocPrestacao>
    <nNFSe>3275</nNFSe>
    <cLocIncid>3550308</cLocIncid>
    <xLocIncid>Sao Paulo</xLocIncid>
    <xTribNac>Suporte tecnico em informatica</xTribNac>
    <verAplic>SefinNacional_1.6.0</verAplic>
    <ambGer>2</ambGer>
    <tpEmis>1</tpEmis>
    <procEmi>1</procEmi>
    <cStat>100</cStat>
    <dhProc>2026-07-31T19:36:33-03:00</dhProc>
    <nDFSe>1234567</nDFSe>
    <emit>
      <CNPJ>11222333000181</CNPJ>
      <xNome>Empresa Teste LTDA</xNome>
    </emit>
    <valores>
      <vBC>405.80</vBC>
      <pAliqAplic>5.00</pAliqAplic>
      <vISSQN>20.29</vISSQN>
      <vLiq>405.80</vLiq>
    </valores>
    <DPS xmlns="http://www.sped.fazenda.gov.br/nfse" versao="1.01">
      <infDPS Id="DPS355030821122233300018100001000000000000003275">
        <tpAmb>2</tpAmb>
        <dhEmi>2026-07-31T19:36:00-03:00</dhEmi>
        <verAplic>NFSe-Nacional</verAplic>
        <serie>1</serie>
        <nDPS>3275</nDPS>
        <dCompet>2026-07-31</dCompet>
        <tpEmit>1</tpEmit>
        <cLocEmi>3550308</cLocEmi>
        <prest>
          <CNPJ>11222333000181</CNPJ>
          <IM>12345</IM>
          <regTrib>
            <opSimpNac>3</opSimpNac>
            <regApTribSN>1</regApTribSN>
            <regEspTrib>0</regEspTrib>
          </regTrib>
        </prest>
        <toma>
          <CPF>12345678909</CPF>
          <xNome>Cliente Teste</xNome>
        </toma>
        <serv>
          <locPrest><cLocPrestacao>3550308</cLocPrestacao></locPrest>
          <cServ>
            <cTribNac>010701</cTribNac>
            <xDescServ>Suporte tecnico em informatica</xDescServ>
          </cServ>
        </serv>
        <valores>
          <vServPrest><vServ>405.80</vServ></vServPrest>
          <trib>
            <tribMun>
              <tribISSQN>1</tribISSQN>
              <tpRetISSQN>1</tpRetISSQN>
              <pAliq>5.00</pAliq>
            </tribMun>
            <totTrib><indTotTrib>0</indTotTrib></totTrib>
          </trib>
        </valores>
      </infDPS>
    </DPS>
  </infNFSe>
</NFSe>
XML;
}
