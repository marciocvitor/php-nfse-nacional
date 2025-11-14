<?php

namespace NFSeNacional\Common;

class Response
{
    public $sucesso;
    public $mensagem;
    public $dados;
    public $numero;
    public $codigoVerificacao;
    public $erros;
    
    public function __construct(bool $sucesso = false, string $mensagem = '', array $dados = [])
    {
        $this->sucesso = $sucesso;
        $this->mensagem = $mensagem;
        $this->dados = $dados;
        $this->erros = [];
    }
    
    public function toArray(): array
    {
        return [
            'sucesso' => $this->sucesso,
            'mensagem' => $this->mensagem,
            'dados' => $this->dados,
            'numero' => $this->numero ?? null,
            'codigoVerificacao' => $this->codigoVerificacao ?? null,
            'erros' => $this->erros
        ];
    }
    
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
