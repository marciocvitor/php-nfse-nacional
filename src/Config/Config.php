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
    public readonly array $wsdl;

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
            wsdl: $data['wsdl'] ?? []
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
        array $wsdl
    ) {
        $this->cnpj = $cnpj;
        $this->im = $im;
        $this->cmun = $cmun;
        $this->razaoSocial = $razaoSocial;
        $this->ambiente = $ambiente;
        $this->certPfx = $certPfx;
        $this->certPassword = $certPassword;
        $this->validarSchemas = $validarSchemas;
        $this->wsdl = $wsdl;
    }

    /**
     * Obtém o WSDL para uma operação específica
     */
    public function getWsdl(string $operation): string
    {
        $ambienteKey = $this->ambiente == 1 ? 'producao' : 'homologacao';
        
        if (isset($this->wsdl[$ambienteKey][$operation])) {
            return $this->wsdl[$ambienteKey][$operation];
        }
        
        // WSDLs padrão do Portal Nacional
        $baseUrl = $this->ambiente == 1 
            ? 'https://api.nfse.gov.br/wsdl' 
            : 'https://homologacao.nfse.gov.br/wsdl';
        
        return $baseUrl . '/' . $operation . '.wsdl';
    }

    /**
     * Retorna o nome do ambiente (para logs)
     */
    public function ambienteNome(): string
    {
        return $this->ambiente == 1 ? 'Produção' : 'Homologação';
    }
}

