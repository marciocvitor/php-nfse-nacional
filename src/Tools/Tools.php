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
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Throwable;

/**
 * Classe principal para comunicação com a API REST do Portal Nacional de NFSe
 * 
 * O Portal Nacional usa APIs REST (não SOAP/WSDL)
 * Documentação: https://www.nfse.gov.br/swagger/contribuintesissqn/
 */
class Tools
{
    private Certificate $certificate;
    private XmlSigner $signer;
    private SchemaValidator $schemaValidator;
    private Logger $logger;
    private Client $httpClient;

    public function __construct(
        private readonly Config $config,
        ?string $logDir = null
    ) {
        $this->certificate = Certificate::fromPfx($config->certPfx, $config->certPassword);
        $this->signer = new XmlSigner($this->certificate);
        $this->schemaValidator = new SchemaValidator(dirname(__DIR__) . '/Schemas');
        $this->logger = new Logger($logDir);
        
        // Configura cliente HTTP REST (Guzzle)
        // Nota: O Portal Nacional pode exigir autenticação adicional (token, etc.)
        // Consulte a documentação oficial: https://www.nfse.gov.br/swagger/contribuintesissqn/
        $this->httpClient = new Client([
            'base_uri' => $this->config->getApiBaseUrl(),
            'timeout' => 60,
            'verify' => $this->config->ambiente === 1, // Verifica SSL apenas em produção
            // Certificado será usado via stream context se necessário
            'headers' => [
                'Content-Type' => 'application/xml',
                'Accept' => 'application/xml',
                'User-Agent' => 'PHP-NFSe-Nacional/1.0',
            ],
        ]);
    }
    
    /**
     * Envia uma DPS individual (SÍNCRONO)
     */
    public function enviarDps(string $xml): Response
    {
        try {
            $this->validateXml($xml, SchemaType::DPS);
            $signedXml = $this->signer->sign($xml);
            $restResult = $this->callRest('POST', '/nfse', $signedXml);
            $response = $this->buildResponse($restResult, 'RecepcionarDps');
            
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
            $restResult = $this->callRest('POST', '/lote', $signedLote);
            $response = $this->buildResponse($restResult, 'RecepcionarLoteRps');
            
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
            $restResult = $this->callRest('POST', '/lote/consulta', $xml);
            $response = $this->buildResponse($restResult, 'ConsultarLoteRps');
            
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
            $restResult = $this->callRest('POST', '/nfse/consulta', $xml);
            $response = $this->buildResponse($restResult, 'ConsultarNfse');
            
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
            $restResult = $this->callRest('POST', '/nfse/cancelamento', $xml);
            $response = $this->buildResponse($restResult, 'CancelarNfse');
            
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

    /**
     * Faz chamada REST para a API do Portal Nacional de NFSe
     * 
     * @param string $method Método HTTP (POST, GET, etc.)
     * @param string $endpoint Endpoint da API (ex: /nfse, /lote)
     * @param string $xml XML da requisição (DPS assinada)
     * @return string XML da resposta
     */
    private function callRest(string $method, string $endpoint, string $xml): string
    {
        $url = $this->config->getApiEndpoint($endpoint);
        
        try {
            $response = $this->httpClient->request($method, $endpoint, [
                RequestOptions::BODY => $xml,
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/xml',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Verifica se a resposta é um XML válido
            if (empty($body) || !str_contains($body, '<')) {
                throw new CommunicationException(
                    "Resposta da API não contém XML válido. Status: {$statusCode}"
                );
            }

            // Se status não for 2xx, pode ser erro mas ainda retornar XML com mensagem de erro
            if ($statusCode >= 400) {
                // Tenta extrair mensagem de erro do XML
                $simpleXml = @simplexml_load_string($body);
                if ($simpleXml !== false) {
                    $mensagemErro = $this->extractErrorMessage($body);
                    throw new CommunicationException(
                        "Erro na API (HTTP {$statusCode}): {$mensagemErro}",
                        $statusCode
                    );
                }
                
                throw new CommunicationException(
                    "Erro na API (HTTP {$statusCode}): {$body}",
                    $statusCode
                );
            }

            return $body;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $mensagem = sprintf(
                'Falha na comunicação com a API (%s): %s',
                $this->config->ambienteNome(),
                $e->getMessage()
            );
            
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();
                
                $mensagem .= "\nHTTP Status: {$statusCode}";
                $mensagem .= "\nURL: {$url}";
                
                if (!empty($body)) {
                    $mensagemErro = $this->extractErrorMessage($body);
                    if ($mensagemErro) {
                        $mensagem .= "\nErro: {$mensagemErro}";
                    }
                }
            }
            
            throw new CommunicationException($mensagem, (int) ($e->getCode() ?: 500), $e);
        } catch (Throwable $e) {
            throw new CommunicationException(
                sprintf('Falha na operação (%s): %s', $this->config->ambienteNome(), $e->getMessage()),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Extrai mensagem de erro do XML de resposta
     */
    private function extractErrorMessage(string $xml): ?string
    {
        try {
            $simpleXml = @simplexml_load_string($xml);
            if ($simpleXml === false) {
                return null;
            }

            // Tenta encontrar mensagem de erro em vários formatos possíveis
            $patterns = [
                '//Mensagem',
                '//MensagemErro',
                '//Erro',
                '//Descricao',
                '//MensagemRetorno',
            ];

            foreach ($patterns as $pattern) {
                $nodes = $simpleXml->xpath($pattern);
                if (!empty($nodes)) {
                    return trim((string) $nodes[0]);
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
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
