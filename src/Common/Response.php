<?php

declare(strict_types=1);

namespace NFSeNacional\Common;

/**
 * Classe para padronização de respostas
 *
 * @package NFSeNacional\Common
 */
class Response
{
    public function __construct(
        public bool $sucesso = false,
        public string $mensagem = '',
        public array $dados = [],
        public ?string $numero = null,
        public ?string $codigoVerificacao = null,
        public ?string $xml = null,
        public array $erros = []
    ) {
    }

    public function addErro(string $mensagem, ?int $codigo = null): self
    {
        $this->erros[] = [
            'mensagem' => $mensagem,
            'codigo' => $codigo
        ];

        return $this;
    }

    public function toArray(): array
    {
        return [
            'sucesso' => $this->sucesso,
            'mensagem' => $this->mensagem,
            'dados' => $this->dados,
            'numero' => $this->numero,
            'codigoVerificacao' => $this->codigoVerificacao,
            'erros' => $this->erros
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
