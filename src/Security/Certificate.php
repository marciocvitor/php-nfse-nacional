<?php

declare(strict_types=1);

namespace NFSeNacional\Security;

use NFSeNacional\Exception\CertificateException;

/**
 * Gerencia o certificado digital A1 (PFX)
 */
class Certificate
{
    private string $certificate;
    private string $privateKey;
    private string $passphrase;
    private ?string $pemFile = null;
    private array $meta;

    private function __construct(string $certificate, string $privateKey, string $passphrase, array $meta)
    {
        $this->certificate = $certificate;
        $this->privateKey = $privateKey;
        $this->passphrase = $passphrase;
        $this->meta = $meta;
    }

    /**
     * Cria instância a partir de arquivo PFX ou conteúdo binário
     * Compatível com nfephp-org (aceita conteúdo binário diretamente)
     * 
     * @param string $pfxPathOrContent Caminho do arquivo PFX ou conteúdo binário do certificado
     * @param string $passphrase Senha do certificado
     * @return self
     */
    public static function fromPfx(string $pfxPathOrContent, string $passphrase): self
    {
        // Verifica se é caminho de arquivo ou conteúdo binário
        $pfxContent = self::getPfxContent($pfxPathOrContent);
        
        if ($pfxContent === false || $pfxContent === '') {
            throw new CertificateException('Conteúdo do certificado PFX inválido ou vazio');
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $passphrase)) {
            throw new CertificateException('Falha ao ler o PFX. Senha inválida ou arquivo corrompido.');
        }

        if (empty($certs['cert']) || empty($certs['pkey'])) {
            throw new CertificateException('Certificado ou chave privada ausente no PFX');
        }

        $parsed = openssl_x509_parse($certs['cert']);
        if ($parsed === false) {
            throw new CertificateException('Falha ao interpretar certificado');
        }

        if (isset($parsed['validTo_time_t']) && $parsed['validTo_time_t'] < time()) {
            throw new CertificateException('Certificado expirado');
        }

        return new self($certs['cert'], $certs['pkey'], $passphrase, $parsed);
    }

    /**
     * Alias para fromPfx() - compatibilidade com nfephp-org
     * Aceita conteúdo binário diretamente, igual ao readPfx() do nfephp-org
     * 
     * @param string $pfxPathOrContent Caminho do arquivo PFX ou conteúdo binário
     * @param string $passphrase Senha do certificado
     * @return self
     */
    public static function readPfx(string $pfxPathOrContent, string $passphrase): self
    {
        return self::fromPfx($pfxPathOrContent, $passphrase);
    }

    /**
     * Extrai o conteúdo binário do PFX
     * Aceita tanto caminho de arquivo quanto conteúdo binário diretamente
     * Compatível com nfephp-org (readPfx aceita conteúdo binário)
     * 
     * @param string $pfxPathOrContent Caminho do arquivo ou conteúdo binário
     * @return string|false Conteúdo binário do PFX ou false em caso de erro
     */
    private static function getPfxContent(string $pfxPathOrContent): string|false
    {
        // Se for um arquivo existente, lê o conteúdo
        if (is_file($pfxPathOrContent) && is_readable($pfxPathOrContent)) {
            $content = file_get_contents($pfxPathOrContent);
            if ($content !== false && strlen($content) > 0) {
                return $content;
            }
        }
        
        // Se não for arquivo, assume que é conteúdo binário
        // Verifica se tem tamanho mínimo de um PFX válido (geralmente > 1KB)
        if (strlen($pfxPathOrContent) > 0) {
            // PFX geralmente tem pelo menos alguns KB
            // Se for muito pequeno e parecer texto, provavelmente é caminho inválido
            if (strlen($pfxPathOrContent) < 100 && ctype_print($pfxPathOrContent)) {
                // Parece ser um caminho de arquivo que não existe
                throw new CertificateException("Certificado não encontrado: {$pfxPathOrContent}");
            }
            
            // Assume que é conteúdo binário
            return $pfxPathOrContent;
        }
        
        return false;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getSerialNumber(): ?string
    {
        return $this->meta['serialNumberHex'] ?? null;
    }

    public function getPemPath(): string
    {
        if ($this->pemFile !== null && is_file($this->pemFile)) {
            return $this->pemFile;
        }

        $pemContent = $this->certificate . PHP_EOL . $this->privateKey;
        $tmpFile = tempnam(sys_get_temp_dir(), 'nfse_pem_');
        if ($tmpFile === false) {
            throw new CertificateException('Não foi possível criar arquivo temporário para o PEM');
        }

        if (file_put_contents($tmpFile, $pemContent) === false) {
            throw new CertificateException('Falha ao escrever arquivo PEM temporário');
        }

        $this->pemFile = $tmpFile;
        register_shutdown_function(static function (?string $file): void {
            if ($file && is_file($file)) {
                @unlink($file);
            }
        }, $this->pemFile);

        return $this->pemFile;
    }

    public function getPassphrase(): string
    {
        return $this->passphrase;
    }
}

