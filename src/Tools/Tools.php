<?php

declare(strict_types=1);

namespace NFSeNacional\Tools;

use NFSeNacional\Common\Logger;
use NFSeNacional\Common\Response;
use NFSeNacional\Config\Config;
use NFSeNacional\Exception\CommunicationException;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Pdf\DanfseGenerator;
use NFSeNacional\Security\Certificate;
use NFSeNacional\Security\XmlSigner;
use NFSeNacional\Validator\SchemaType;
use NFSeNacional\Validator\SchemaValidator;
use SoapClient;
use Throwable;

/**
 * Classe principal para comunicação com o WebService da NFSe Nacional
 */
class Tools
{
    private Certificate $certificate;
    private XmlSigner $signer;
    private SchemaValidator $schemaValidator;
    private Logger $logger;

    public function __construct(
        private readonly Config $config,
        ?string $logDir = null
    ) {
        $this->certificate = Certificate::fromPfx($config->certPfx, $config->certPassword);
        $this->signer = new XmlSigner($this->certificate);
        $this->schemaValidator = new SchemaValidator(dirname(__DIR__) . '/Schemas');
        $this->logger = new Logger($logDir);
    }
    
    /**
     * Envia uma DPS individual (SÍNCRONO)
     */
    public function enviarDps(string $xml): Response
    {
        try {
            $this->validateXml($xml, SchemaType::DPS);
            $signedXml = $this->signer->sign($xml);
            $soapResult = $this->callSoap('RecepcionarDps', $signedXml);
            $response = $this->buildResponse($soapResult, 'RecepcionarDps');
            
            // Extrai número da DPS do XML para log
            $numeroDps = $this->extractDpsNumber($xml);
            
            $this->logger->logEnvioDps(
                $numeroDps,
                $response->sucesso,
                $response->numero,
                $response->sucesso ? null : $response->mensagem
            );
            
            return $response;
        } catch (Throwable $e) {
            $numeroDps = $this->extractDpsNumber($xml);
            $this->logger->logEnvioDps($numeroDps, false, null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Envia lote de DPS (ASSÍNCRONO)
     */
    public function enviarLoteDps(array $listaDps, string $numeroLote): Response
    {
        try {
            if (empty($listaDps)) {
                throw new ValidationException('Lista de DPS vazia');
            }
            
            if (count($listaDps) > 50) {
                throw new ValidationException('Limite máximo de 50 DPS por lote excedido');
            }
            
            $assinadas = [];
            foreach ($listaDps as $dpsXml) {
                $this->validateXml($dpsXml, SchemaType::DPS);
                $assinadas[] = $this->signer->sign($dpsXml);
            }
            
            $xmlLote = $this->gerarXmlLote($assinadas, $numeroLote);
            $this->validateXml($xmlLote, SchemaType::LOTE);
            $signedLote = $this->signer->sign($xmlLote, 'LoteRps');
            $soapResult = $this->callSoap('RecepcionarLoteRps', $signedLote);
            $response = $this->buildResponse($soapResult, 'RecepcionarLoteRps');
            
            $protocolo = $response->dados['parsed']['Protocolo'] ?? null;
            
            $this->logger->logEnvioLote(
                $numeroLote,
                count($listaDps),
                $response->sucesso,
                $protocolo ? (string) $protocolo : null,
                $response->sucesso ? null : $response->mensagem
            );
            
            return $response;
        } catch (Throwable $e) {
            $this->logger->logEnvioLote($numeroLote, count($listaDps), false, null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Consulta resultado de um lote pelo protocolo (ASSÍNCRONO)
     */
    public function consultarLote(string $protocolo): Response
    {
        try {
            $xml = $this->gerarXmlConsultaLote($protocolo);
            $soapResult = $this->callSoap('ConsultarLoteRps', $xml);
            $response = $this->buildResponse($soapResult, 'ConsultarLoteRps');
            
            $situacao = $response->dados['parsed']['Situacao'] ?? null;
            
            $this->logger->logConsultaLote(
                $protocolo,
                $response->sucesso,
                $situacao ? (string) $situacao : null,
                $response->sucesso ? null : $response->mensagem
            );
            
            return $response;
        } catch (Throwable $e) {
            $this->logger->logConsultaLote($protocolo, false, null, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Consulta NFSe individual por número (SÍNCRONO)
     */
    public function consultarNfse(string $numero): Response
    {
        try {
            $xml = $this->gerarXmlConsultaNfse($numero);
            $soapResult = $this->callSoap('ConsultarNfse', $xml);
            $response = $this->buildResponse($soapResult, 'ConsultarNfse');
            
            $this->logger->log('ConsultarNfse', "Consulta realizada para NFSe: {$numero}", [
                'numero' => $numero,
                'sucesso' => $response->sucesso
            ]);
            
            return $response;
        } catch (Throwable $e) {
            $this->logger->log('ConsultarNfse', "Falha ao consultar NFSe: {$numero}", [
                'numero' => $numero,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    /**
     * Cancela NFSe individual (SÍNCRONO)
     */
    public function cancelarNfse(string $numero, string $motivo): Response
    {
        try {
            $xml = $this->gerarXmlCancelamento($numero, $motivo);
            $soapResult = $this->callSoap('CancelarNfse', $xml);
            $response = $this->buildResponse($soapResult, 'CancelarNfse');
            
            $this->logger->logCancelamento(
                $numero,
                $response->sucesso,
                $response->sucesso ? null : $response->mensagem
            );
            
            return $response;
        } catch (Throwable $e) {
            $this->logger->logCancelamento($numero, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Gera PDF do DANFSe a partir do Response da NFSe
     * 
     * @param Response $response Response da NFSe emitida/consultada
     * @param array $dadosAdicionais Dados adicionais para o PDF (ex: prestador.razaoSocial)
     * @return string Conteúdo do PDF em binário
     */
    public function gerarDanfse(Response $response, array $dadosAdicionais = []): string
    {
        $generator = new DanfseGenerator();
        return $generator->gerar($response, $dadosAdicionais);
    }

    /**
     * Gera PDF do DANFSe e salva em arquivo
     * 
     * @param Response $response Response da NFSe emitida/consultada
     * @param string $caminho Caminho completo do arquivo PDF
     * @param array $dadosAdicionais Dados adicionais para o PDF
     */
    public function gerarDanfseArquivo(Response $response, string $caminho, array $dadosAdicionais = []): void
    {
        $generator = new DanfseGenerator();
        $generator->gerarArquivo($response, $caminho, $dadosAdicionais);
    }

    /**
     * Gera PDF do DANFSe e retorna como base64
     * 
     * @param Response $response Response da NFSe emitida/consultada
     * @param array $dadosAdicionais Dados adicionais para o PDF
     * @return string PDF em base64
     */
    public function gerarDanfseBase64(Response $response, array $dadosAdicionais = []): string
    {
        $generator = new DanfseGenerator();
        return $generator->gerarBase64($response, $dadosAdicionais);
    }
    
    private function validateXml(string $xml, SchemaType $schemaType): void
    {
        if (!$this->config->validarSchemas) {
            return;
        }

        try {
            $this->schemaValidator->validate($xml, $schemaType);
            $this->logger->logValidacao($schemaType->value, true);
        } catch (Throwable $e) {
            $this->logger->logValidacao($schemaType->value, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extrai número da DPS do XML
     */
    private function extractDpsNumber(string $xml): string
    {
        try {
            $simpleXml = @simplexml_load_string($xml);
            if ($simpleXml !== false) {
                $numeroNode = $simpleXml->xpath('//Numero');
                if (!empty($numeroNode)) {
                    return (string) $numeroNode[0];
                }
            }
        } catch (Throwable) {
            // Ignora erros de parsing
        }
        
        return 'N/A';
    }

    private function callSoap(string $operation, string $xml): string
    {
        $wsdl = $this->config->getWsdl($operation);

        try {
            $client = new SoapClient($wsdl, [
                'trace' => true,
                'soap_version' => SOAP_1_2,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_DISK,
                'local_cert' => $this->certificate->getPemPath(),
                'passphrase' => $this->certificate->getPassphrase(),
            ]);

            $params = ['nfseDadosMsg' => $xml];
            $response = $client->__soapCall($operation, [$params]);

            return $this->extractXmlFromSoapResponse($response);
        } catch (Throwable $e) {
            throw new CommunicationException(
                sprintf('Falha na operação %s (%s): %s', $operation, $this->config->ambienteNome(), $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    private function extractXmlFromSoapResponse(mixed $response): string
    {
        if (is_string($response) && str_contains($response, '<')) {
            return $response;
        }

        if (is_object($response)) {
            $vars = get_object_vars($response);
            foreach ($vars as $value) {
                if (is_string($value) && str_contains($value, '<')) {
                    return $value;
                }
            }
        }

        throw new CommunicationException('Resposta SOAP não contém payload XML esperado.');
    }

    private function buildResponse(string $xml, string $contexto): Response
    {
        $dados = [
            'contexto' => $contexto,
            'xml' => $xml,
        ];

        $simpleXml = @simplexml_load_string($xml);
        if ($simpleXml !== false) {
            $dados['parsed'] = json_decode(json_encode($simpleXml), true);
            $numeroNode = $simpleXml->xpath('//NumeroNfse');
            $codigoNode = $simpleXml->xpath('//CodigoVerificacao');
            $numero = $numeroNode[0] ?? null;
            $codigo = $codigoNode[0] ?? null;

            return new Response(
                sucesso: true,
                mensagem: 'Operação concluída com sucesso',
                dados: $dados,
                numero: $numero ? (string) $numero : null,
                codigoVerificacao: $codigo ? (string) $codigo : null,
                xml: $xml
            );
        }

        return new Response(
            sucesso: true,
            mensagem: 'Operação concluída. Veja XML em dados.xml',
            dados: $dados,
            xml: $xml
        );
    }
    
    /**
     * Gera XML do lote de DPS
     */
    private function gerarXmlLote(array $listaDps, string $numeroLote): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="' . $numeroLote . '" versao="1.00">';
        $xml .= '<NumeroLote>' . $numeroLote . '</NumeroLote>';
        $xml .= '<CpfCnpj><Cnpj>' . $this->config->cnpj . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $this->config->im . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . count($listaDps) . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        
        foreach ($listaDps as $dps) {
            $xml .= '<Rps>';
            $xml .= str_replace(
                ['<?xml version="1.0" encoding="UTF-8"?>', '<EnviarDpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">', '</EnviarDpsEnvio>', '<Dps>', '</Dps>'],
                '',
                $dps
            );
            $xml .= '</Rps>';
        }
        
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        
        return $xml;
    }

    private function gerarXmlConsultaLote(string $protocolo): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ConsultarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<Prestador>'
            . '<CpfCnpj><Cnpj>' . $this->config->cnpj . '</Cnpj></CpfCnpj>'
            . '<InscricaoMunicipal>' . $this->config->im . '</InscricaoMunicipal>'
            . '</Prestador>'
            . '<Protocolo>' . $protocolo . '</Protocolo>'
            . '</ConsultarLoteRpsEnvio>';
    }

    private function gerarXmlConsultaNfse(string $numero): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<ConsultarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<Prestador>'
            . '<CpfCnpj><Cnpj>' . $this->config->cnpj . '</Cnpj></CpfCnpj>'
            . '<InscricaoMunicipal>' . $this->config->im . '</InscricaoMunicipal>'
            . '</Prestador>'
            . '<NumeroNfse>' . $numero . '</NumeroNfse>'
            . '</ConsultarNfseEnvio>';
    }

    private function gerarXmlCancelamento(string $numero, string $motivo): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<CancelarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">'
            . '<Pedido>'
            . '<InfPedidoCancelamento Id="Canc' . $numero . '">'
            . '<IdentificacaoNfse>'
            . '<Numero>' . $numero . '</Numero>'
            . '<CpfCnpj><Cnpj>' . $this->config->cnpj . '</Cnpj></CpfCnpj>'
            . '<InscricaoMunicipal>' . $this->config->im . '</InscricaoMunicipal>'
            . '<CodigoMunicipio>' . $this->config->cmun . '</CodigoMunicipio>'
            . '</IdentificacaoNfse>'
            . '<CodigoCancelamento>2</CodigoCancelamento>'
            . '<Motivo>' . htmlspecialchars($motivo, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Motivo>'
            . '</InfPedidoCancelamento>'
            . '</Pedido>'
            . '</CancelarNfseEnvio>';
    }
}
