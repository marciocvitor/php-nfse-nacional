<?php

declare(strict_types=1);

namespace NFSeNacional\Config;

/**
 * Classe de configuração para NFSe Nacional
 */
class Config
{
    public readonly string $cnpj;
    public readonly string $im;
    public readonly string $cmun;
    public readonly string $razaoSocial;
    public readonly int $ambiente; // 1=Produção, 2=Homologação
    public readonly string|array $certPfx; // Pode ser caminho do arquivo ou conteúdo binário
    public readonly string $certPassword;
    public readonly bool $validarSchemas;
    public readonly ?string $apiBaseUrl; // URL base da API REST (opcional, usa padrão se não informado)

    /**
     * Cria uma instância de Config a partir de um array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cnpj: $data['cnpj'] ?? '',
            im: $data['im'] ?? $data['inscricaoMunicipal'] ?? '',
            cmun: $data['cmun'] ?? $data['codigoMunicipio'] ?? '',
            razaoSocial: $data['razaoSocial'] ?? $data['razaosocial'] ?? '',
            ambiente: (int) ($data['ambiente'] ?? 2),
            certPfx: $data['certPfx'] ?? '',
            certPassword: $data['certPassword'] ?? '',
            validarSchemas: $data['validarSchemas'] ?? true,
            apiBaseUrl: $data['apiBaseUrl'] ?? null
        );
    }

    /**
     * Construtor privado - use fromArray() para criar instâncias
     */
    private function __construct(
        string $cnpj,
        string $im,
        string $cmun,
        string $razaoSocial,
        int $ambiente,
        string|array $certPfx,
        string $certPassword,
        bool $validarSchemas,
        ?string $apiBaseUrl
    ) {
        $this->cnpj = $cnpj;
        $this->im = $im;
        $this->cmun = $cmun;
        $this->razaoSocial = $razaoSocial;
        $this->ambiente = $ambiente;
        $this->certPfx = $certPfx;
        $this->certPassword = $certPassword;
        $this->validarSchemas = $validarSchemas;
        $this->apiBaseUrl = $apiBaseUrl;
    }

    /**
     * Obtém a URL base da API REST conforme ambiente
     * Baseado nas URLs oficiais do Portal Nacional de NFSe
     * 
     * @return string URL base da API (ex: https://sefin.nfse.gov.br)
     */
    public function getApiBaseUrl(): string
    {
        // Se foi configurada manualmente, usa ela
        if (!empty($this->apiBaseUrl)) {
            return rtrim($this->apiBaseUrl, '/');
        }
        
        // URLs oficiais do Portal Nacional de NFSe
        // Conforme documentação oficial e relatos de usuários
        // O endpoint correto é sefin.producaorestrita.nfse.gov.br/sefinnacional/
        if ($this->ambiente == 1) {
            // Produção
            return 'https://sefin.nfse.gov.br';
        } else {
            // Homologação (Produção Restrita)
            return 'https://sefin.producaorestrita.nfse.gov.br';
        }
    }

    /**
     * Obtém o endpoint completo para uma operação
     * 
     * @param string $endpoint Endpoint da API (ex: /nfse, /lote, /consulta)
     * @return string URL completa
     */
    public function getApiEndpoint(string $endpoint): string
    {
        $baseUrl = $this->getApiBaseUrl();
        $endpoint = ltrim($endpoint, '/');
        
        // Endpoints diferentes para homologação e produção:
        // Homologação: https://sefin.producaorestrita.nfse.gov.br/SefinNacional/nfse (sem /API/)
        // Produção: https://sefin.nfse.gov.br/sefinnacional/nfse (sem /API/)
        // Conforme documentação: https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/
        
        if ($this->ambiente == 1) {
            // Produção: /sefinnacional/ (minúsculo)
            if (!str_starts_with($endpoint, 'sefinnacional/')) {
                $endpoint = 'sefinnacional/' . $endpoint;
            }
        } else {
            // Homologação: /SefinNacional/ (com maiúsculas)
            if (!str_starts_with($endpoint, 'SefinNacional/')) {
                $endpoint = 'SefinNacional/' . $endpoint;
            }
        }
        
        return $baseUrl . '/' . $endpoint;
    }

    /**
     * Retorna o nome do ambiente (para logs)
     */
    public function ambienteNome(): string
    {
        return $this->ambiente == 1 ? 'Produção' : 'Homologação';
    }
}

