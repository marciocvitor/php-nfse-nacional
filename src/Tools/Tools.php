<?php

declare(strict_types=1);

namespace NFSeNacional\Tools;

use NFSeNacional\Common\Logger;
use NFSeNacional\Common\Response;
use NFSeNacional\Config\Config;
use NFSeNacional\Exception\CommunicationException;
use NFSeNacional\Exception\DanfseIndisponivelException;
use NFSeNacional\Exception\ValidationException;
use NFSeNacional\Pdf\DanfseGenerator;
use NFSeNacional\Pdf\DanfseGeneratorNacional;
use NFSeNacional\Security\Certificate;
use NFSeNacional\Security\XmlSigner;
use NFSeNacional\Validator\SchemaType;
use NFSeNacional\Validator\SchemaValidator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
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
    /** Marcado na primeira falha da API de DANFSe (descontinuada em 03/08/2026) para não insistir. */
    private bool $danfseApiIndisponivel = false;

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

        // Obtém o caminho do certificado PEM (contém certificado + chave privada)
        $certPemPath = $this->certificate->getPemPath();
        $certPassphrase = $this->certificate->getPassphrase();

        // Configura o certificado SSL do cliente para mutual TLS
        // O Guzzle aceita um arquivo PEM que contenha tanto o certificado quanto a chave privada
        // Ou pode usar um array com 'cert', 'key' e 'passphrase'
        // Como o arquivo PEM já contém ambos, usamos apenas 'cert'
        // Mas se a chave tiver senha, precisamos usar o formato de array
        $certConfig = $certPemPath;
        if (!empty($certPassphrase)) {
            // Se tiver senha, usa formato de array
            $certConfig = [
                $certPemPath, // Caminho do arquivo PEM (contém cert + key)
                $certPassphrase // Senha da chave privada
            ];
        }

        $this->httpClient = new Client([
            'base_uri' => $this->config->getApiBaseUrl(),
            'timeout' => 60,
            'verify' => $this->config->ambiente === 1, // Verifica SSL apenas em produção
            'cert' => $certConfig, // Certificado do cliente para mutual TLS
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

            // A API do Portal Nacional espera JSON com XML compactado em GZIP e codificado em Base64
            $restResult = $this->callRest('POST', '/nfse', $signedXml, true);
            $xmlResult = $restResult['xml'];
            $chaveAcesso = $restResult['chaveAcesso'] ?? null;
            $response = $this->buildResponse($xmlResult, 'RecepcionarDps', $chaveAcesso);

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
     * Cancela NFSe individual via API de Eventos
     *
     * O evento é um pedRegEvento (schema pedRegEvento_v1.01.xsd), com tpEvento 101101.
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e (50 dígitos)
     * @param string $motivo Justificativa do cancelamento (15 a 255 caracteres)
     * @param int $cMotivo Código do motivo: 1, 2 ou 9 (TSCodJustCanc). Confirme o significado no Anexo oficial.
     * @return Response
     */
    public function cancelarNfse(string $chaveAcesso, string $motivo, int $cMotivo = 2): Response
    {
        try {
            // Gerar XML do evento de cancelamento no formato SPED
            // O método retorna tanto o XML quanto o ID do evento para referência na assinatura
            $resultadoXml = $this->gerarXmlEventoCancelamento($chaveAcesso, $motivo, $cMotivo);
            $xmlEvento = $resultadoXml['xml'];
            $idEvento = $resultadoXml['idEvento'];

            // Assinar o XML do evento usando o ID correto
            // O elemento a ser assinado é infPedReg (não infEvento)
            $xmlEventoAssinado = $this->signer->sign($xmlEvento, 'infPedReg', $idEvento);

            // Enviar para o endpoint de eventos
            // Endpoint: POST /nfse/{chaveAcesso}/eventos
            $restResult = $this->callRestEvento('POST', '/nfse/' . $chaveAcesso . '/eventos', $xmlEventoAssinado);
            $xmlResult = $restResult['xml'];
            $response = $this->buildResponse($xmlResult, 'CancelarNfse', $chaveAcesso);

            $this->logger->logCancelamento(
                $chaveAcesso,
                $response->sucesso,
                $response->sucesso ? null : $response->mensagem
            );

            return $response;
        } catch (Throwable $e) {
            $this->logger->logCancelamento($chaveAcesso, false, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Consulta DPS pelo identificador para obter a chave de acesso da NFS-e gerada
     *
     * @param string $idDps Identificador da DPS (42 dígitos: cLocEmi(7) + tipoInscricao(1) + CNPJ(14) + serie(5) + nDPS(15))
     * @return Response Response contendo a chave de acesso em response->dados['chaveAcesso']
     */
    public function consultarDps(string $idDps): Response
    {
        try {
            // GET /dps/{idDps} - retorna JSON com chaveAcesso
            $url = $this->config->getApiEndpoint('/dps/' . $idDps);

            $certPemPath = $this->certificate->getPemPath();
            $certPassphrase = $this->certificate->getPassphrase();
            $certConfig = $certPemPath;
            if (!empty($certPassphrase)) {
                $certConfig = [$certPemPath, $certPassphrase];
            }

            $response = $this->httpClient->request('GET', $url, [
                RequestOptions::CERT => $certConfig,
                RequestOptions::VERIFY => $this->config->ambiente === 1,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode >= 400) {
                $jsonData = @json_decode($body, true);
                $mensagemErro = $jsonData['mensagem'] ?? $jsonData['message'] ?? "Erro ao consultar DPS";
                throw new CommunicationException("Erro ao consultar DPS: {$mensagemErro}", $statusCode);
            }

            $jsonData = @json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new CommunicationException("Resposta da API não é um JSON válido");
            }

            $chaveAcesso = $jsonData['chaveAcesso'] ?? $jsonData['chave_acesso'] ?? null;
            if (empty($chaveAcesso)) {
                throw new CommunicationException("Chave de acesso não encontrada na resposta da API");
            }

            $this->logger->log('ConsultarDps', "Consulta realizada para DPS: {$idDps}", [
                'idDps' => $idDps,
                'chaveAcesso' => $chaveAcesso,
                'sucesso' => true
            ]);

            return new Response(
                sucesso: true,
                mensagem: 'DPS consultada com sucesso',
                dados: [
                    'chaveAcesso' => $chaveAcesso,
                    'idDps' => $idDps,
                    'json' => $jsonData
                ],
                xml: null
            );
        } catch (Throwable $e) {
            $this->logger->log('ConsultarDps', "Falha ao consultar DPS: {$idDps}", [
                'idDps' => $idDps,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Consulta NFS-e pela chave de acesso
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e (44 dígitos)
     * @return Response Response contendo o XML da NFS-e em response->xml
     */
    public function consultarNfsePorChave(string $chaveAcesso): Response
    {
        try {
            // GET /nfse/{chaveAcesso} - retorna JSON com nfseXmlGZipB64 ou XML direto
            $url = $this->config->getApiEndpoint('/nfse/' . $chaveAcesso);

            $certPemPath = $this->certificate->getPemPath();
            $certPassphrase = $this->certificate->getPassphrase();
            $certConfig = $certPemPath;
            if (!empty($certPassphrase)) {
                $certConfig = [$certPemPath, $certPassphrase];
            }

            $response = $this->httpClient->request('GET', $url, [
                RequestOptions::CERT => $certConfig,
                RequestOptions::VERIFY => $this->config->ambiente === 1,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json, application/xml',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode >= 400) {
                $jsonData = @json_decode($body, true);
                $mensagemErro = $jsonData['mensagem'] ?? $jsonData['message'] ?? "Erro ao consultar NFS-e";
                throw new CommunicationException("Erro ao consultar NFS-e: {$mensagemErro}", $statusCode);
            }

            // Verificar se a resposta é JSON ou XML
            $xmlNfse = null;
            if (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')) {
                // É JSON - pode conter nfseXmlGZipB64
                $jsonData = @json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (isset($jsonData['nfseXmlGZipB64'])) {
                        // Descompactar GZIP e decodificar Base64
                        $gzBinary = base64_decode((string)$jsonData['nfseXmlGZipB64'], true);
                        if ($gzBinary === false) {
                            throw new CommunicationException('Falha ao decodificar nfseXmlGZipB64');
                        }
                        $xmlNfse = @gzdecode($gzBinary);
                        if ($xmlNfse === false) {
                            throw new CommunicationException('Falha ao descompactar nfseXmlGZipB64');
                        }
                    } elseif (isset($jsonData['nfseXml'])) {
                        $xmlNfse = (string)$jsonData['nfseXml'];
                    } elseif (isset($jsonData['xml'])) {
                        $xmlNfse = (string)$jsonData['xml'];
                    } else {
                        throw new CommunicationException('XML da NFS-e não encontrado na resposta JSON');
                    }
                } else {
                    throw new CommunicationException('Resposta da API não é um JSON válido');
                }
            } else {
                // É XML direto
                $xmlNfse = $body;
            }

            if (empty($xmlNfse)) {
                throw new CommunicationException('XML da NFS-e não encontrado na resposta');
            }

            $this->logger->log('ConsultarNfsePorChave', "Consulta realizada para NFS-e: {$chaveAcesso}", [
                'chaveAcesso' => $chaveAcesso,
                'sucesso' => true
            ]);

            return new Response(
                sucesso: true,
                mensagem: 'NFS-e consultada com sucesso',
                dados: [
                    'chaveAcesso' => $chaveAcesso,
                    'xml' => $xmlNfse
                ],
                xml: $xmlNfse
            );
        } catch (Throwable $e) {
            $this->logger->log('ConsultarNfsePorChave', "Falha ao consultar NFS-e: {$chaveAcesso}", [
                'chaveAcesso' => $chaveAcesso,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Baixa o DANFSe (PDF) diretamente da API do Portal Nacional
     *
     * Endpoint: GET /danfse/{chaveAcesso}
     * Retorna o PDF oficial do DANFSe gerado pelo Portal Nacional
     *
     * ATENÇÃO: o serviço foi descontinuado em 03/08/2026 (NT 008 v1.02). Falhas de conexão/TLS
     * (ex.: "sslv3 alert handshake failure") e respostas 5xx/410 são tratadas como
     * indisponibilidade: lança DanfseIndisponivelException, sem nova tentativa, e as chamadas
     * seguintes desta instância falham de imediato. Use obterDanfse() para cair no gerador local.
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e (50 dígitos)
     * @return string Conteúdo binário do PDF
     * @throws DanfseIndisponivelException Se a API de DANFSe estiver indisponível
     * @throws CommunicationException Se houver erro na comunicação ou se o PDF não for encontrado
     */
    public function baixarDanfse(string $chaveAcesso): string
    {
        if ($this->danfseApiIndisponivel) {
            throw new DanfseIndisponivelException(
                'API de DANFSe indisponível nesta sessão (serviço descontinuado em 03/08/2026); '
                . 'gere o DANFSe localmente. Nova tentativa não realizada.'
            );
        }

        try {
            // GET /danfse/{chaveAcesso} - retorna PDF binário diretamente
            $url = $this->config->getApiEndpoint('/danfse/' . $chaveAcesso);

            $certPemPath = $this->certificate->getPemPath();
            $certPassphrase = $this->certificate->getPassphrase();
            $certConfig = $certPemPath;
            if (!empty($certPassphrase)) {
                $certConfig = [$certPemPath, $certPassphrase];
            }

            try {
                $response = $this->httpClient->request('GET', $url, [
                    RequestOptions::CERT => $certConfig,
                    RequestOptions::VERIFY => $this->config->ambiente === 1,
                    RequestOptions::HEADERS => [
                        'Accept' => 'application/pdf',
                    ],
                ]);
            } catch (BadResponseException $e) {
                $status = $e->getResponse()->getStatusCode();
                if ($status >= 500 || $status === 410) {
                    throw $this->marcarDanfseIndisponivel("HTTP {$status}", $e);
                }

                $json = @json_decode((string) $e->getResponse()->getBody(), true);
                $detalhe = is_array($json) ? ($json['mensagem'] ?? $json['message'] ?? null) : null;
                throw new CommunicationException(
                    'Erro ao baixar DANFSe: ' . ($detalhe ?? "HTTP {$status}"),
                    $status,
                    $e
                );
            } catch (TransferException $e) {
                // Sem resposta HTTP: conexão recusada, timeout ou falha de TLS (cURL 35/56/58/60...)
                throw $this->marcarDanfseIndisponivel($e->getMessage(), $e);
            }

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode >= 400) {
                // Tentar extrair mensagem de erro se for JSON
                $jsonData = @json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && isset($jsonData['mensagem'])) {
                    $mensagemErro = $jsonData['mensagem'];
                } elseif (json_last_error() === JSON_ERROR_NONE && isset($jsonData['message'])) {
                    $mensagemErro = $jsonData['message'];
                } else {
                    $mensagemErro = "Erro ao baixar DANFSe (HTTP {$statusCode})";
                }
                throw new CommunicationException("Erro ao baixar DANFSe: {$mensagemErro}", $statusCode);
            }

            // Verificar se a resposta é realmente um PDF
            // PDFs começam com %PDF
            if (!str_starts_with($body, '%PDF')) {
                // Pode ser JSON com erro ou outro formato
                $jsonData = @json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $mensagemErro = $jsonData['mensagem'] ?? $jsonData['message'] ?? "Resposta não é um PDF válido";
                    throw new CommunicationException("Erro ao baixar DANFSe: {$mensagemErro}");
                } else {
                    throw new CommunicationException("Resposta da API não é um PDF válido. Tipo de conteúdo: " . ($response->getHeader('Content-Type')[0] ?? 'desconhecido'));
                }
            }

            $this->logger->log('BaixarDanfse', "DANFSe baixado com sucesso: {$chaveAcesso}", [
                'chaveAcesso' => $chaveAcesso,
                'tamanhoPdf' => strlen($body),
                'sucesso' => true
            ]);

            return $body;
        } catch (Throwable $e) {
            $this->logger->log('BaixarDanfse', "Falha ao baixar DANFSe: {$chaveAcesso}", [
                'chaveAcesso' => $chaveAcesso,
                'erro' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Registra que a API de DANFSe está fora do ar (sem novas tentativas) e devolve a exceção a lançar.
     */
    private function marcarDanfseIndisponivel(string $detalhe, Throwable $anterior): DanfseIndisponivelException
    {
        $this->danfseApiIndisponivel = true;

        return new DanfseIndisponivelException(
            "API de DANFSe indisponível (serviço descontinuado em 03/08/2026): {$detalhe}",
            0,
            $anterior
        );
    }

    /**
     * Obtém o PDF do DANFSe: tenta a API oficial uma única vez e, se estiver indisponível,
     * gera o DANFSe localmente (DanfseGeneratorNacional) a partir do XML autorizado da NFS-e.
     *
     * Depois da primeira indisponibilidade, esta instância não volta a chamar a API.
     *
     * @param string $chaveAcesso Chave de acesso da NFS-e (50 dígitos)
     * @param string $xmlNfse XML autorizado da NFS-e (elemento NFSe)
     * @param array $dadosAdicionais Dados extras para o gerador local
     * @param bool $canceled true para a NFS-e cancelada: o gerador local aplica a marca d'água
     *                       "CANCELADA". O cancelamento não consta no XML autorizado, então o
     *                       chamador informa (ex.: ERP com nfse_cod_status = 135). O PDF oficial da API,
     *                       quando disponível, vem pronto e não recebe a marca.
     * @return array{pdf: string, origem: string} origem = 'api' (oficial) ou 'local' (gerador da biblioteca)
     * @throws \InvalidArgumentException Se a API estiver indisponível e o XML não for informado
     * @throws \RuntimeException Se o gerador local não puder ser usado (mpdf ausente)
     */
    public function obterDanfse(string $chaveAcesso, string $xmlNfse, array $dadosAdicionais = [], bool $canceled = false): array
    {
        try {
            return ['pdf' => $this->baixarDanfse($chaveAcesso), 'origem' => 'api'];
        } catch (DanfseIndisponivelException) {
            // segue para o gerador local
        }

        if (trim($xmlNfse) === '') {
            throw new \InvalidArgumentException('XML da NFS-e é necessário para gerar o DANFSe localmente.');
        }
        if (!class_exists(\Mpdf\Mpdf::class)) {
            throw new \RuntimeException('Gerador local de DANFSe requer mpdf/mpdf (composer require mpdf/mpdf).');
        }

        $response = new Response(sucesso: true, xml: $xmlNfse);
        $pdf = (new DanfseGeneratorNacional())->gerar($response, $dadosAdicionais, $canceled);

        $this->logger->log('ObterDanfse', "DANFSe gerado localmente: {$chaveAcesso}", [
            'chaveAcesso' => $chaveAcesso,
            'origem' => 'local',
            'cancelada' => $canceled,
            'tamanhoPdf' => strlen($pdf),
        ]);

        return ['pdf' => $pdf, 'origem' => 'local'];
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
    private function callRest(string $method, string $endpoint, string $xml, bool $useGzipBase64 = false): array
    {
        $url = $this->config->getApiEndpoint($endpoint);

        // Preserva o XML original para exibição em caso de erro
        $xmlOriginal = $xml;

        try {
            // Garante que o certificado está sendo usado na requisição
            $certPemPath = $this->certificate->getPemPath();
            $certPassphrase = $this->certificate->getPassphrase();
            $certConfig = $certPemPath;
            if (!empty($certPassphrase)) {
                $certConfig = [$certPemPath, $certPassphrase];
            }

            // A API do Portal Nacional espera JSON com XML compactado em GZIP e codificado em Base64
            // Especificamente para o endpoint /nfse (envio de DPS)
            if ($useGzipBase64) {
                // 1) Compactar XML em GZIP (nível 9 = máxima compressão)
                $gzipData = gzencode($xml, 9);
                if ($gzipData === false) {
                    throw new CommunicationException('Falha ao compactar XML em GZIP');
                }

                // 2) Codificar GZIP em Base64
                $base64Data = base64_encode($gzipData);

                // 3) Montar JSON com dpsXmlGZipB64
                $jsonBody = json_encode([
                    'dpsXmlGZipB64' => $base64Data
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                // Para outros endpoints, manter formato anterior (se necessário)
                $jsonBody = json_encode([
                    'xml' => $xml
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $response = $this->httpClient->request($method, $url, [
                RequestOptions::BODY => $jsonBody,
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, application/xml',
                ],
                RequestOptions::CERT => $certConfig, // Garante que o certificado é enviado
                RequestOptions::VERIFY => $this->config->ambiente === 1, // Verifica SSL apenas em produção
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Se a resposta for JSON, tenta extrair o XML (direto ou GZIP+Base64)
            $jsonData = null;
            $chaveAcessoJson = null; // Armazenar chave de acesso do JSON
            if (str_starts_with(trim($body), '{') || str_starts_with(trim($body), '[')) {
                $jsonData = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    // Capturar chave de acesso do JSON antes de processar XML
                    $chaveAcessoJson = $jsonData['chaveAcesso'] ?? $jsonData['chave_acesso'] ?? null;

                    // nfseXmlGZipB64 (retorno de sucesso da API) -> descompactar
                    if (isset($jsonData['nfseXmlGZipB64'])) {
                        $gzBinary = base64_decode((string)$jsonData['nfseXmlGZipB64'], true);
                        if ($gzBinary === false) {
                            throw new CommunicationException('Falha ao decodificar nfseXmlGZipB64 retornado pela API');
                        }
                        $xmlDecoded = @gzdecode($gzBinary);
                        if ($xmlDecoded === false) {
                            throw new CommunicationException('Falha ao descompactar nfseXmlGZipB64 retornado pela API');
                        }
                        $body = $xmlDecoded;
                    } elseif (isset($jsonData['nfseXml'])) {
                        $body = (string)$jsonData['nfseXml'];
                    } elseif (isset($jsonData['xml'])) {
                        $body = (string)$jsonData['xml'];
                    } elseif (isset($jsonData['data'])) {
                        $body = is_string($jsonData['data']) ? $jsonData['data'] : json_encode($jsonData['data']);
                    } elseif (isset($jsonData['response'])) {
                        $body = is_string($jsonData['response']) ? $jsonData['response'] : json_encode($jsonData['response']);
                    }
                } else {
                    $jsonData = null;
                }
            }

            // Verifica se a resposta é um XML válido
            if (empty($body) || !str_contains($body, '<')) {
                throw new CommunicationException(
                    "Resposta da API não contém XML válido. Status: {$statusCode}. Resposta: " . substr($body, 0, 500)
                );
            }

            // Se status não for 2xx, trata como erro
            if ($statusCode >= 400) {
                $mensagemErro = '';

                // Se a resposta for JSON, tenta extrair mensagem
                $jsonData = @json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    // Procura por campos comuns de erro em JSON
                    if (isset($jsonData['mensagem'])) {
                        $mensagemErro = $jsonData['mensagem'];
                    } elseif (isset($jsonData['message'])) {
                        $mensagemErro = $jsonData['message'];
                    } elseif (isset($jsonData['erro'])) {
                        $mensagemErro = is_string($jsonData['erro']) ? $jsonData['erro'] : json_encode($jsonData['erro']);
                    } elseif (isset($jsonData['erros']) && is_array($jsonData['erros'])) {
                        // Extrai erros do array e exibe apenas códigos/descrições para o usuário
                        $erros = [];
                        $temErrosEstruturados = false;
                        foreach ($jsonData['erros'] as $erro) {
                            $codigo = $erro['Codigo'] ?? '';
                            $descricao = $erro['Descricao'] ?? '';
                            $complemento = $erro['Complemento'] ?? '';

                            $erroFormatado = $codigo ? "[{$codigo}] " : '';
                            $erroFormatado .= $descricao;
                            if ($complemento) {
                                $erroFormatado .= ' - ' . $complemento;
                            }

                            // Mensagens específicas para erros comuns
                            if ($codigo === 'E0715') {
                                $erroFormatado .= "\n\nIMPORTANTE: Erro de validação do certificado digital.";
                                $erroFormatado .= "\nPossíveis causas:";
                                $erroFormatado .= "\n1. Certificado revogado ou expirado";
                                $erroFormatado .= "\n2. Servidor de revogação do Portal Nacional temporariamente offline";
                                $erroFormatado .= "\n3. Cadeia de certificação incompleta";
                                $erroFormatado .= "\n\nSoluções:";
                                $erroFormatado .= "\n- Verifique se o certificado está válido e não expirado";
                                $erroFormatado .= "\n- Aguarde alguns minutos e tente novamente (pode ser problema temporário)";
                                $erroFormatado .= "\n- Verifique se o certificado está configurado corretamente";
                                $erroFormatado .= "\n- Entre em contato com a autoridade certificadora se o problema persistir";
                            } elseif ($codigo === 'E0310') {
                                $erroFormatado .= "\n\nIMPORTANTE: O código de tributação nacional informado não existe na lista oficial do Portal Nacional.";
                                $erroFormatado .= "\nVerifique:";
                                $erroFormatado .= "\n1. Se o código LC116 está correto e ativo";
                                $erroFormatado .= "\n2. Se o código está na lista oficial do Portal Nacional";
                                $erroFormatado .= "\n3. Se o código está habilitado para o município de emissão";
                            } elseif ($codigo === 'RNG9997') {
                                // Extrair informações do erro de schema
                                $mensagemDetalhada = $this->extrairErroSchema($complemento ?: $descricao);
                                if ($mensagemDetalhada) {
                                    $erroFormatado = $codigo ? "[{$codigo}] " : '';
                                    $erroFormatado .= $mensagemDetalhada;
                                }
                            }
                            $erros[] = $erroFormatado;
                            if (!empty($codigo) || !empty($descricao)) {
                                $temErrosEstruturados = true;
                            }
                        }
                        if ($temErrosEstruturados) {
                            // Quando há erros estruturados, retorna apenas a mensagem limpa
                            $mensagemErro = "Resposta da API: " . implode("\n", $erros);
                        } else {
                            // Se não houver código/descrição, mantém comportamento antigo
                            $mensagemErro = implode("\n", $erros);
                            $mensagemErro .= "\n\nResposta da API: " . json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    } else {
                        $mensagemErro = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                } else {
                    // Tenta extrair mensagem de erro do XML
                    $simpleXml = @simplexml_load_string($body);
                    if ($simpleXml !== false) {
                        $mensagemErro = $this->extractErrorMessage($body);
                    } else {
                        // Se não for nem JSON nem XML, retorna o corpo completo
                        $mensagemErro = substr($body, 0, 1000);
                    }
                }

                // Se a mensagem de erro já contém "Resposta da API:" (erros estruturados),
                // retorna apenas essa parte limpa, sem detalhes técnicos
                if (strpos($mensagemErro, 'Resposta da API:') !== false) {
                    throw new CommunicationException($mensagemErro, $statusCode);
                }

                // Para outros tipos de erro, adiciona detalhes técnicos
                $xmlFormatado = $this->formatXml($xmlOriginal);
                $mensagemCompleta = "Erro na API (HTTP {$statusCode}): {$mensagemErro}";
                $mensagemCompleta .= "\n\n=== XML ENVIADO ===\n" . $xmlFormatado;

                throw new CommunicationException($mensagemCompleta, $statusCode);
            }

            // Retornar XML e chave de acesso (se disponível) para buildResponse
            return ['xml' => $body, 'chaveAcesso' => $chaveAcessoJson];
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
                    // Tenta decodificar JSON primeiro
                    $jsonData = @json_decode($body, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                        // Extrai mensagens de erro do JSON
                        if (isset($jsonData['erros']) && is_array($jsonData['erros'])) {
                            $erros = [];
                            $temErrosEstruturados = false;
                            foreach ($jsonData['erros'] as $erro) {
                                $codigo = $erro['Codigo'] ?? '';
                                $descricao = $erro['Descricao'] ?? '';
                                $complemento = $erro['Complemento'] ?? '';

                                $erroFormatado = $codigo ? "[{$codigo}] " : '';
                                $erroFormatado .= $descricao;
                                if ($complemento) {
                                    $erroFormatado .= ' - ' . $complemento;
                                }

                                // Mensagens específicas para erros comuns
                                if ($codigo === 'RNG9997') {
                                    // Extrair informações do erro de schema
                                    $mensagemDetalhada = $this->extrairErroSchema($complemento ?: $descricao);
                                    if ($mensagemDetalhada) {
                                        $erroFormatado = $codigo ? "[{$codigo}] " : '';
                                        $erroFormatado .= $mensagemDetalhada;
                                    }
                                }

                                $erros[] = $erroFormatado;
                                if (!empty($codigo) || !empty($descricao)) {
                                    $temErrosEstruturados = true;
                                }
                            }
                            if ($temErrosEstruturados) {
                                // Quando há erros estruturados, retorna apenas a mensagem limpa
                                $mensagem = "Resposta da API: " . implode("\n", $erros);
                            } else {
                                $mensagem .= "\nResposta da API: " . implode("\n", $erros);
                                $mensagem .= "\n\nResposta completa da API:\n" . json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                            }
                        } else {
                            // Mostra o JSON completo formatado
                            $mensagem .= "\nResposta da API: " . json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        }
                    } else {
                        // Se não for JSON, tenta extrair mensagem de erro do XML
                        $mensagemErro = $this->extractErrorMessage($body);
                        if ($mensagemErro) {
                            $mensagem .= "\nErro: {$mensagemErro}";
                        } else {
                            $mensagem .= "\nResposta: " . substr($body, 0, 2000);
                        }
                    }
                }
            }

            // Se a mensagem já contém "Resposta da API:" (erros estruturados),
            // retorna apenas essa parte limpa, sem detalhes técnicos
            if (strpos($mensagem, 'Resposta da API:') !== false && strpos($mensagem, 'Falha na comunicação') === false) {
                throw new CommunicationException($mensagem, (int) ($e->getCode() ?: 500), $e);
            }

            // Para outros tipos de erro, adiciona detalhes técnicos
            $xmlFormatado = $this->formatXml($xmlOriginal);
            $mensagem .= "\n\n=== XML ENVIADO ===\n" . $xmlFormatado;

            throw new CommunicationException($mensagem, (int) ($e->getCode() ?: 500), $e);
        } catch (Throwable $e) {
            $mensagem = sprintf('Falha na operação (%s): %s', $this->config->ambienteNome(), $e->getMessage());
            $xmlFormatado = $this->formatXml($xmlOriginal);
            $mensagem .= "\n\n=== XML ENVIADO ===\n" . $xmlFormatado;
            throw new CommunicationException($mensagem, (int) $e->getCode(), $e);
        }
    }

    /**
     * Formata XML para exibição (identado e escapado para HTML)
     */
    private function formatXml(string $xml): string
    {
        // Remove espaços em branco extras e quebras de linha no início
        $xml = trim($xml);

        // Tenta formatar o XML usando DOMDocument
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Tenta carregar o XML
        // Usa @ para suprimir warnings caso o XML tenha problemas
        $loaded = @$dom->loadXML($xml);

        if ($loaded) {
            $xmlFormatado = $dom->saveXML();
        } else {
            // Se não conseguir formatar, usa o XML original
            $xmlFormatado = $xml;
        }

        // Escapa caracteres HTML para exibição segura em HTML
        // Usa apenas ENT_QUOTES para compatibilidade com versões antigas do PHP
        return htmlspecialchars($xmlFormatado, ENT_QUOTES, 'UTF-8');
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

    /**
     * Extrai e formata erros de schema (RNG9997) de forma mais clara para o usuário
     *
     * @param string $mensagemErro Mensagem de erro completa do schema
     * @return string Mensagem formatada e clara para o usuário
     */
    private function extrairErroSchema(string $mensagemErro): string
    {
        $mensagem = "Erro de validação do XML conforme schema do Portal Nacional.\n\n";

        // Extrair nome do elemento que está com problema
        if (preg_match("/The '([^']+)' element is invalid/i", $mensagemErro, $matches)) {
            $elemento = $matches[1];
            // Remover namespace para facilitar leitura
            $elemento = preg_replace('/^.*:/', '', $elemento);

            // Mapear elementos conhecidos para mensagens mais claras
            $mapeamentoElementos = [
                'xNome' => 'Nome/Razão Social do Tomador',
                'xRazaoSocial' => 'Razão Social do Prestador',
                'xNomeFantasia' => 'Nome Fantasia',
                'xLgr' => 'Logradouro (Rua/Avenida)',
                'nro' => 'Número do Endereço',
                'xBairro' => 'Bairro',
                'cMun' => 'Código do Município (IBGE)',
                'CEP' => 'CEP',
                'fone' => 'Telefone',
                'email' => 'E-mail',
            ];

            $nomeAmigavel = $mapeamentoElementos[$elemento] ?? $elemento;

            // Verificar se é erro de valor vazio
            if (preg_match("/The value '' is invalid|actual length is less than the MinLength/i", $mensagemErro)) {
                $mensagem .= "PROBLEMA IDENTIFICADO:\n";
                $mensagem .= "O campo '{$nomeAmigavel}' está vazio ou não foi preenchido.\n\n";
                $mensagem .= "SOLUÇÃO:\n";

                if ($elemento === 'xNome') {
                    $mensagem .= "1. Verifique o cadastro do cliente (tomador) no sistema\n";
                    $mensagem .= "2. Certifique-se de que o campo 'Razão Social' ou 'Nome' está preenchido\n";
                    $mensagem .= "3. Se o cliente não tiver razão social, use o nome completo\n";
                    $mensagem .= "4. O campo não pode estar vazio conforme exigência do Portal Nacional\n";
                } else {
                    $mensagem .= "1. Verifique o cadastro do cliente no sistema\n";
                    $mensagem .= "2. Preencha o campo '{$nomeAmigavel}' corretamente\n";
                    $mensagem .= "3. O campo é obrigatório conforme schema do Portal Nacional\n";
                }
            } elseif (preg_match("/invalid according to its datatype/i", $mensagemErro)) {
                $mensagem .= "PROBLEMA IDENTIFICADO:\n";
                $mensagem .= "O campo '{$nomeAmigavel}' contém um valor inválido.\n\n";
                $mensagem .= "SOLUÇÃO:\n";
                $mensagem .= "1. Verifique o formato do campo '{$nomeAmigavel}'\n";
                $mensagem .= "2. Certifique-se de que o valor está no formato correto\n";
            } else {
                $mensagem .= "PROBLEMA IDENTIFICADO:\n";
                $mensagem .= "O campo '{$nomeAmigavel}' não está conforme o schema esperado.\n\n";
                $mensagem .= "DETALHES TÉCNICOS:\n";
                $mensagem .= $mensagemErro;
            }
        } else {
            // Se não conseguir extrair o elemento, mostra a mensagem original formatada
            $mensagem .= "PROBLEMA IDENTIFICADO:\n";
            $mensagem .= $mensagemErro . "\n\n";
            $mensagem .= "SOLUÇÃO:\n";
            $mensagem .= "1. Verifique todos os campos obrigatórios do cadastro do cliente\n";
            $mensagem .= "2. Certifique-se de que todos os dados estão preenchidos corretamente\n";
        }

        return $mensagem;
    }

    private function buildResponse(string $xml, string $contexto, ?string $chaveAcessoJson = null): Response
    {
        $dados = [
            'contexto' => $contexto,
            'xml' => $xml,
        ];

        // Adicionar chave de acesso do JSON se disponível
        if (!empty($chaveAcessoJson)) {
            $dados['chaveAcesso'] = $chaveAcessoJson;
        }

        $simpleXml = @simplexml_load_string($xml);
        if ($simpleXml !== false) {
            $dados['parsed'] = json_decode(json_encode($simpleXml), true);
            $numeroNode = $simpleXml->xpath('//NumeroNfse');
            $codigoNode = $simpleXml->xpath('//CodigoVerificacao');
            $numero = $numeroNode[0] ?? null;
            $codigo = $codigoNode[0] ?? null;

            // Se não encontrou chave de acesso no JSON, tenta usar o código de verificação
            if (empty($chaveAcessoJson) && !empty($codigo)) {
                $dados['chaveAcesso'] = (string) $codigo;
            }

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
     * Gera XML do evento de cancelamento no formato SPED
     * Conforme Anexo II - Eventos do Sistema Nacional
     *
     * O XML do evento segue o padrão do SPED para eventos de NFS-e
     *
     * O Id do pedRegEvento é "PRE" + chave(50) + "101101" (59 caracteres); $nPedRegEvento
     * não compõe o Id (a SEFIN rejeita 62 caracteres com E1235) e é mantido só por compatibilidade.
     *
     * @return array ['xml' => string, 'idEvento' => string]
     */
    private function gerarXmlEventoCancelamento(string $chaveAcesso, string $motivo, int $cMotivo = 2, int $nPedRegEvento = 1): array
    {
        if (strlen($chaveAcesso) !== 50 || !ctype_digit($chaveAcesso)) {
            throw new ValidationException('A chave de acesso deve conter exatamente 50 dígitos numéricos para eventos da NFS-e Nacional.');
        }

        if (!in_array($cMotivo, [1, 2, 9], true)) {
            throw new ValidationException('Código de motivo do cancelamento inválido (use 1, 2 ou 9).');
        }

        $motivoLimpo = rtrim(trim((string) preg_replace('/\s+/', ' ', $motivo)), ':');
        $tamanho = mb_strlen($motivoLimpo, 'UTF-8');
        if ($tamanho < 15 || $tamanho > 255) {
            throw new ValidationException('A justificativa de cancelamento deve ter entre 15 e 255 caracteres.');
        }

        // TSIdPedRegEvt (maxLength 59): "PRE" + chave(50) + tpEvento(6) = 59 caracteres.
        // O sufixo nPedRegEvento citado na documentação do XSD levaria o Id a 62 caracteres e a SEFIN
        // rejeita com E1235 (Pattern constraint failed); por isso ele não faz parte do Id.
        $tpEvento = '101101';
        $idEvento = 'PRE' . $chaveAcesso . $tpEvento;

        $cnpjAutor = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $this->config->cnpj));
        $dhEvento = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i:sP');

        $ns = 'http://www.sped.fazenda.gov.br/nfse';
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $root = $dom->createElementNS($ns, 'pedRegEvento');
        $root->setAttribute('versao', '1.01');
        $dom->appendChild($root);

        $inf = $dom->createElementNS($ns, 'infPedReg');
        $inf->setAttribute('Id', $idEvento);
        $root->appendChild($inf);

        // Ordem exata do TCInfPedReg: tpAmb, verAplic, dhEvento, CNPJAutor|CPFAutor, chNFSe, e101101
        $this->elementoNs($dom, $inf, 'tpAmb', (string) $this->config->ambiente);
        $this->elementoNs($dom, $inf, 'verAplic', '1.0.0');
        $this->elementoNs($dom, $inf, 'dhEvento', $dhEvento);
        $this->elementoNs($dom, $inf, strlen($cnpjAutor) === 11 ? 'CPFAutor' : 'CNPJAutor', $cnpjAutor);
        $this->elementoNs($dom, $inf, 'chNFSe', $chaveAcesso);

        $e101101 = $this->elementoNs($dom, $inf, 'e101101');
        $this->elementoNs($dom, $e101101, 'xDesc', 'Cancelamento de NFS-e');
        $this->elementoNs($dom, $e101101, 'cMotivo', (string) $cMotivo);
        $this->elementoNs($dom, $e101101, 'xMotivo', $motivoLimpo);

        $xml = $dom->saveXML();
        if ($xml === false) {
            throw new \RuntimeException('Falha ao gerar XML do evento de cancelamento');
        }

        return [
            'xml' => $xml,
            'idEvento' => $idEvento,
        ];
    }

    /**
     * Cria um elemento na namespace da NFS-e Nacional (evita xmlns="" nos filhos).
     */
    private function elementoNs(\DOMDocument $dom, \DOMElement $pai, string $nome, ?string $texto = null): \DOMElement
    {
        $elemento = $dom->createElementNS('http://www.sped.fazenda.gov.br/nfse', $nome);
        if ($texto !== null) {
            $elemento->appendChild($dom->createTextNode($texto));
        }
        $pai->appendChild($elemento);

        return $elemento;
    }

    /**
     * Faz chamada REST para API de Eventos
     * Envia JSON com XML do evento assinado compactado em GZIP+Base64
     * Campo: pedidoRegistroEventoXmlGZipB64
     */
    private function callRestEvento(string $method, string $endpoint, string $xml): array
    {
        $url = $this->config->getApiEndpoint($endpoint);

        // Preservar XML original para log de erro
        $xmlOriginal = $xml;

        try {
            $certPemPath = $this->certificate->getPemPath();
            $certPassphrase = $this->certificate->getPassphrase();
            $certConfig = $certPemPath;
            if (!empty($certPassphrase)) {
                $certConfig = [$certPemPath, $certPassphrase];
            }

            // 1) Compactar XML em GZIP (nível 9 = máxima compressão)
            $gzipData = gzencode($xml, 9);
            if ($gzipData === false) {
                throw new CommunicationException('Falha ao compactar XML do evento em GZIP');
            }

            // 2) Codificar GZIP em Base64
            $base64Data = base64_encode($gzipData);

            // 3) Montar JSON no padrão do Portal Nacional
            //    pedidoRegistroEventoXmlGZipB64 -> XML do evento assinado compactado+Base64
            $jsonBody = json_encode([
                'pedidoRegistroEventoXmlGZipB64' => $base64Data,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Log (apenas para homologação)
            if ($this->config->ambiente === 2) {
                $this->logger->log('CancelarNfse', 'Enviando evento de cancelamento (GZIP+Base64)', [
                    'url' => $url,
                    'xml_length' => strlen($xml),
                    'json_length' => strlen($jsonBody),
                    'xml_preview' => substr($xml, 0, 500),
                ]);
            }

            $response = $this->httpClient->request($method, $url, [
                RequestOptions::BODY => $jsonBody,
                RequestOptions::HEADERS => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, application/xml',
                ],
                RequestOptions::CERT => $certConfig,
                RequestOptions::VERIFY => $this->config->ambiente === 1,
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            // Se status não for 2xx, trata como erro
            if ($statusCode >= 400) {
                $mensagemErro = '';
                $jsonData = @json_decode($body, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    if (isset($jsonData['mensagem'])) {
                        $mensagemErro = $jsonData['mensagem'];
                    } elseif (isset($jsonData['message'])) {
                        $mensagemErro = $jsonData['message'];
                    } elseif (isset($jsonData['erros']) && is_array($jsonData['erros'])) {
                        $erros = [];
                        $temErrosEstruturados = false;
                        foreach ($jsonData['erros'] as $erro) {
                            $codigo = $erro['Codigo'] ?? '';
                            $descricao = $erro['Descricao'] ?? '';
                            $complemento = $erro['Complemento'] ?? '';

                            $linha = $codigo ? "[{$codigo}] " : '';
                            $linha .= $descricao;
                            if ($complemento) {
                                $linha .= ' - ' . $complemento;
                            }
                            $erros[] = $linha;
                            if (!empty($codigo) || !empty($descricao)) {
                                $temErrosEstruturados = true;
                            }
                        }
                        if ($temErrosEstruturados) {
                            // Quando há erros estruturados, retorna apenas a mensagem limpa
                            $mensagemErro = "Resposta da API: " . implode("\n", $erros);
                        } else {
                            $mensagemErro = implode("\n", $erros);
                        }
                    } else {
                        $mensagemErro = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    }
                } else {
                    $mensagemErro = substr($body, 0, 1000);
                }

                // Se a mensagem de erro já contém "Resposta da API:" (erros estruturados),
                // retorna apenas essa parte limpa, sem detalhes técnicos
                if (strpos($mensagemErro, 'Resposta da API:') !== false) {
                    throw new CommunicationException($mensagemErro, $statusCode);
                }

                // Para outros tipos de erro, adiciona detalhes técnicos
                $xmlFormatado = $this->formatXml($xmlOriginal);
                $mensagemCompleta = "Erro na API de Eventos (HTTP {$statusCode}): {$mensagemErro}";
                $mensagemCompleta .= "\n\n=== XML ENVIADO (antes do GZIP) ===\n" . $xmlFormatado;
                $mensagemCompleta .= "\n\nURL: {$url}\nResposta: {$body}";

                throw new CommunicationException($mensagemCompleta, $statusCode);
            }

            /**
             * Tratamento da resposta:
             * - Se vier JSON com eventoXmlGZipB64, descompacta e retorna o XML
             * - Se vier JSON com eventoXml, usa direto
             * - Se vier XML puro, retorna direto
             */
            $conteudoXml = $body;
            $jsonData = @json_decode($body, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                if (isset($jsonData['eventoXmlGZipB64'])) {
                    $gzBin = base64_decode((string)$jsonData['eventoXmlGZipB64'], true);
                    if ($gzBin === false) {
                        throw new CommunicationException('Falha ao decodificar eventoXmlGZipB64 da resposta');
                    }
                    $xmlDecoded = @gzdecode($gzBin);
                    if ($xmlDecoded === false) {
                        throw new CommunicationException('Falha ao descompactar eventoXmlGZipB64 da resposta');
                    }
                    $conteudoXml = $xmlDecoded;
                } elseif (isset($jsonData['eventoXml'])) {
                    $conteudoXml = (string)$jsonData['eventoXml'];
                }
            }

            return ['xml' => $conteudoXml, 'chaveAcesso' => null];
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $mensagem = '';

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                // Tenta decodificar JSON primeiro
                $jsonData = @json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    // Extrai mensagens de erro do JSON
                    if (isset($jsonData['erros']) && is_array($jsonData['erros'])) {
                        $erros = [];
                        $temErrosEstruturados = false;
                        foreach ($jsonData['erros'] as $erro) {
                            $codigo = $erro['Codigo'] ?? '';
                            $descricao = $erro['Descricao'] ?? '';
                            $complemento = $erro['Complemento'] ?? '';

                            $linha = $codigo ? "[{$codigo}] " : '';
                            $linha .= $descricao;
                            if ($complemento) {
                                $linha .= ' - ' . $complemento;
                            }
                            $erros[] = $linha;
                            if (!empty($codigo) || !empty($descricao)) {
                                $temErrosEstruturados = true;
                            }
                        }
                        if ($temErrosEstruturados) {
                            // Quando há erros estruturados, retorna apenas a mensagem limpa
                            $mensagem = "Resposta da API: " . implode("\n", $erros);
                        } else {
                            $mensagem = sprintf(
                                'Falha na comunicação com a API de Eventos (%s): %s',
                                $this->config->ambienteNome(),
                                $e->getMessage()
                            );
                            $mensagem .= "\nHTTP Status: {$statusCode}\nURL: {$url}\nResposta: {$body}";
                        }
                    } else {
                        $mensagem = sprintf(
                            'Falha na comunicação com a API de Eventos (%s): %s',
                            $this->config->ambienteNome(),
                            $e->getMessage()
                        );
                        $mensagem .= "\nHTTP Status: {$statusCode}\nURL: {$url}\nResposta: {$body}";
                    }
                } else {
                    $mensagem = sprintf(
                        'Falha na comunicação com a API de Eventos (%s): %s',
                        $this->config->ambienteNome(),
                        $e->getMessage()
                    );
                    $mensagem .= "\nHTTP Status: {$statusCode}\nURL: {$url}\nResposta: {$body}";
                }
            } else {
                $mensagem = sprintf(
                    'Falha na comunicação com a API de Eventos (%s): %s',
                    $this->config->ambienteNome(),
                    $e->getMessage()
                );
            }

            // Se a mensagem já contém "Resposta da API:" (erros estruturados),
            // retorna apenas essa parte limpa, sem detalhes técnicos
            if (strpos($mensagem, 'Resposta da API:') !== false) {
                throw new CommunicationException($mensagem, (int)($e->getCode() ?: 500), $e);
            }

            // Para outros tipos de erro, adiciona detalhes técnicos
            $xmlFormatado = $this->formatXml($xmlOriginal);
            $mensagem .= "\n\n=== XML ENVIADO (antes do GZIP) ===\n" . $xmlFormatado;

            throw new CommunicationException($mensagem, (int)($e->getCode() ?: 500), $e);
        }
    }
}
