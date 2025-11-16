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

    public static function fromPfx(string $pfxPath, string $passphrase): self
    {
        if (!is_file($pfxPath)) {
            throw new CertificateException("Certificado não encontrado em {$pfxPath}");
        }

        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new CertificateException('Não foi possível ler o arquivo PFX');
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

