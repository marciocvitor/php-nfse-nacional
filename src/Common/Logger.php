<?php

declare(strict_types=1);

namespace NFSeNacional\Common;

/**
 * Sistema de log para operações NFSe
 * Registra data, versão do layout e NT aplicada
 *
 * @package NFSeNacional\Common
 */
class Logger
{
    private const LOG_FORMAT = "[%s] %s | Versão: %s | NT: %s | %s | %s" . PHP_EOL;

    private string $logFile;
    private string $versaoLayout;
    private string $notaTecnica;

    public function __construct(?string $logDir = null)
    {
        $logDir = $logDir ?? sys_get_temp_dir() . '/nfse-nacional';

        if (!is_dir($logDir) && !mkdir($logDir, 0755, true) && !is_dir($logDir)) {
            throw new \RuntimeException("Não foi possível criar diretório de log: {$logDir}");
        }

        $this->logFile = $logDir . '/nfse-operacoes.log';
        $this->versaoLayout = $this->loadVersaoLayout();
        $this->notaTecnica = $this->loadNotaTecnica();
    }

    /**
     * Registra uma operação no log
     */
    public function log(string $operacao, string $mensagem, array $contexto = []): void
    {
        $dataHora = date('Y-m-d H:i:s');
        $contextoStr = empty($contexto) ? '' : json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $logEntry = sprintf(
            self::LOG_FORMAT,
            $dataHora,
            str_pad($operacao, 25),
            $this->versaoLayout,
            $this->notaTecnica,
            $mensagem,
            $contextoStr
        );

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Registra envio de DPS
     */
    public function logEnvioDps(
        string $numeroDps,
        bool $sucesso,
        ?string $numeroNfse = null,
        ?string $erro = null
    ): void {
        $mensagem = $sucesso
            ? "DPS enviado com sucesso. NFSe: {$numeroNfse}"
            : "Falha ao enviar DPS. Erro: {$erro}";

        $this->log('EnviarDps', $mensagem, [
            'numeroDps' => $numeroDps,
            'numeroNfse' => $numeroNfse,
            'sucesso' => $sucesso,
            'erro' => $erro
        ]);
    }

    /**
     * Registra cancelamento de NFSe
     */
    public function logCancelamento(string $numeroNfse, bool $sucesso, ?string $erro = null): void
    {
        $mensagem = $sucesso
            ? "NFSe cancelada com sucesso"
            : "Falha ao cancelar NFSe. Erro: {$erro}";

        $this->log('CancelarNfse', $mensagem, [
            'numeroNfse' => $numeroNfse,
            'sucesso' => $sucesso,
            'erro' => $erro
        ]);
    }

    /**
     * Registra validação de XML
     */
    public function logValidacao(string $tipoSchema, bool $sucesso, ?string $erro = null): void
    {
        $mensagem = $sucesso
            ? "XML validado com sucesso"
            : "XML inválido. Erro: {$erro}";

        $this->log('ValidarXml', $mensagem, [
            'tipoSchema' => $tipoSchema,
            'sucesso' => $sucesso,
            'erro' => $erro
        ]);
    }

    /**
     * Carrega versão do layout atual do arquivo VERSION.txt
     */
    private function loadVersaoLayout(): string
    {
        $versionFile = dirname(__DIR__, 2) . '/VERSION.txt';

        if (is_file($versionFile)) {
            $versao = trim(file_get_contents($versionFile));
            if ($versao !== '') {
                return $versao;
            }
        }

        // Versão padrão se arquivo não existir
        return '1.00';
    }

    /**
     * Carrega Nota Técnica (NT) aplicada do arquivo NT.txt
     */
    private function loadNotaTecnica(): string
    {
        $ntFile = dirname(__DIR__, 2) . '/NT.txt';

        if (is_file($ntFile)) {
            $nt = trim(file_get_contents($ntFile));
            if ($nt !== '') {
                return $nt;
            }
        }

        // NT padrão se arquivo não existir
        return 'NT inicial - Layout 1.00';
    }

    /**
     * Retorna o caminho do arquivo de log
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }

    /**
     * Retorna versão do layout atual
     */
    public function getVersaoLayout(): string
    {
        return $this->versaoLayout;
    }

    /**
     * Retorna Nota Técnica atual
     */
    public function getNotaTecnica(): string
    {
        return $this->notaTecnica;
    }
}
