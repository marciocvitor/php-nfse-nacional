<?php

declare(strict_types=1);

namespace NFSeNacional\Security;

use DOMDocument;
use NFSeNacional\Exception\ValidationException;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

class XmlSigner
{
    public function __construct(private readonly Certificate $certificate)
    {
    }

    public function sign(string $xml, string $tagName = 'InfDps', ?string $referenceId = null): string
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);
        // Registrar namespaces comuns
        $xpath->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');
        $xpath->registerNamespace('sped', 'http://www.sped.fazenda.gov.br/nfse');

        // Buscar o elemento para referenciar na assinatura
        $elementToSign = null;
        if ($referenceId !== null) {
            // Buscar por ID em qualquer namespace (tentar tanto Id maiúsculo quanto id minúsculo)
            $elementToSign = $xpath->query(sprintf('//*[@Id="%s"]', $referenceId))->item(0);
            if (!$elementToSign instanceof \DOMElement) {
                $elementToSign = $xpath->query(sprintf('//*[@id="%s"]', $referenceId))->item(0);
            }
        } else {
            // Estratégia: buscar por ID primeiro (mais confiável), depois por nome da tag
            // Buscar por qualquer elemento com atributo Id/id que comece com "DPS", "Evt", "ID", "PRE" ou números (para eventos NFS-e)
            $elementToSign = $xpath->query('//*[starts-with(@Id, "DPS") or starts-with(@Id, "Evt") or starts-with(@Id, "ID") or starts-with(@Id, "PRE") or string-length(@Id) >= 50]')->item(0);
            if (!$elementToSign instanceof \DOMElement) {
                $elementToSign = $xpath->query('//*[starts-with(@id, "DPS") or starts-with(@id, "Evt") or starts-with(@id, "ID") or starts-with(@id, "PRE") or string-length(@id) >= 50]')->item(0);
            }

            // Se não encontrar por ID, tentar por nome da tag usando XPath (funciona com namespaces)
            if (!$elementToSign instanceof \DOMElement) {
                // Buscar infPedReg no namespace SPED (para eventos de cancelamento)
                $elementToSign = $xpath->query('//sped:infPedReg')->item(0);
            }

            if (!$elementToSign instanceof \DOMElement) {
                // Buscar infPedReg sem namespace (qualquer namespace)
                $elementToSign = $xpath->query('//*[local-name()="infPedReg"]')->item(0);
            }

            if (!$elementToSign instanceof \DOMElement) {
                // Buscar infEvento no namespace SPED (fallback para outros tipos de evento)
                $elementToSign = $xpath->query('//sped:infEvento')->item(0);
            }

            if (!$elementToSign instanceof \DOMElement) {
                // Buscar infEvento sem namespace (qualquer namespace)
                $elementToSign = $xpath->query('//*[local-name()="infEvento"]')->item(0);
            }

            // Se não encontrar, tentar por nome da tag sem namespace (fallback)
            if (!$elementToSign instanceof \DOMElement) {
                // Tenta o nome exato (pode não funcionar com namespace)
                $elements = $dom->getElementsByTagName($tagName);
                $elementToSign = $elements->item(0);
            }

            // Se não encontrar, tenta variações comuns
            if (!$elementToSign instanceof \DOMElement) {
                // Tenta infDPS (formato SPED) usando XPath
                $elementToSign = $xpath->query('//sped:infDPS')->item(0);
            }

            if (!$elementToSign instanceof \DOMElement) {
                // Tenta infDPS sem namespace
                $elementToSign = $xpath->query('//*[local-name()="infDPS"]')->item(0);
            }

            if (!$elementToSign instanceof \DOMElement) {
                // Tenta infDps (camelCase) sem namespace
                $elementToSign = $xpath->query('//*[local-name()="infDps"]')->item(0);
            }

            // Última tentativa: buscar qualquer elemento com Id que contenha "DPS" ou "Evt"
            if (!$elementToSign instanceof \DOMElement) {
                $elementToSign = $xpath->query('//*[contains(@Id, "DPS") or contains(@Id, "Evt")]')->item(0);
            }
        }

        if (!$elementToSign instanceof \DOMElement) {
            throw new ValidationException(sprintf('Tag "%s" (ou variações infDPS/infDps/infEvento) não encontrada para assinatura.', $tagName));
        }

        // Buscar o elemento raiz (pode ser NFSe ou DPS)
        $rootElement = $dom->documentElement;
        if (!$rootElement) {
            throw new ValidationException('Elemento raiz não encontrado no XML para assinatura.');
        }

        // Se o raiz for DPS, a assinatura vai após </infDPS>
        // Se o raiz for NFSe, a assinatura vai após </infNFSe>
        // O appendSignature já trata isso corretamente

        $objDSig = new XMLSecurityDSig('');
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $options = ['id_name' => 'Id', 'overwrite' => false];

        $objDSig->addReference(
            $elementToSign,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            $options
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($this->certificate->getPrivateKey());
        $objDSig->sign($key);

        // Apenas o certificado do emitente (e-CNPJ/e-CPF) no KeyInfo.
        // PFX com cadeia completa no PKCS#12 gerava vários X509Certificate na assinatura
        // e rejeição E0714 no Portal Nacional; emissões que funcionam usam só o leaf.
        $objDSig->add509Cert($this->certificate->getCertificate());

        // Adicionar a assinatura no elemento raiz (NFSe) em vez de dentro do infDPS
        // Isso fará com que a assinatura apareça após </infNFSe>
        $objDSig->appendSignature($rootElement);

        return $dom->saveXML() ?: '';
    }
}
