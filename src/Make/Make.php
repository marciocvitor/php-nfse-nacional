<?php

declare(strict_types=1);

namespace NFSeNacional\Make;

use DOMDocument;

/**
 * Classe para geração de XML da DPS/RPS (NFSe)
 * 
 * @package NFSeNacional\Make
 */
class Make
{
    private DOMDocument $dom;
    private array $prestador = [];
    private array $tomador = [];
    private array $servico = [];
    
    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
    }
    
    public function setPrestador(array $data): self
    {
        $this->prestador = $data;
        return $this;
    }
    
    public function setTomador(array $data): self
    {
        $this->tomador = $data;
        return $this;
    }
    
    public function setServico(array $data): self
    {
        $this->servico = $data;
        return $this;
    }
    
    public function getXML(): string
    {
        $root = $this->dom->createElement('EnviarDpsEnvio');
        $root->setAttribute('xmlns', 'http://www.abrasf.org.br/nfse.xsd');
        $this->dom->appendChild($root);
        
        $dps = $this->dom->createElement('Dps');
        $root->appendChild($dps);
        
        $infDps = $this->dom->createElement('InfDps');
        $infDps->setAttribute('Id', 'DPS' . date('YmdHis'));
        $dps->appendChild($infDps);
        
        // Adicionar dados do prestador (simplificado)
        if (!empty($this->prestador)) {
            $prestador = $this->dom->createElement('Prestador');
            $infDps->appendChild($prestador);
            
            $cpfCnpj = $this->dom->createElement('CpfCnpj');
            $prestador->appendChild($cpfCnpj);
            $cpfCnpj->appendChild(
                $this->dom->createElement('Cnpj', $this->prestador['cnpj'] ?? '')
            );
        }
        
        return $this->dom->saveXML() ?: '';
    }
}
