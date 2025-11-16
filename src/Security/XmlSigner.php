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
        $xpath->registerNamespace('nfse', 'http://www.abrasf.org.br/nfse.xsd');

        if ($referenceId !== null) {
            $element = $xpath->query(sprintf('//*[@Id="%s"]', $referenceId))->item(0);
        } else {
            $elements = $dom->getElementsByTagName($tagName);
            $element = $elements->item(0);
        }

        if (!$element instanceof \DOMElement) {
            throw new ValidationException(sprintf('Tag "%s" não encontrada para assinatura.', $tagName));
        }

        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);
        $options = ['id_name' => 'Id', 'overwrite' => false];

        $objDSig->addReference(
            $element,
            XMLSecurityDSig::SHA256,
            ['http://www.w3.org/2000/09/xmldsig#enveloped-signature'],
            $options
        );

        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($this->certificate->getPrivateKey());
        $objDSig->sign($key);
        $objDSig->add509Cert($this->certificate->getCertificate());
        $objDSig->appendSignature($element);

        return $dom->saveXML() ?: '';
    }
}

