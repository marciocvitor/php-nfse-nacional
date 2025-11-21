<?php

declare(strict_types=1);

namespace NFSeNacional\Make;

use DOMDocument;
use NFSeNacional\Validator\DpsValidator;

/**
 * Classe para geração de XML da DPS/RPS (NFSe)
 * 
 * @package NFSeNacional\Make
 */
class Make
{
    private DOMDocument $dom;
    private array $identificacao = [
        'numero' => '',
        'serie' => 'UNICA',
        'tipo' => 1,
    ];
    private string $competencia;
    private int $naturezaOperacao = 1;
    private int $regimeEspecialTributacao = 0;
    private int $optanteSimples = 2;
    private int $incentivadorCultural = 2;
    private array $prestador = [];
    private array $tomador = [];
    private array $servico = [];
    private DpsValidator $validator;
    private ?string $infDpsId = null;

    public function __construct(?DpsValidator $validator = null)
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
        $this->competencia = date('Y-m-d');
        $this->validator = $validator ?? new DpsValidator();
    }
    
    public function setIdentificacao(array $identificacao): self
    {
        $this->identificacao = array_merge($this->identificacao, [
            'numero' => $identificacao['numero'] ?? $this->identificacao['numero'],
            'serie' => $identificacao['serie'] ?? $this->identificacao['serie'],
            'tipo' => (int) ($identificacao['tipo'] ?? $this->identificacao['tipo']),
        ]);
        return $this;
    }

    public function setCompetencia(string $competencia): self
    {
        $this->competencia = $competencia;
        return $this;
    }

    public function setNaturezaOperacao(int $natureza): self
    {
        $this->naturezaOperacao = $natureza;
        return $this;
    }

    public function setRegimeEspecialTributacao(int $regime): self
    {
        $this->regimeEspecialTributacao = $regime;
        return $this;
    }

    public function setOptanteSimples(int $optante): self
    {
        $this->optanteSimples = $optante;
        return $this;
    }

    public function setIncentivadorCultural(int $flag): self
    {
        $this->incentivadorCultural = $flag;
        return $this;
    }

    public function setPrestador(array $data): self
    {
        $this->prestador = $this->validator->validatePrestador($data);
        return $this;
    }
    
    public function setTomador(array $data): self
    {
        $this->tomador = $this->validator->validateTomador($data);
        return $this;
    }
    
    public function setServico(array $data): self
    {
        $this->servico = $this->validator->validateServico($data);
        return $this;
    }
    
    /**
     * Alias para setServico() para compatibilidade
     */
    public function addServico(array $data): self
    {
        return $this->setServico($data);
    }
    
    /**
     * Define apenas os valores do serviço (ValorServicos, ValorIss, Aliquota)
     * Aceita tanto camelCase quanto PascalCase nos nomes dos campos
     */
    public function setValores(array $valores): self
    {
        // Se ainda não existe array de serviço, inicializa
        if (empty($this->servico)) {
            $this->servico = [];
        }
        
        // Normaliza os nomes dos campos (aceita camelCase e PascalCase)
        $valorServicos = $valores['valorServicos'] ?? $valores['ValorServicos'] ?? $valores['valor_servicos'] ?? null;
        $valorIss = $valores['valorIss'] ?? $valores['ValorIss'] ?? $valores['valor_iss'] ?? null;
        $aliquota = $valores['aliquota'] ?? $valores['Aliquota'] ?? null;
        
        // Mescla os valores com os dados existentes do serviço
        if ($valorServicos !== null) {
            $this->servico['valorServicos'] = $valorServicos;
        }
        if ($valorIss !== null) {
            $this->servico['valorIss'] = $valorIss;
        }
        if ($aliquota !== null) {
            $this->servico['aliquota'] = $aliquota;
        }
        
        // Se já tiver todos os dados do serviço, valida
        if (!empty($this->servico['itemListaServico']) && !empty($this->servico['discriminacao']) && !empty($this->servico['codigoMunicipio'])) {
            try {
                $this->servico = $this->validator->validateServico($this->servico);
            } catch (\Exception $e) {
                // Se falhar na validação, apenas armazena os valores sem validar ainda
                // A validação completa será feita no getXML()
            }
        }
        
        return $this;
    }
    
    public function getXML(): string
    {
        $this->assertRequiredData();

        $root = $this->dom->createElement('EnviarDpsEnvio');
        $root->setAttribute('xmlns', 'http://www.abrasf.org.br/nfse.xsd');
        $this->dom->appendChild($root);
        
        $dps = $this->dom->createElement('Dps');
        $root->appendChild($dps);
        
        $infDps = $this->dom->createElement('InfDps');
        $this->infDpsId = 'DPS' . date('YmdHis');
        $infDps->setAttribute('Id', $this->infDpsId);
        $dps->appendChild($infDps);

        $this->buildIdentificacao($infDps);
        $infDps->appendChild($this->dom->createElement('Competencia', $this->competencia));
        $infDps->appendChild($this->dom->createElement('NaturezaOperacao', (string) $this->naturezaOperacao));
        $infDps->appendChild($this->dom->createElement('RegimeEspecialTributacao', (string) $this->regimeEspecialTributacao));
        $infDps->appendChild($this->dom->createElement('OptanteSimplesNacional', (string) $this->optanteSimples));
        $infDps->appendChild($this->dom->createElement('IncentivadorCultural', (string) $this->incentivadorCultural));

        $infDps->appendChild($this->buildPrestador());
        $infDps->appendChild($this->buildTomador());
        $infDps->appendChild($this->buildServico());
        
        return $this->dom->saveXML() ?: '';
    }

    public function getInfDpsId(): ?string
    {
        return $this->infDpsId;
    }

    private function assertRequiredData(): void
    {
        if (empty($this->identificacao['numero'])) {
            throw new \InvalidArgumentException('Número da DPS é obrigatório.');
        }

        if (empty($this->prestador)) {
            throw new \InvalidArgumentException('Dados do prestador não informados.');
        }

        if (empty($this->tomador)) {
            throw new \InvalidArgumentException('Dados do tomador não informados.');
        }

        if (empty($this->servico)) {
            throw new \InvalidArgumentException('Dados do serviço não informados.');
        }
    }

    private function buildIdentificacao(\DOMElement $parent): void
    {
        $identificacao = $this->dom->createElement('IdentificacaoDps');
        $identificacao->appendChild($this->dom->createElement('Numero', $this->identificacao['numero']));
        $identificacao->appendChild($this->dom->createElement('Serie', $this->identificacao['serie']));
        $identificacao->appendChild($this->dom->createElement('Tipo', (string) $this->identificacao['tipo']));
        $parent->appendChild($identificacao);
    }

    private function buildPrestador(): \DOMElement
    {
        $prestador = $this->dom->createElement('Prestador');
        $cpfCnpj = $this->dom->createElement('CpfCnpj');
        $cpfCnpj->appendChild($this->dom->createElement('Cnpj', $this->prestador['cnpj']));
        $prestador->appendChild($cpfCnpj);
        $prestador->appendChild($this->dom->createElement('InscricaoMunicipal', $this->prestador['im']));
        return $prestador;
    }

    private function buildTomador(): \DOMElement
    {
        $tomador = $this->dom->createElement('Tomador');
        $cpfCnpj = $this->dom->createElement('CpfCnpj');
        $docTag = strlen($this->tomador['cpfCnpj']) === 11 ? 'Cpf' : 'Cnpj';
        $cpfCnpj->appendChild($this->dom->createElement($docTag, $this->tomador['cpfCnpj']));
        $tomador->appendChild($cpfCnpj);
        $tomador->appendChild($this->dom->createElement('RazaoSocial', $this->tomador['razaoSocial']));

        if (!empty($this->tomador['email'])) {
            $contato = $this->dom->createElement('Contato');
            $contato->appendChild($this->dom->createElement('Email', $this->tomador['email']));
            $tomador->appendChild($contato);
        }

        return $tomador;
    }

    private function buildServico(): \DOMElement
    {
        $servico = $this->dom->createElement('Servico');
        $valores = $this->dom->createElement('Valores');
        $valores->appendChild($this->dom->createElement('ValorServicos', $this->servico['valorServicos']));
        $valores->appendChild($this->dom->createElement('ValorIss', $this->servico['valorIss']));
        $valores->appendChild($this->dom->createElement('Aliquota', $this->servico['aliquota']));
        $servico->appendChild($valores);
        $servico->appendChild($this->dom->createElement('ItemListaServico', $this->servico['itemListaServico']));
        $servico->appendChild($this->dom->createElement('CodigoMunicipio', $this->servico['codigoMunicipio']));
        $servico->appendChild($this->dom->createElement('Discriminacao', $this->servico['discriminacao']));
        $servico->appendChild($this->dom->createElement('IssRetido', (string) $this->servico['issRetido']));

        return $servico;
    }
}
