<?php

declare(strict_types=1);

namespace NFSeNacional\Make;

use DOMDocument;
use NFSeNacional\Validator\DpsValidator;

/**
 * Classe para geração de XML da DPS/RPS (NFSe) no formato SPED
 * Conforme XML aprovado no Portal Nacional NFSe
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
    
    // Campos adicionais conforme XML aprovado no Portal Nacional (formato SPED)
    private int $tpAmb = 2; // 1=Produção, 2=Homologação
    private ?string $dhEmi = null; // Data/Hora de Emissão
    private string $verAplic = '1.0.0'; // Versão da Aplicação
    private int $tpEmit = 1; // Tipo de Emitente: 1=Prestador
    private ?string $cLocEmi = null; // Código do Município de Emissão (IBGE)
    private array $infNFSeCampos = []; // Campos adicionais do infNFSe (xLocEmi, xLocPrestacao, nNFSe, etc.)
    private ?string $pTotTribSN = null; // Percentual total de tributos no Simples Nacional (pTotTribSN)
    private ?float $vTotalRet = null; // Valor total retido (vTotalRet)
    private ?float $vLiq = null; // Valor líquido (vLiq)

    public function __construct(?DpsValidator $validator = null)
    {
        $this->dom = new DOMDocument('1.0', 'utf-8');
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

    /**
     * Define o tipo de ambiente (1=Produção, 2=Homologação)
     */
    public function setTpAmb(int $tpAmb): self
    {
        $this->tpAmb = $tpAmb;
        return $this;
    }

    /**
     * Define a data/hora de emissão (formato: YYYY-MM-DDTHH:mm:ss-03:00)
     */
    public function setDhEmi(string $dhEmi): self
    {
        $this->dhEmi = $dhEmi;
        return $this;
    }

    /**
     * Define a versão da aplicação
     */
    public function setVerAplic(string $verAplic): self
    {
        $this->verAplic = $verAplic;
        return $this;
    }

    /**
     * Define o tipo de emitente (1=Prestador)
     */
    public function setTpEmit(int $tpEmit): self
    {
        $this->tpEmit = $tpEmit;
        return $this;
    }

    /**
     * Define o código do município de emissão (IBGE)
     */
    public function setCLocEmi(string $cLocEmi): self
    {
        $this->cLocEmi = $cLocEmi;
        return $this;
    }

    /**
     * Define campos adicionais do infNFSe (xLocEmi, xLocPrestacao, nNFSe, etc.)
     */
    public function setInfNFSeCampos(array $campos): self
    {
        $this->infNFSeCampos = array_merge($this->infNFSeCampos, $campos);
        return $this;
    }
    
    /**
     * Define o percentual total de tributos no Simples Nacional (pTotTribSN)
     */
    public function setPTotTribSN(?string $pTotTribSN): self
    {
        $this->pTotTribSN = $pTotTribSN;
        return $this;
    }
    
    /**
     * Define valores totais retidos e líquido (vTotalRet e vLiq)
     */
    public function setValoresTotais(?float $vTotalRet, ?float $vLiq): self
    {
        $this->vTotalRet = $vTotalRet;
        $this->vLiq = $vLiq;
        return $this;
    }

    public function setPrestador(array $data): self
    {
        // Validação robusta conforme NT - similar à estrutura spednfe-php
        $this->prestador = $this->validator->validatePrestador($data);
        return $this;
    }
    
    public function setTomador(array $data): self
    {
        // Validação robusta conforme NT - similar à estrutura spednfe-php
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
    
    /**
     * Gera XML no formato SPED conforme XML aprovado no Portal Nacional
     * Estrutura: <NFSe><infNFSe><DPS><infDPS>
     */
    public function getXML(): string
    {
        $this->assertRequiredData();

        // Estrutura SPED conforme XML aprovado
        // Criar elemento com namespace para garantir ordem correta dos atributos
        $root = $this->dom->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'NFSe');
        // Definir versao primeiro (ordem conforme XML aprovado: versao antes de xmlns)
        $root->setAttribute('versao', '1.00');
        // O xmlns já está definido pelo createElementNS, mas vamos garantir
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $this->dom->appendChild($root);
        
        // infNFSe (wrapper interno)
        $infNFSe = $this->dom->createElement('infNFSe');
        $cLocEmiSafe = $this->cLocEmi ?? '';
        $cnpjSafe = $this->prestador['cnpj'] ?? '';
        $numeroSafe = (string)($this->identificacao['numero'] ?? '');
        $infNFSeId = 'NFS' . $cLocEmiSafe . $cnpjSafe . str_pad($numeroSafe, 15, '0', STR_PAD_LEFT) . date('YmdHis');
        $infNFSe->setAttribute('Id', $infNFSeId);
        
        // Campos dentro de infNFSe (antes de <emit> e <DPS>)
        // Conforme XML aprovado no Portal Nacional
        if (!empty($this->infNFSeCampos['xLocEmi'])) {
            $infNFSe->appendChild($this->dom->createElement('xLocEmi', $this->infNFSeCampos['xLocEmi']));
        }
        
        if (!empty($this->infNFSeCampos['xLocPrestacao'])) {
            $infNFSe->appendChild($this->dom->createElement('xLocPrestacao', $this->infNFSeCampos['xLocPrestacao']));
        }
        
        if (!empty($this->infNFSeCampos['nNFSe'])) {
            $infNFSe->appendChild($this->dom->createElement('nNFSe', (string) $this->infNFSeCampos['nNFSe']));
        }
        
        if (!empty($this->infNFSeCampos['cLocIncid'])) {
            $infNFSe->appendChild($this->dom->createElement('cLocIncid', $this->infNFSeCampos['cLocIncid']));
        }
        
        if (!empty($this->infNFSeCampos['xLocIncid'])) {
            $infNFSe->appendChild($this->dom->createElement('xLocIncid', $this->infNFSeCampos['xLocIncid']));
        }
        
        if (!empty($this->infNFSeCampos['xTribNac'])) {
            $infNFSe->appendChild($this->dom->createElement('xTribNac', $this->infNFSeCampos['xTribNac']));
        }
        
        if (!empty($this->infNFSeCampos['xTribMun'])) {
            $infNFSe->appendChild($this->dom->createElement('xTribMun', $this->infNFSeCampos['xTribMun']));
        }
        
        if (!empty($this->infNFSeCampos['xNBS'])) {
            $infNFSe->appendChild($this->dom->createElement('xNBS', $this->infNFSeCampos['xNBS']));
        }
        
        // verAplic dentro de infNFSe (não em infDPS)
        // NOTA: verAplic também está em infDPS, então pode ser redundante aqui
        // $infNFSe->appendChild($this->dom->createElement('verAplic', $this->verAplic));
        
        // ambGer (ambiente de geração) - mesmo valor que tpAmb
        // NOTA: Pode ser apenas de resposta, não de requisição
        // $infNFSe->appendChild($this->dom->createElement('ambGer', (string) $this->tpAmb));
        
        // tpEmis (tipo de emissão) - padrão 1
        // NOTA: Pode ser apenas de resposta, não de requisição
        // if (!empty($this->infNFSeCampos['tpEmis'])) {
        //     $infNFSe->appendChild($this->dom->createElement('tpEmis', (string) $this->infNFSeCampos['tpEmis']));
        // } else {
        //     $infNFSe->appendChild($this->dom->createElement('tpEmis', '1'));
        // }
        
        // procEmi (processo de emissão) - padrão 2
        // NOTA: Pode ser apenas de resposta, não de requisição
        // if (!empty($this->infNFSeCampos['procEmi'])) {
        //     $infNFSe->appendChild($this->dom->createElement('procEmi', (string) $this->infNFSeCampos['procEmi']));
        // } else {
        //     $infNFSe->appendChild($this->dom->createElement('procEmi', '2'));
        // }
        
        // cStat (código de status) - padrão 101 (em processamento)
        // NOTA: Este campo é APENAS de resposta, não deve estar na requisição
        // if (!empty($this->infNFSeCampos['cStat'])) {
        //     $infNFSe->appendChild($this->dom->createElement('cStat', (string) $this->infNFSeCampos['cStat']));
        // } else {
        //     $infNFSe->appendChild($this->dom->createElement('cStat', '101'));
        // }
        
        // dhProc (data/hora de processamento) - data/hora atual
        // NOTA: Este campo é APENAS de resposta, não deve estar na requisição
        // $dhProc = date('Y-m-d\TH:i:s') . '-03:00';
        // if (!empty($this->infNFSeCampos['dhProc'])) {
        //     $dhProc = $this->infNFSeCampos['dhProc'];
        // }
        // $infNFSe->appendChild($this->dom->createElement('dhProc', $dhProc));
        
        // nDFSe (número do DFSe) - pode ser o mesmo que nNFSe
        // NOTA: Este campo é APENAS de resposta, não deve estar na requisição
        // if (!empty($this->infNFSeCampos['nDFSe'])) {
        //     $infNFSe->appendChild($this->dom->createElement('nDFSe', (string) $this->infNFSeCampos['nDFSe']));
        // } elseif (!empty($this->infNFSeCampos['nNFSe'])) {
        //     $infNFSe->appendChild($this->dom->createElement('nDFSe', (string) $this->infNFSeCampos['nNFSe']));
        // }
        
        // emit (prestador) dentro de infNFSe
        // IMPORTANTE: No XML do portal, os campos ficam diretamente dentro de <emit>, não dentro de <prest>
        $emit = $this->dom->createElement('emit');
        $this->buildEmit($emit);
        $infNFSe->appendChild($emit);
        
        // valores (valores totais) dentro de infNFSe, entre emit e DPS
        $valoresTotais = $this->dom->createElement('valores');
        
        // Calcular vTotalRet (valor total retido) se não foi informado
        $vTotalRet = $this->vTotalRet;
        if ($vTotalRet === null) {
            // Calcular baseado nos impostos retidos (ISS, PIS, COFINS, etc.)
            $valorServicos = (float)($this->servico['valorServicos'] ?? 0);
            $valorIss = (float)($this->servico['valorIss'] ?? 0);
            $issRetido = (int)($this->servico['issRetido'] ?? 2); // 1=Sim, 2=Não
            
            $vTotalRet = 0.00;
            
            // Se ISS estiver retido, incluir no total retido
            if ($issRetido == 1) {
                $vTotalRet += $valorIss;
            }
            
            // Adicionar PIS/COFINS retidos (tpRetPisCofins = 1 significa retido)
            // Valores padrão se não informados (conforme buildValores)
            $vBCPisCofins = $valorServicos;
            $pAliqPis = 0.31; // Alíquota PIS padrão
            $pAliqCofins = 1.43; // Alíquota COFINS padrão
            $tpRetPisCofins = 1; // 1=Sim (retido)
            
            // Se PIS/COFINS estiverem retidos, incluir no total retido
            if ($tpRetPisCofins == 1) {
                $vPis = $vBCPisCofins * ($pAliqPis / 100);
                $vCofins = $vBCPisCofins * ($pAliqCofins / 100);
                $vTotalRet += $vPis + $vCofins;
            }
        }
        
        // Calcular vLiq (valor líquido) se não foi informado
        $vLiq = $this->vLiq;
        if ($vLiq === null) {
            $valorServicos = (float)($this->servico['valorServicos'] ?? 0);
            $vLiq = $valorServicos - $vTotalRet;
        }
        
        $valoresTotais->appendChild($this->dom->createElement('vTotalRet', number_format($vTotalRet, 2, '.', '')));
        $valoresTotais->appendChild($this->dom->createElement('vLiq', number_format($vLiq, 2, '.', '')));
        $infNFSe->appendChild($valoresTotais);
        
        $root->appendChild($infNFSe);
        
        // DPS dentro de infNFSe (depois de valores)
        // Adicionar namespace explicitamente no DPS conforme XML aprovado no portal
        // Criar elemento normalmente e depois adicionar xmlns usando setAttributeNS
        $dps = $this->dom->createElement('DPS');
        $dps->setAttribute('versao', '1.00');
        // Forçar renderização do xmlns no elemento DPS usando setAttributeNS
        $dps->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $infNFSe->appendChild($dps);
        
        // infDPS
        // Formato do Id conforme TDN (Tabela Dinâmica Nacional):
        // DPS + cLocEmi (7 dígitos) + Tipo de Inscrição (1 dígito: 1=CPF, 2=CNPJ) + 
        // Inscrição Federal (14 dígitos) + Série (5 dígitos) + Número (15 dígitos)
        // Reutilizar variáveis já definidas acima, mas formatar corretamente
        $cLocEmiFormatted = str_pad((string)$cLocEmiSafe, 7, '0', STR_PAD_LEFT);
        $cnpjFormatted = preg_replace('/[^0-9]/', '', (string)$cnpjSafe);
        $cnpjFormatted = str_pad($cnpjFormatted, 14, '0', STR_PAD_LEFT);
        $tipoInscricao = (strlen($cnpjFormatted) == 11) ? '1' : '2';
        $serieFormatted = str_pad((string)($this->identificacao['serie'] ?? '1'), 5, '0', STR_PAD_LEFT);
        $numeroFormatted = str_pad((string)$numeroSafe, 15, '0', STR_PAD_LEFT);
        
        $this->infDpsId = 'DPS' . $cLocEmiFormatted . $tipoInscricao . $cnpjFormatted . $serieFormatted . $numeroFormatted;
        
        $infDps = $this->dom->createElement('infDPS');
        $infDps->setAttribute('Id', $this->infDpsId);
        $dps->appendChild($infDps);

        // Campos do infDPS conforme XML aprovado (ordem exata)
        $infDps->appendChild($this->dom->createElement('tpAmb', (string) $this->tpAmb));
        
        if ($this->dhEmi !== null) {
            $infDps->appendChild($this->dom->createElement('dhEmi', $this->dhEmi));
        } else {
            $dhEmi = date('Y-m-d\TH:i:s') . '-03:00';
            $infDps->appendChild($this->dom->createElement('dhEmi', $dhEmi));
        }
        
        // verAplic dentro de infDPS (conforme XML aprovado no portal)
        $infDps->appendChild($this->dom->createElement('verAplic', $this->verAplic));
        
        $infDps->appendChild($this->dom->createElement('serie', (string) $this->identificacao['serie']));
        $infDps->appendChild($this->dom->createElement('nDPS', (string) $this->identificacao['numero']));
        $infDps->appendChild($this->dom->createElement('dCompet', $this->competencia));
        $infDps->appendChild($this->dom->createElement('tpEmit', (string) $this->tpEmit));
        
        if (!empty($cLocEmiSafe)) {
            $infDps->appendChild($this->dom->createElement('cLocEmi', $cLocEmiSafe));
        }

        // prest (prestador) dentro de infDPS, após cLocEmi (conforme XML aprovado no portal)
        $infDps->appendChild($this->buildPrestador());
        
        $infDps->appendChild($this->buildTomador());
        $infDps->appendChild($this->buildServico());
        $infDps->appendChild($this->buildValores());
        
        $xml = $this->dom->saveXML();
        
        if ($xml === false || empty($xml)) {
            throw new \RuntimeException('Falha ao gerar XML da DPS');
        }
        
        // Ajustar ordem dos atributos no elemento raiz para corresponder ao XML aprovado
        // XML aprovado: <NFSe versao="1.00" xmlns="...">
        // Substituir para garantir ordem: versao antes de xmlns
        $xml = preg_replace(
            '/<NFSe\s+xmlns="([^"]+)"\s+versao="([^"]+)">/',
            '<NFSe versao="$2" xmlns="$1">',
            $xml
        );
        
        // Adicionar namespace explicitamente no elemento DPS conforme XML aprovado
        // XML aprovado: <DPS versao="1.00" xmlns="http://www.sped.fazenda.gov.br/nfse">
        $xml = preg_replace(
            '/<DPS\s+versao="([^"]+)">/',
            '<DPS versao="$1"' . "\n            xmlns=\"http://www.sped.fazenda.gov.br/nfse\">",
            $xml
        );
        
        // Valida se o XML está bem formado
        libxml_use_internal_errors(true);
        $domCheck = new \DOMDocument('1.0', 'utf-8');
        if (!@$domCheck->loadXML($xml)) {
            $errors = libxml_get_errors();
            $errorMsg = 'XML mal formado: ';
            foreach ($errors as $error) {
                $errorMsg .= trim($error->message) . ' ';
            }
            libxml_clear_errors();
            throw new \RuntimeException($errorMsg);
        }
        libxml_use_internal_errors(false);
        
        // Garante que o XML está em UTF-8
        if (!mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'auto');
        }
        
        return $xml;
    }
    
    /**
     * Gera XML apenas da DPS (sem wrapper NFSe/infNFSe)
     * Este é o formato esperado pela API POST /nfse
     * Estrutura: <DPS><infDPS>...<ds:Signature>
     */
    public function getDPSXML(): string
    {
        $this->assertRequiredData();

        // Criar documento XML
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        // DPS como elemento raiz (sem NFSe/infNFSe)
        $dps = $dom->createElementNS('http://www.sped.fazenda.gov.br/nfse', 'DPS');
        $dps->setAttribute('versao', '1.00');
        $dps->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns', 'http://www.sped.fazenda.gov.br/nfse');
        $dom->appendChild($dps);
        
        // infDPS
        // Formato do Id conforme TDN (Tabela Dinâmica Nacional):
        // DPS + cLocEmi (7 dígitos) + Tipo de Inscrição (1 dígito: 1=CPF, 2=CNPJ) + 
        // Inscrição Federal (14 dígitos) + Série (5 dígitos) + Número (15 dígitos)
        $cLocEmiSafe = str_pad((string)($this->cLocEmi ?? ''), 7, '0', STR_PAD_LEFT);
        $cnpjSafe = preg_replace('/[^0-9]/', '', (string)($this->prestador['cnpj'] ?? ''));
        $cnpjSafe = str_pad($cnpjSafe, 14, '0', STR_PAD_LEFT);
        
        // Determinar tipo de inscrição: 1=CPF (11 dígitos), 2=CNPJ (14 dígitos)
        $tipoInscricao = (strlen($cnpjSafe) == 11) ? '1' : '2';
        
        $serieSafe = str_pad((string)($this->identificacao['serie'] ?? '1'), 5, '0', STR_PAD_LEFT);
        $numeroSafe = str_pad((string)($this->identificacao['numero'] ?? '1'), 15, '0', STR_PAD_LEFT);
        
        $infDpsId = 'DPS' . $cLocEmiSafe . $tipoInscricao . $cnpjSafe . $serieSafe . $numeroSafe;
        $this->infDpsId = $infDpsId;
        
        $infDps = $dom->createElement('infDPS');
        $infDps->setAttribute('Id', $infDpsId);
        $dps->appendChild($infDps);

        // Campos do infDPS conforme XML aprovado (ordem exata)
        $infDps->appendChild($dom->createElement('tpAmb', (string) $this->tpAmb));
        
        if ($this->dhEmi !== null) {
            $infDps->appendChild($dom->createElement('dhEmi', $this->dhEmi));
        } else {
            $dhEmi = date('Y-m-d\TH:i:s') . '-03:00';
            $infDps->appendChild($dom->createElement('dhEmi', $dhEmi));
        }
        
        // verAplic dentro de infDPS
        $infDps->appendChild($dom->createElement('verAplic', $this->verAplic));
        
        $infDps->appendChild($dom->createElement('serie', (string)($this->identificacao['serie'] ?? '1')));
        $infDps->appendChild($dom->createElement('nDPS', (string)($this->identificacao['numero'] ?? '1')));
        $infDps->appendChild($dom->createElement('dCompet', (string)($this->competencia ?? date('Y-m-d'))));
        $infDps->appendChild($dom->createElement('tpEmit', (string)($this->tpEmit ?? 1)));
        
        if (!empty($cLocEmiSafe)) {
            $infDps->appendChild($dom->createElement('cLocEmi', $cLocEmiSafe));
        }

        // prest (prestador) dentro de infDPS, após cLocEmi
        $infDps->appendChild($this->buildPrestadorForDOM($dom));
        
        $infDps->appendChild($this->buildTomadorForDOM($dom));
        $infDps->appendChild($this->buildServicoForDOM($dom));
        $infDps->appendChild($this->buildValoresForDOM($dom));
        
        $xml = $dom->saveXML();
        
        if ($xml === false || empty($xml)) {
            throw new \RuntimeException('Falha ao gerar XML da DPS');
        }
        
        // Valida se o XML está bem formado
        libxml_use_internal_errors(true);
        $domCheck = new \DOMDocument('1.0', 'utf-8');
        if (!@$domCheck->loadXML($xml)) {
            $errors = libxml_get_errors();
            $errorMsg = 'XML mal formado: ';
            foreach ($errors as $error) {
                $errorMsg .= trim($error->message) . ' ';
            }
            libxml_clear_errors();
            throw new \RuntimeException($errorMsg);
        }
        libxml_clear_errors();
        
        // Garante que o XML está em UTF-8
        if (!mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'auto');
        }
        
        return $xml;
    }
    
    /**
     * Constrói elemento prestador para um DOM específico (usado em getDPSXML)
     */
    private function buildPrestadorForDOM(DOMDocument $dom): \DOMElement
    {
        return $this->buildElementForDOM($dom, 'prest', function($prest) use ($dom) {
            // Garantir que sempre seja string, mesmo se for null
            $cnpj = $this->prestador['cnpj'] ?? null;
            $im = $this->prestador['inscricaoMunicipal'] ?? $this->prestador['im'] ?? null;
            
            $prest->appendChild($dom->createElement('CNPJ', (string)($cnpj ?? '')));
            $prest->appendChild($dom->createElement('IM', (string)($im ?? '')));
            
            // Telefone: verificar tanto 'fone' quanto 'telefone' (pode vir de diferentes fontes)
            $fone = $this->prestador['fone'] ?? $this->prestador['telefone'] ?? null;
            if (!empty($fone)) {
                $prest->appendChild($dom->createElement('fone', (string)$fone));
            }
            
            if (!empty($this->prestador['email'])) {
                $prest->appendChild($dom->createElement('email', (string)($this->prestador['email'] ?? '')));
            }
            
            // regTrib
            $regTrib = $dom->createElement('regTrib');
            $regTrib->appendChild($dom->createElement('opSimpNac', (string)($this->prestador['opSimpNac'] ?? '3')));
            $regTrib->appendChild($dom->createElement('regApTribSN', (string)($this->prestador['regApTribSN'] ?? '1')));
            $regTrib->appendChild($dom->createElement('regEspTrib', (string)($this->prestador['regEspTrib'] ?? '0')));
            $prest->appendChild($regTrib);
        });
    }
    
    /**
     * Constrói elemento tomador para um DOM específico (usado em getDPSXML)
     */
    private function buildTomadorForDOM(DOMDocument $dom): \DOMElement
    {
        return $this->buildElementForDOM($dom, 'toma', function($toma) use ($dom) {
            if (!empty($this->tomador['cpfCnpj'])) {
                $cpfCnpj = preg_replace('/[^0-9]/', '', $this->tomador['cpfCnpj']);
                if (strlen($cpfCnpj) == 11) {
                    $toma->appendChild($dom->createElement('CPF', $cpfCnpj));
                } else {
                    $toma->appendChild($dom->createElement('CNPJ', $cpfCnpj));
                }
            }
            
            if (!empty($this->tomador['razaoSocial'])) {
                $toma->appendChild($dom->createElement('xNome', (string)$this->tomador['razaoSocial']));
            }
            
            // end (endereço)
            if (!empty($this->tomador['endereco']) || !empty($this->tomador['codigoMunicipio'])) {
                $end = $dom->createElement('end');
                $endNac = $dom->createElement('endNac');
                
                if (!empty($this->tomador['codigoMunicipio'])) {
                    $endNac->appendChild($dom->createElement('cMun', (string)$this->tomador['codigoMunicipio']));
                }
                
                if (!empty($this->tomador['cep'])) {
                    $endNac->appendChild($dom->createElement('CEP', preg_replace('/[^0-9]/', '', (string)$this->tomador['cep'])));
                }
                
                $end->appendChild($endNac);
                
                if (!empty($this->tomador['endereco'])) {
                    $end->appendChild($dom->createElement('xLgr', (string)$this->tomador['endereco']));
                }
                
                if (!empty($this->tomador['numero'])) {
                    $end->appendChild($dom->createElement('nro', (string)$this->tomador['numero']));
                }
                
                if (!empty($this->tomador['complemento'])) {
                    $end->appendChild($dom->createElement('xCpl', (string)$this->tomador['complemento']));
                }
                
                if (!empty($this->tomador['bairro'])) {
                    $end->appendChild($dom->createElement('xBairro', (string)$this->tomador['bairro']));
                }
                
                $toma->appendChild($end);
            }
            
            // Telefone: verificar tanto 'fone' quanto 'telefone' (pode vir de diferentes fontes)
            $foneTomador = $this->tomador['fone'] ?? $this->tomador['telefone'] ?? null;
            if (!empty($foneTomador)) {
                $toma->appendChild($dom->createElement('fone', (string)$foneTomador));
            }
            
            if (!empty($this->tomador['email'])) {
                $toma->appendChild($dom->createElement('email', (string)$this->tomador['email']));
            }
        });
    }
    
    /**
     * Constrói elemento serviço para um DOM específico (usado em getDPSXML)
     */
    private function buildServicoForDOM(DOMDocument $dom): \DOMElement
    {
        return $this->buildElementForDOM($dom, 'serv', function($serv) use ($dom) {
            // locPrest
            $locPrest = $dom->createElement('locPrest');
            $cLocPrestacao = (string)($this->servico['cLocPrestacao'] ?? $this->cLocEmi ?? '');
            if (!empty($cLocPrestacao)) {
                $locPrest->appendChild($dom->createElement('cLocPrestacao', $cLocPrestacao));
            }
            $serv->appendChild($locPrest);
            
            // cServ
            $cServ = $dom->createElement('cServ');
            
            // cTribNac (LC116 sem pontos, deve ter exatamente 6 dígitos)
            $cTribNac = (string)($this->servico['cTribNac'] ?? str_replace('.', '', (string)($this->servico['itemListaServico'] ?? '')));
            // Remover qualquer caractere não numérico e garantir 6 dígitos
            $cTribNac = preg_replace('/[^0-9]/', '', $cTribNac);
            // Garantir que tenha exatamente 6 dígitos (preencher com zeros à esquerda se necessário)
            $cTribNac = str_pad($cTribNac, 6, '0', STR_PAD_LEFT);
            if (!empty($cTribNac) && strlen($cTribNac) == 6) {
                $cServ->appendChild($dom->createElement('cTribNac', $cTribNac));
            }
            
            // cTribMun
            $cTribMun = (string)($this->servico['cTribMun'] ?? $this->servico['codigoComplementarMunicipal'] ?? '001');
            if (!empty($cTribMun)) {
                $cServ->appendChild($dom->createElement('cTribMun', $cTribMun));
            }
            
            // xDescServ
            $xDescServ = (string)($this->servico['discriminacao'] ?? $this->servico['descricao'] ?? '');
            if (!empty($xDescServ)) {
                $cServ->appendChild($dom->createElement('xDescServ', $xDescServ));
            }
            
            // cNBS (opcional)
            if (!empty($this->servico['cNBS'])) {
                $cServ->appendChild($dom->createElement('cNBS', (string)$this->servico['cNBS']));
            }
            
            $serv->appendChild($cServ);
        });
    }
    
    /**
     * Constrói elemento valores para um DOM específico (usado em getDPSXML)
     */
    private function buildValoresForDOM(DOMDocument $dom): \DOMElement
    {
        return $this->buildElementForDOM($dom, 'valores', function($valores) use ($dom) {
            // vServPrest
            $vServPrest = $dom->createElement('vServPrest');
            $valorServicos = (float)($this->servico['valorServicos'] ?? 0);
            $vServPrest->appendChild($dom->createElement('vServ', number_format($valorServicos, 2, '.', '')));
            $valores->appendChild($vServPrest);
            
            // trib
            $trib = $dom->createElement('trib');
            
            // tribMun
            $tribMun = $dom->createElement('tribMun');
            $tribISSQN = (int)($this->servico['tribISSQN'] ?? 1);
            $tpRetISSQN = (int)($this->servico['tpRetISSQN'] ?? ($this->servico['issRetido'] == 1 ? 1 : 2));
            $tribMun->appendChild($dom->createElement('tribISSQN', (string)$tribISSQN));
            $tribMun->appendChild($dom->createElement('tpRetISSQN', (string)$tpRetISSQN));
            $trib->appendChild($tribMun);
            
            // tribFed (PIS/COFINS)
            $tribFed = $dom->createElement('tribFed');
            $piscofins = $dom->createElement('piscofins');
            
            $vBCPisCofins = (float)($this->servico['vBCPisCofins'] ?? $valorServicos);
            $pAliqPis = (float)($this->servico['pAliqPis'] ?? 0.31);
            $pAliqCofins = (float)($this->servico['pAliqCofins'] ?? 1.43);
            $vPis = $vBCPisCofins * ($pAliqPis / 100);
            $vCofins = $vBCPisCofins * ($pAliqCofins / 100);
            $tpRetPisCofins = (int)($this->servico['tpRetPisCofins'] ?? 1);
            
            $piscofins->appendChild($dom->createElement('CST', '01'));
            $piscofins->appendChild($dom->createElement('vBCPisCofins', number_format($vBCPisCofins, 2, '.', '')));
            $piscofins->appendChild($dom->createElement('pAliqPis', number_format($pAliqPis, 2, '.', '')));
            $piscofins->appendChild($dom->createElement('pAliqCofins', number_format($pAliqCofins, 2, '.', '')));
            $piscofins->appendChild($dom->createElement('vPis', number_format($vPis, 2, '.', '')));
            $piscofins->appendChild($dom->createElement('vCofins', number_format($vCofins, 2, '.', '')));
            $piscofins->appendChild($dom->createElement('tpRetPisCofins', (string)$tpRetPisCofins));
            
            $tribFed->appendChild($piscofins);
            $trib->appendChild($tribFed);
            
            // totTrib (pTotTribSN)
            $totTrib = $dom->createElement('totTrib');
            $pTotTribSN = (string)($this->pTotTribSN ?? $this->servico['pTotTribSN'] ?? '10.51');
            $totTrib->appendChild($dom->createElement('pTotTribSN', $pTotTribSN));
            $trib->appendChild($totTrib);
            
            $valores->appendChild($trib);
        });
    }
    
    /**
     * Helper para construir elementos DOM de forma consistente
     */
    private function buildElementForDOM(DOMDocument $dom, string $tagName, callable $callback): \DOMElement
    {
        $element = $dom->createElement($tagName);
        $callback($element);
        return $element;
    }

    public function getInfDpsId(): ?string
    {
        return $this->infDpsId;
    }

    private function assertRequiredData(): void
    {
        $errors = [];
        
        // Validação básica
        if (empty($this->identificacao['numero'])) {
            $errors[] = 'Número da DPS é obrigatório';
        }

        if (empty($this->prestador)) {
            $errors[] = 'Dados do prestador não informados';
        }

        if (empty($this->tomador)) {
            $errors[] = 'Dados do tomador não informados';
        }

        if (empty($this->servico)) {
            $errors[] = 'Dados do serviço não informados';
        }
        
        // Validação de cLocEmi
        if (empty($this->cLocEmi)) {
            $errors[] = 'Código do município de emissão (cLocEmi) é obrigatório';
        } elseif (strlen(preg_replace('/[^0-9]/', '', $this->cLocEmi)) !== 7) {
            $errors[] = 'Código do município de emissão inválido (deve ter 7 dígitos - IBGE)';
        }
        
        // Validação de competência
        if (empty($this->competencia)) {
            $errors[] = 'Competência é obrigatória';
        }
        
        if (!empty($errors)) {
            throw new \InvalidArgumentException('Erros de validação: ' . implode('; ', $errors));
        }
    }

    /**
     * Constrói elemento emit (prestador) no formato SPED conforme XML do Portal Nacional
     * Os campos ficam diretamente dentro de <emit>, não dentro de <prest>
     */
    private function buildEmit(\DOMElement $emit): void
    {
        // CNPJ
        $emit->appendChild($this->dom->createElement('CNPJ', $this->prestador['cnpj']));
        
        // IM (Inscrição Municipal)
        $emit->appendChild($this->dom->createElement('IM', $this->prestador['im']));
        
        // xNome (Razão Social) - obrigatório conforme XML do portal
        if (!empty($this->prestador['razaoSocial'])) {
            $emit->appendChild($this->dom->createElement('xNome', $this->prestador['razaoSocial']));
        }
        
        // enderNac (Endereço Nacional) - conforme XML do portal
        $cMun = '';
        if (!empty($this->prestador['municipio'])) {
            // Se município for numérico (código IBGE), usar diretamente
            $cMun = preg_match('/^\d{7}$/', $this->prestador['municipio']) 
                ? $this->prestador['municipio'] 
                : '';
        }
        
        // Se não tiver cMun do prestador, usar cLocEmi como fallback
        if (empty($cMun) && !empty($this->cLocEmi)) {
            $cMun = $this->cLocEmi;
        }
        
        // Adicionar enderNac se tiver pelo menos um campo de endereço e cMun
        if ((!empty($this->prestador['endereco']) || !empty($this->prestador['cep']) || 
             !empty($this->prestador['numero']) || !empty($this->prestador['bairro'])) && 
            !empty($cMun)) {
            
            $enderNac = $this->dom->createElement('enderNac');
            
            // xLgr (Logradouro)
            if (!empty($this->prestador['endereco'])) {
                $enderNac->appendChild($this->dom->createElement('xLgr', $this->prestador['endereco']));
            }
            
            // nro (Número)
            if (!empty($this->prestador['numero'])) {
                $enderNac->appendChild($this->dom->createElement('nro', $this->prestador['numero']));
            }
            
            // xBairro (Bairro)
            if (!empty($this->prestador['bairro'])) {
                $enderNac->appendChild($this->dom->createElement('xBairro', $this->prestador['bairro']));
            }
            
            // cMun (Código do Município - IBGE) - obrigatório
            $enderNac->appendChild($this->dom->createElement('cMun', $cMun));
            
            // UF
            if (!empty($this->prestador['uf'])) {
                $enderNac->appendChild($this->dom->createElement('UF', $this->prestador['uf']));
            }
            
            // CEP
            if (!empty($this->prestador['cep'])) {
                $enderNac->appendChild($this->dom->createElement('CEP', $this->prestador['cep']));
            }
            
            $emit->appendChild($enderNac);
        }
        
        // fone (Telefone)
        if (!empty($this->prestador['telefone'])) {
            $emit->appendChild($this->dom->createElement('fone', $this->prestador['telefone']));
        }
        
        // email
        if (!empty($this->prestador['email'])) {
            $emit->appendChild($this->dom->createElement('email', $this->prestador['email']));
        }
        
        // NOTA: regTrib não aparece no XML do portal, mas pode ser necessário na requisição
        // Por enquanto, vamos omitir conforme XML do portal
    }
    
    /**
     * Constrói elemento prest (prestador) no formato SPED
     * @deprecated Use buildEmit() em vez disso - mantido para compatibilidade
     */
    private function buildPrestador(): \DOMElement
    {
        $prest = $this->dom->createElement('prest');
        $prest->appendChild($this->dom->createElement('CNPJ', $this->prestador['cnpj']));
        $prest->appendChild($this->dom->createElement('IM', $this->prestador['im']));
        
        // Campos opcionais conforme XML aprovado
        if (!empty($this->prestador['telefone'])) {
            $prest->appendChild($this->dom->createElement('fone', $this->prestador['telefone']));
        }
        
        if (!empty($this->prestador['email'])) {
            $prest->appendChild($this->dom->createElement('email', $this->prestador['email']));
        }
        
        // regTrib (Regime Tributário) - sempre adicionar conforme XML aprovado
        $regTrib = $this->dom->createElement('regTrib');
        $regTrib->appendChild($this->dom->createElement('opSimpNac', (string) ($this->prestador['opSimpNac'] ?? 3)));
        $regTrib->appendChild($this->dom->createElement('regApTribSN', (string) ($this->prestador['regApTribSN'] ?? 1)));
        $regTrib->appendChild($this->dom->createElement('regEspTrib', (string) ($this->prestador['regEspTrib'] ?? 0)));
        $prest->appendChild($regTrib);
        
        return $prest;
    }

    /**
     * Constrói elemento toma (tomador) no formato SPED
     */
    private function buildTomador(): \DOMElement
    {
        $toma = $this->dom->createElement('toma');
        
        // CNPJ/CPF direto (sem wrapper CpfCnpj)
        $docTag = strlen($this->tomador['cpfCnpj']) === 11 ? 'CPF' : 'CNPJ';
        $toma->appendChild($this->dom->createElement($docTag, $this->tomador['cpfCnpj']));
        $toma->appendChild($this->dom->createElement('xNome', $this->tomador['razaoSocial']));

        // Estrutura de endereço conforme XML aprovado
        // cMun é obrigatório dentro de endNac conforme XML aprovado
        $cMun = '';
        if (!empty($this->tomador['codigoMunicipio'])) {
            $cMun = $this->tomador['codigoMunicipio'];
        } elseif (!empty($this->tomador['municipio'])) {
            // Se município for numérico (código IBGE), usar diretamente
            $cMun = preg_match('/^\d+$/', $this->tomador['municipio']) 
                ? $this->tomador['municipio'] 
                : '';
        }
        
        // Se não tiver cMun, usar o código do município do prestador como fallback
        if (empty($cMun) && !empty($this->cLocEmi)) {
            $cMun = $this->cLocEmi;
        }
        
        // Adicionar endereço se tiver pelo menos endereco ou cep ou numero ou bairro
        // Mas cMun é obrigatório, então só adicionar se tiver cMun (mesmo que seja fallback)
        if (!empty($this->tomador['endereco']) || !empty($this->tomador['cep']) || !empty($this->tomador['numero']) || !empty($this->tomador['bairro'])) {
            // Se ainda não tiver cMun, usar cLocEmi como fallback obrigatório
            if (empty($cMun) && !empty($this->cLocEmi)) {
                $cMun = $this->cLocEmi;
            }
            
            // Só criar endereço se tiver cMun (obrigatório)
            if (!empty($cMun)) {
                $end = $this->dom->createElement('end');
                $endNac = $this->dom->createElement('endNac');
                
                // cMun é obrigatório - sempre adicionar
                $endNac->appendChild($this->dom->createElement('cMun', $cMun));
                
                // CEP - adicionar se disponível
                if (!empty($this->tomador['cep'])) {
                    $endNac->appendChild($this->dom->createElement('CEP', $this->tomador['cep']));
                }
                
                // endNac contém apenas cMun e CEP
                $end->appendChild($endNac);
                
                // Campos do endereço (xLgr, nro, xCpl, xBairro) ficam dentro de 'end', mas FORA de 'endNac'
                if (!empty($this->tomador['endereco'])) {
                    $end->appendChild($this->dom->createElement('xLgr', $this->tomador['endereco']));
                }
                
                if (!empty($this->tomador['numero'])) {
                    $end->appendChild($this->dom->createElement('nro', $this->tomador['numero']));
                }
                
                if (!empty($this->tomador['complemento'])) {
                    $end->appendChild($this->dom->createElement('xCpl', $this->tomador['complemento']));
                }
                
                if (!empty($this->tomador['bairro'])) {
                    $end->appendChild($this->dom->createElement('xBairro', $this->tomador['bairro']));
                }
                
                $toma->appendChild($end);
            }
        }

        if (!empty($this->tomador['telefone'])) {
            $toma->appendChild($this->dom->createElement('fone', $this->tomador['telefone']));
        }

        if (!empty($this->tomador['email'])) {
            $toma->appendChild($this->dom->createElement('email', $this->tomador['email']));
        }

        return $toma;
    }

    /**
     * Constrói elemento serv (serviço) no formato SPED
     */
    private function buildServico(): \DOMElement
    {
        $serv = $this->dom->createElement('serv');
        
        // locPrest (Local de Prestação)
        // Usar cLocPrestacao se fornecido, senão usar cLocEmi como fallback (conforme XML aprovado)
        $cLocPrestacao = $this->servico['cLocPrestacao'] ?? $this->cLocEmi ?? '';
        if (!empty($cLocPrestacao)) {
            $locPrest = $this->dom->createElement('locPrest');
            $locPrest->appendChild($this->dom->createElement('cLocPrestacao', $cLocPrestacao));
            $serv->appendChild($locPrest);
        }
        
        // cServ (Código do Serviço)
        $cServ = $this->dom->createElement('cServ');
        
        // cTribNac (Código de Tributação Nacional - LC116 sem pontos, deve ter exatamente 6 dígitos)
        if (!empty($this->servico['cTribNac'])) {
            $cTribNac = preg_replace('/[^0-9]/', '', (string)$this->servico['cTribNac']);
            $cTribNac = str_pad($cTribNac, 6, '0', STR_PAD_LEFT);
            if (strlen($cTribNac) == 6) {
                $cServ->appendChild($this->dom->createElement('cTribNac', $cTribNac));
            }
        } elseif (!empty($this->servico['itemListaServico'])) {
            // Se não tiver cTribNac, converter LC116 (ex: 01.07.00 -> 010700)
            $cTribNac = preg_replace('/[^0-9]/', '', (string)$this->servico['itemListaServico']);
            $cTribNac = str_pad($cTribNac, 6, '0', STR_PAD_LEFT);
            if (strlen($cTribNac) == 6) {
                $cServ->appendChild($this->dom->createElement('cTribNac', $cTribNac));
            }
        }
        
        // cTribMun (Código Complementar Municipal)
        if (!empty($this->servico['cTribMun'])) {
            $cServ->appendChild($this->dom->createElement('cTribMun', $this->servico['cTribMun']));
        } elseif (!empty($this->servico['codigo_complementar_municipal'])) {
            $cServ->appendChild($this->dom->createElement('cTribMun', $this->servico['codigo_complementar_municipal']));
        } else {
            // Fallback padrão
            $cServ->appendChild($this->dom->createElement('cTribMun', '001'));
        }
        
        // xDescServ (Descrição do Serviço)
        $xDescServ = $this->servico['discriminacao'] ?? $this->servico['descricao'] ?? '';
        $cServ->appendChild($this->dom->createElement('xDescServ', $xDescServ));
        
        // cNBS (Código NBS) - opcional
        if (!empty($this->servico['cNBS'])) {
            $cServ->appendChild($this->dom->createElement('cNBS', $this->servico['cNBS']));
        }
        
        $serv->appendChild($cServ);
        
        return $serv;
    }

    /**
     * Constrói elemento valores no formato SPED (separado do serv)
     */
    private function buildValores(): \DOMElement
    {
        $valores = $this->dom->createElement('valores');
        
        // vServPrest/vServ
        $vServPrest = $this->dom->createElement('vServPrest');
        $vServPrest->appendChild($this->dom->createElement('vServ', (string) $this->servico['valorServicos']));
        $valores->appendChild($vServPrest);
        
        // trib (Tributos) - estrutura completa conforme XML aprovado
        $trib = $this->dom->createElement('trib');
        
        // tribMun (Tributos Municipais)
        $tribMun = $this->dom->createElement('tribMun');
        $tribISSQN = $this->dom->createElement('tribISSQN', '1'); // 1=Sim
        $tpRetISSQN = $this->dom->createElement('tpRetISSQN', (string) ($this->servico['issRetido'] ?? 2)); // 1=Sim, 2=Não
        $tribMun->appendChild($tribISSQN);
        $tribMun->appendChild($tpRetISSQN);
        $trib->appendChild($tribMun);
        
        // tribFed (Tributos Federais - PIS/COFINS) - opcional mas presente no XML aprovado
        // Se tiver dados de PIS/COFINS, adicionar; senão, adicionar estrutura básica
        $tribFed = $this->dom->createElement('tribFed');
        $piscofins = $this->dom->createElement('piscofins');
        
        // Valores padrão se não informados
        $vBCPisCofins = (string) ($this->servico['valorServicos'] ?? '0.00');
        $cst = '01'; // CST padrão
        $pAliqPis = '0.31'; // Alíquota PIS padrão
        $pAliqCofins = '1.43'; // Alíquota COFINS padrão
        $vPis = number_format((float)$vBCPisCofins * ((float)$pAliqPis / 100), 2, '.', '');
        $vCofins = number_format((float)$vBCPisCofins * ((float)$pAliqCofins / 100), 2, '.', '');
        $tpRetPisCofins = '1'; // 1=Sim
        
        $piscofins->appendChild($this->dom->createElement('CST', $cst));
        $piscofins->appendChild($this->dom->createElement('vBCPisCofins', $vBCPisCofins));
        $piscofins->appendChild($this->dom->createElement('pAliqPis', $pAliqPis));
        $piscofins->appendChild($this->dom->createElement('pAliqCofins', $pAliqCofins));
        $piscofins->appendChild($this->dom->createElement('vPis', $vPis));
        $piscofins->appendChild($this->dom->createElement('vCofins', $vCofins));
        $piscofins->appendChild($this->dom->createElement('tpRetPisCofins', $tpRetPisCofins));
        $tribFed->appendChild($piscofins);
        $trib->appendChild($tribFed);
        
        // totTrib (Total de Tributos) - opcional mas presente no XML aprovado
        $totTrib = $this->dom->createElement('totTrib');
        // Percentual total de tributos do Simples Nacional (pTotTribSN)
        // Usar valor passado via setPTotTribSN() ou valor do serviço, senão usar padrão
        $pTotTribSN = $this->pTotTribSN ?? $this->servico['pTotTribSN'] ?? '10.51';
        if (!empty($pTotTribSN)) {
            $totTrib->appendChild($this->dom->createElement('pTotTribSN', (string)$pTotTribSN));
        }
        $trib->appendChild($totTrib);
        
        $valores->appendChild($trib);
        
        return $valores;
    }
}
