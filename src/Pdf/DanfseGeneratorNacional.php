<?php

declare(strict_types=1);

namespace NFSeNacional\Pdf;

use NFSeNacional\Common\Response;
use Mpdf\Mpdf;

/**
 * Gerador de DANFSe (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica) em PDF
 * Usa mPDF para gerar PDF com layout similar ao oficial do Portal Nacional
 * 
 * @package NFSeNacional\Pdf
 */
class DanfseGeneratorNacional
{
    private Mpdf $pdf;

    public function __construct()
    {
        $this->pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_top' => 3,
            'margin_left' => 6,
            'margin_right' => 6,
            'margin_bottom' => 3,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir(),
            'ignore_invalid_utf8' => false, // Não ignorar UTF-8 inválido, converter corretamente
            'autoPageBreak' => false, // Desabilita quebra automática de página
        ]);
        
        $this->pdf->SetTitle('DANFSe - Documento Auxiliar da NFSe');
        $this->pdf->SetAuthor('PHP NFSe Nacional');
        $this->pdf->SetCreator('PHP NFSe Nacional');
    }

    /**
     * Gera PDF do DANFSe a partir do Response da NFSe
     */
    public function gerar(Response $response, array $dadosAdicionais = []): string
    {
        if (!$response->sucesso || !$response->xml) {
            throw new \InvalidArgumentException('Response inválido ou sem XML da NFSe');
        }

        $dados = $this->extrairDados($response->xml);
        $dados = array_merge($dados, $dadosAdicionais);

        $html = $this->gerarHTML($dados);
        
        $this->pdf->WriteHTML($html);
        
        return $this->pdf->Output('', 'S');
    }

    /**
     * Gera HTML completo do DANFSe
     */
    private function gerarHTML(array $dados): string
    {
        $css = $this->getCSS();
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $css . '</head><body style="margin: 0; padding: 0;">';
        
        // Cabeçalho
        $html .= $this->renderizarCabecalho($dados);
        
        // Tabela de Identificação
        $html .= $this->renderizarTabelaIdentificacao($dados);
        
        // Dados do Prestador e Tomador lado a lado (economiza espaço)
        $html .= '<div class="two-columns">';
        $html .= '<div>' . $this->renderizarPrestador($dados) . '</div>';
        $html .= '<div>' . $this->renderizarTomador($dados) . '</div>';
        $html .= '</div>';
        
        // Dados do Serviço
        $html .= $this->renderizarServico($dados);
        
        // Tributação Municipal e Federal lado a lado
        $html .= '<div class="two-columns">';
        $html .= '<div>' . $this->renderizarTributacaoMunicipal($dados) . '</div>';
        $html .= '<div>' . $this->renderizarTributacaoFederal($dados) . '</div>';
        $html .= '</div>';
        
        // Totais
        $html .= $this->renderizarTotais($dados);
        
        // Informações Complementares
        $html .= $this->renderizarInformacoesComplementares($dados);
        
        // Rodapé
        $html .= $this->renderizarRodape($dados);
        
        $html .= '</body></html>';
        
        return $html;
    }

    /**
     * CSS para o PDF - Otimizado para caber em uma página
     */
    private function getCSS(): string
    {
        return '<style>
            body {
                font-family: Arial, sans-serif;
                font-size: 7.5pt;
                color: #000;
                margin: 0;
                padding: 0;
            }
            .header {
                background-color: #e0e0e0;
                padding: 4px;
                text-align: center;
                font-weight: bold;
                font-size: 9pt;
                margin-bottom: 3px;
            }
            .section-title {
                background-color: #d0d0d0;
                padding: 2px 4px;
                font-weight: bold;
                font-size: 8pt;
                margin-top: 4px;
                margin-bottom: 2px;
            }
            .chave-acesso {
                font-size: 8pt;
                font-weight: bold;
                text-align: center;
                margin: 3px 0;
                padding: 3px;
                background-color: #f0f0f0;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 3px;
                font-size: 7pt;
            }
            table td {
                padding: 2px 3px;
                border: 1px solid #ccc;
                font-size: 7pt;
            }
            table th {
                padding: 2px 3px;
                border: 1px solid #ccc;
                background-color: #e8e8e8;
                font-weight: bold;
                font-size: 7pt;
            }
            .label {
                font-weight: bold;
            }
            .footer {
                margin-top: 5px;
                padding-top: 3px;
                border-top: 1px solid #ccc;
                font-size: 7pt;
                text-align: center;
                color: #666;
            }
            .valor {
                text-align: right;
            }
            p {
                margin: 2px 0;
                font-size: 7pt;
            }
            .two-columns {
                display: table;
                width: 100%;
                margin-bottom: 3px;
            }
            .two-columns > div {
                display: table-cell;
                width: 50%;
                vertical-align: top;
                padding-right: 5px;
            }
            .two-columns > div:last-child {
                padding-right: 0;
                padding-left: 5px;
            }
            .two-columns .section-title {
                margin-top: 2px;
            }
        </style>';
    }

    /**
     * Renderiza cabeçalho
     */
    private function renderizarCabecalho(array $dados): string
    {
        $chaveAcesso = $dados['chaveAcesso'] ?? $dados['codigoVerificacao'] ?? '---';
        
        $html = '<div class="header">';
        $html .= 'Nota Fiscal de Serviço eletrônica DANFSe v1.0<br>';
        $html .= 'Documento Auxiliar da NFS-e';
        $html .= '</div>';
        
        $html .= '<div class="chave-acesso">';
        $html .= 'Chave de Acesso da NFS-e: ' . $chaveAcesso;
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Renderiza tabela de identificação
     */
    private function renderizarTabelaIdentificacao(array $dados): string
    {
        $numero = $dados['numero'] ?? '---';
        $competencia = $this->formatarData($dados['competencia'] ?? '---');
        $dataEmissao = $this->formatarDataHora($dados['dataEmissao'] ?? $dados['dhEmi'] ?? '---');
        $numeroDps = $dados['numeroDps'] ?? $dados['nDPS'] ?? '---';
        $serieDps = $dados['serieDps'] ?? $dados['serie'] ?? '---';
        $dataEmissaoDps = $this->formatarDataHora($dados['dhEmi'] ?? '---');
        
        $html = '<table>';
        $html .= '<tr>';
        $html .= '<th>Número da NFS-e</th>';
        $html .= '<th>Competência da NFS-e</th>';
        $html .= '<th>Data e Hora da emissão da NFS-e</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td>' . $numero . '</td>';
        $html .= '<td>' . $competencia . '</td>';
        $html .= '<td>' . $dataEmissao . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>Número da DPS</th>';
        $html .= '<th>Série da DPS</th>';
        $html .= '<th>Data e Hora da emissão da DPS</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td>' . $numeroDps . '</td>';
        $html .= '<td>' . $serieDps . '</td>';
        $html .= '<td>' . $dataEmissaoDps . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza dados do prestador
     */
    private function renderizarPrestador(array $dados): string
    {
        $prestador = $dados['prestador'] ?? [];
        $cnpj = $this->formatarCnpj($prestador['cnpj'] ?? '');
        $im = $prestador['im'] ?? $prestador['inscricaoMunicipal'] ?? '---';
        $razao = $prestador['razaoSocial'] ?? $prestador['xNome'] ?? '---';
        $fone = $this->formatarTelefone($prestador['fone'] ?? '---');
        $email = $prestador['email'] ?? '---';
        $endereco = $this->formatarEndereco($prestador['endereco'] ?? []);
        $municipio = $prestador['municipio'] ?? '---';
        if ($municipio === '---' && !empty($prestador['endereco']['cMun'])) {
            // Se não encontrou nome, usar código IBGE + UF
            $municipio = $prestador['endereco']['cMun'] . ' - ' . ($prestador['endereco']['UF'] ?? '');
        }
        $regime = $prestador['regimeTributario'] ?? '---';
        
        // Se o regime não foi extraído do XML, tentar determinar a partir dos dados adicionais
        if ($regime === '---' && isset($dadosAdicionais['prestador_opSimpNac'])) {
            $opSimpNac = $dadosAdicionais['prestador_opSimpNac'] ?? '';
            $regApTribSN = $dadosAdicionais['prestador_regApTribSN'] ?? '';
            
            if ($opSimpNac == '3') {
                $regime = 'Simples Nacional';
                if ($regApTribSN == '1') {
                    $regime .= ' - Regime de Apuração Tributária pelo SN';
                }
            } elseif ($opSimpNac == '1') {
                $regime = 'Não Optante pelo Simples Nacional';
            } elseif ($opSimpNac == '2') {
                $regime = 'Optante pelo Simples Nacional';
            }
        }
        
        $html = '<div class="section-title">EMITENTE DA NFS-e (Prestador do Serviço)</div>';
        $html .= '<table>';
        $html .= '<tr><td class="label" style="width: 35%;">CNPJ/CPF/NIF:</td><td>' . htmlspecialchars($cnpj, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Inscrição Municipal:</td><td>' . htmlspecialchars($im, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Telefone:</td><td>' . htmlspecialchars($fone, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Nome / Nome Empresarial:</td><td>' . htmlspecialchars($razao, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">E-mail:</td><td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Endereço:</td><td>' . htmlspecialchars($endereco, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Município:</td><td>' . htmlspecialchars($municipio, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Regime Tributário:</td><td>' . htmlspecialchars($regime, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza dados do tomador
     */
    private function renderizarTomador(array $dados): string
    {
        $tomador = $dados['tomador'] ?? [];
        $cpfCnpj = $tomador['cpfCnpj'] ?? '';
        $docFormatado = strlen($cpfCnpj) == 11 ? $this->formatarCpf($cpfCnpj) : $this->formatarCnpj($cpfCnpj);
        $razao = $tomador['razaoSocial'] ?? $tomador['xNome'] ?? '---';
        $email = $tomador['email'] ?? '---';
        $fone = $this->formatarTelefone($tomador['fone'] ?? '---');
        $endereco = $this->formatarEndereco($tomador['endereco'] ?? []);
        
        $html = '<div class="section-title">TOMADOR DO SERVIÇO</div>';
        $html .= '<table>';
        $html .= '<tr><td class="label" style="width: 35%;">CNPJ/CPF:</td><td>' . htmlspecialchars($docFormatado, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Nome / Nome Empresarial:</td><td>' . htmlspecialchars($razao, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">E-mail:</td><td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Telefone:</td><td>' . htmlspecialchars($fone, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><td class="label">Endereço:</td><td>' . htmlspecialchars($endereco, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza dados do serviço
     */
    private function renderizarServico(array $dados): string
    {
        $servico = $dados['servico'] ?? [];
        $cTribNac = $servico['cTribNac'] ?? $servico['cTribNacional'] ?? '---';
        $cTribMun = $servico['cTribMun'] ?? $servico['cTribMunicipal'] ?? '---';
        $xDescServ = $servico['xDescServ'] ?? $servico['discriminacao'] ?? '---';
        $localPrestacao = $servico['localPrestacao'] ?? '---';
        $nbs = $servico['cNBS'] ?? $servico['nbs'] ?? '---';
        
        $html = '<div class="section-title">SERVIÇO PRESTADO</div>';
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<th>Código de Tributação Nacional</th>';
        $html .= '<th>Código de Tributação Municipal</th>';
        $html .= '<th>Local da Prestação</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td>' . htmlspecialchars($cTribNac, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($cTribMun, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($localPrestacao, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '<p style="margin: 1px 0;"><strong>Inscrição do Serviço:</strong> ' . htmlspecialchars($xDescServ, ENT_QUOTES, 'UTF-8') . '</p>';
        
        return $html;
    }

    /**
     * Renderiza tributação municipal
     */
    private function renderizarTributacaoMunicipal(array $dados): string
    {
        $trib = $dados['tributacao'] ?? [];
        $tribMun = $trib['tribMun'] ?? [];
        $vServ = $this->formatarMoeda($dados['valorServico'] ?? $dados['vServ'] ?? 0);
        $bcIssqn = $this->formatarMoeda($tribMun['bcIssqn'] ?? 0);
        $aliquota = $this->formatarPercentual($tribMun['aliquota'] ?? 0);
        $issRetido = ($tribMun['issRetido'] ?? 0) == 1 ? 'Sim' : 'Não';
        $issApurado = $this->formatarMoeda($tribMun['issApurado'] ?? 0);
        
        $html = '<div class="section-title">TRIBUTAÇÃO MUNICIPAL</div>';
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<th>Valor do Serviço</th>';
        $html .= '<th>BC ISSQN</th>';
        $html .= '<th>Alíquota</th>';
        $html .= '<th>Retenção ISSQN</th>';
        $html .= '<th>ISSQN Apurado</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="valor">' . $vServ . '</td>';
        $html .= '<td class="valor">' . $bcIssqn . '</td>';
        $html .= '<td>' . $aliquota . '</td>';
        $html .= '<td>' . $issRetido . '</td>';
        $html .= '<td class="valor">' . $issApurado . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza tributação federal
     */
    private function renderizarTributacaoFederal(array $dados): string
    {
        $trib = $dados['tributacao'] ?? [];
        $tribFed = $trib['tribFed'] ?? [];
        $piscofins = $tribFed['piscofins'] ?? [];
        $vPis = $this->formatarMoeda($piscofins['vPis'] ?? 0);
        $vCofins = $this->formatarMoeda($piscofins['vCofins'] ?? 0);
        $vTotalRet = $this->formatarMoeda($dados['vTotalRet'] ?? 0);
        
        $html = '<div class="section-title">TRIBUTAÇÃO FEDERAL</div>';
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<th>PIS</th>';
        $html .= '<th>COFINS</th>';
        $html .= '<th>Retenção PIS/COFINS</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="valor">' . $vPis . '</td>';
        $html .= '<td class="valor">' . $vCofins . '</td>';
        $html .= '<td class="valor">' . $vTotalRet . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza totais
     */
    private function renderizarTotais(array $dados): string
    {
        $vServ = $this->formatarMoeda($dados['valorServico'] ?? $dados['vServ'] ?? 0);
        $vLiq = $this->formatarMoeda($dados['valorTotal'] ?? $dados['vLiq'] ?? 0);
        
        $html = '<div class="section-title">VALOR TOTAL DA NFS-E</div>';
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<th>Valor do Serviço</th>';
        $html .= '<th>Valor Total</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="valor">' . $vServ . '</td>';
        $html .= '<td class="valor"><strong>' . $vLiq . '</strong></td>';
        $html .= '</tr>';
        $html .= '</table>';
        
        return $html;
    }

    /**
     * Renderiza informações complementares
     */
    private function renderizarInformacoesComplementares(array $dados): string
    {
        $servico = $dados['servico'] ?? [];
        $nbs = $servico['cNBS'] ?? $servico['nbs'] ?? '';
        
        $html = '<div class="section-title">INFORMAÇÕES COMPLEMENTARES</div>';
        if (!empty($nbs)) {
            $html .= '<p style="margin: 1px 0;"><strong>NBS:</strong> ' . $nbs . '</p>';
        }
        
        return $html;
    }

    /**
     * Renderiza rodapé
     */
    private function renderizarRodape(array $dados): string
    {
        $chaveAcesso = $dados['chaveAcesso'] ?? $dados['codigoVerificacao'] ?? '---';
        
        $html = '<div class="footer">';
        $html .= '<p style="margin: 1px 0;"><strong>Código de Verificação:</strong> ' . $chaveAcesso . '</p>';
        $html .= '<p style="margin: 1px 0;">Consulte a autenticidade em: <a href="https://www.nfse.gov.br/consulta">https://www.nfse.gov.br/consulta</a></p>';
        $html .= '<p style="margin: 1px 0;">Este documento é uma representação gráfica da NFSe e não substitui o XML original.</p>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Extrai dados do XML SPED
     */
    private function extrairDados(string $xml): array
    {
        try {
            $dom = new \DOMDocument('1.0', 'UTF-8');
            // Garantir que o XML está em UTF-8
            if (!mb_check_encoding($xml, 'UTF-8')) {
                $xml = mb_convert_encoding($xml, 'UTF-8', 'ISO-8859-1');
            }
            if (!@$dom->loadXML($xml)) {
                return [];
            }

            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('sped', 'http://www.sped.fazenda.gov.br/nfse');
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

            $dados = [];

            // Chave de acesso (Id da infNFSe)
            $idNodes = $xpath->query('//sped:infNFSe/@Id | //infNFSe/@Id');
            $dados['chaveAcesso'] = $idNodes->length > 0 ? str_replace('NFS', '', $idNodes->item(0)->nodeValue) : '';

            // Número da NFS-e
            $numeroNodes = $xpath->query('//sped:nNFSe | //nNFSe');
            $dados['numero'] = $numeroNodes->length > 0 ? trim($numeroNodes->item(0)->nodeValue) : '';

            // Série
            $serieNodes = $xpath->query('//sped:serie | //serie');
            $dados['serie'] = $serieNodes->length > 0 ? trim($serieNodes->item(0)->nodeValue) : '';

            // Competência
            $compNodes = $xpath->query('//sped:dCompet | //dCompet');
            $dados['competencia'] = $compNodes->length > 0 ? trim($compNodes->item(0)->nodeValue) : '';

            // Data/Hora de emissão
            $dhEmiNodes = $xpath->query('//sped:dhEmi | //dhEmi');
            $dados['dhEmi'] = $dhEmiNodes->length > 0 ? trim($dhEmiNodes->item(0)->nodeValue) : '';
            
            $dhProcNodes = $xpath->query('//sped:dhProc | //dhProc');
            $dados['dataEmissao'] = $dhProcNodes->length > 0 ? trim($dhProcNodes->item(0)->nodeValue) : $dados['dhEmi'];

            // Número e série da DPS
            $nDpsNodes = $xpath->query('//sped:nDPS | //nDPS');
            $dados['numeroDps'] = $nDpsNodes->length > 0 ? trim($nDpsNodes->item(0)->nodeValue) : '';
            $dados['nDPS'] = $dados['numeroDps'];

            // Emitente (pode estar em infNFSe/emit ou DPS/prest)
            $emitNodes = $xpath->query('//sped:infNFSe//sped:emit | //infNFSe//emit');
            $prestadorNodes = $xpath->query('//sped:prest | //prest');
            
            // Usar emit se existir, senão usar prest
            $prest = null;
            if ($emitNodes->length > 0) {
                $prest = $emitNodes->item(0);
            } elseif ($prestadorNodes->length > 0) {
                $prest = $prestadorNodes->item(0);
            }
            
            if ($prest) {
                $dados['prestador'] = [
                    'cnpj' => $this->getNodeValue($xpath, $prest, './/sped:CNPJ | .//CNPJ'),
                    'im' => $this->getNodeValue($xpath, $prest, './/sped:IM | .//IM'),
                    'xNome' => $this->getNodeValue($xpath, $prest, './/sped:xNome | .//xNome'),
                    'fone' => $this->getNodeValue($xpath, $prest, './/sped:fone | .//fone'),
                    'email' => $this->getNodeValue($xpath, $prest, './/sped:email | .//email'),
                ];
                
                // Regime Tributário do prestador
                $regTribNodes = $xpath->query('.//sped:regTrib | .//regTrib', $prest);
                if ($regTribNodes->length > 0) {
                    $regTrib = $regTribNodes->item(0);
                    $opSimpNac = $this->getNodeValue($xpath, $regTrib, './/sped:opSimpNac | .//opSimpNac');
                    $regApTribSN = $this->getNodeValue($xpath, $regTrib, './/sped:regApTribSN | .//regApTribSN');
                    $regEspTrib = $this->getNodeValue($xpath, $regTrib, './/sped:regEspTrib | .//regEspTrib');
                    
                    // Determinar regime tributário
                    $regimeTexto = '---';
                    if ($opSimpNac == '3') {
                        $regimeTexto = 'Simples Nacional';
                        if ($regApTribSN == '1') {
                            $regimeTexto .= ' - Regime de Apuração Tributária pelo SN';
                        }
                    } elseif ($opSimpNac == '1') {
                        $regimeTexto = 'Não Optante pelo Simples Nacional';
                    } elseif ($opSimpNac == '2') {
                        $regimeTexto = 'Optante pelo Simples Nacional';
                    }
                    
                    $dados['prestador']['regimeTributario'] = $regimeTexto;
                }
                
                // Endereço do prestador (pode estar em emit/enderNac ou prest/enderNac)
                $enderNodes = $xpath->query('.//sped:enderNac | .//enderNac', $prest);
                if ($enderNodes->length > 0) {
                    $ender = $enderNodes->item(0);
                    $cMun = $this->getNodeValue($xpath, $ender, './/sped:cMun | .//cMun');
                    $uf = $this->getNodeValue($xpath, $ender, './/sped:UF | .//UF');
                    
                    $dados['prestador']['endereco'] = [
                        'xLgr' => $this->getNodeValue($xpath, $ender, './/sped:xLgr | .//xLgr'),
                        'nro' => $this->getNodeValue($xpath, $ender, './/sped:nro | .//nro'),
                        'xBairro' => $this->getNodeValue($xpath, $ender, './/sped:xBairro | .//xBairro'),
                        'cMun' => $cMun,
                        'UF' => $uf,
                        'CEP' => $this->getNodeValue($xpath, $ender, './/sped:CEP | .//CEP'),
                    ];
                    
                    // Buscar nome do município pelo código IBGE (se disponível)
                    if (!empty($cMun)) {
                        $dados['prestador']['municipio'] = $this->buscarNomeMunicipio($cMun, $uf);
                    }
                }
            }

            // Tomador
            $tomadorNodes = $xpath->query('//sped:toma | //toma');
            if ($tomadorNodes->length > 0) {
                $toma = $tomadorNodes->item(0);
                $dados['tomador'] = [
                    'cpfCnpj' => $this->getNodeValue($xpath, $toma, './/sped:CNPJ | .//CNPJ') ?: $this->getNodeValue($xpath, $toma, './/sped:CPF | .//CPF'),
                    'xNome' => $this->getNodeValue($xpath, $toma, './/sped:xNome | .//xNome'),
                    'fone' => $this->getNodeValue($xpath, $toma, './/sped:fone | .//fone'),
                    'email' => $this->getNodeValue($xpath, $toma, './/sped:email | .//email'),
                ];
                
                // Endereço do tomador
                $enderNodes = $xpath->query('.//sped:end//sped:endNac | .//end//endNac', $toma);
                if ($enderNodes->length > 0) {
                    $ender = $enderNodes->item(0);
                    $dados['tomador']['endereco'] = [
                        'xLgr' => $this->getNodeValue($xpath, $toma, './/sped:xLgr | .//xLgr'),
                        'nro' => $this->getNodeValue($xpath, $toma, './/sped:nro | .//nro'),
                        'xCpl' => $this->getNodeValue($xpath, $toma, './/sped:xCpl | .//xCpl'),
                        'xBairro' => $this->getNodeValue($xpath, $toma, './/sped:xBairro | .//xBairro'),
                        'cMun' => $this->getNodeValue($xpath, $ender, './/sped:cMun | .//cMun'),
                        'CEP' => $this->getNodeValue($xpath, $ender, './/sped:CEP | .//CEP'),
                    ];
                }
            }

            // Serviço
            $servNodes = $xpath->query('//sped:serv | //serv');
            if ($servNodes->length > 0) {
                $serv = $servNodes->item(0);
                $cServNodes = $xpath->query('.//sped:cServ | .//cServ', $serv);
                if ($cServNodes->length > 0) {
                    $cServ = $cServNodes->item(0);
                    $dados['servico'] = [
                        'cTribNac' => $this->getNodeValue($xpath, $cServ, './/sped:cTribNac | .//cTribNac'),
                        'cTribMun' => $this->getNodeValue($xpath, $cServ, './/sped:cTribMun | .//cTribMun'),
                        'xDescServ' => $this->getNodeValue($xpath, $cServ, './/sped:xDescServ | .//xDescServ'),
                        'cNBS' => $this->getNodeValue($xpath, $cServ, './/sped:cNBS | .//cNBS'),
                    ];
                }
                
                $locPrestNodes = $xpath->query('.//sped:locPrest//sped:cLocPrestacao | .//locPrest//cLocPrestacao', $serv);
                if ($locPrestNodes->length > 0) {
                    $dados['servico']['localPrestacao'] = trim($locPrestNodes->item(0)->nodeValue);
                }
            }

            // Valores
            $valoresNodes = $xpath->query('//sped:valores | //valores');
            if ($valoresNodes->length > 0) {
                $valores = $valoresNodes->item(0);
                $dados['vServ'] = (float)$this->getNodeValue($xpath, $valores, './/sped:vServ | .//vServ');
                $dados['valorServico'] = $dados['vServ'];
                
                $vLiqNodes = $xpath->query('.//sped:vLiq | .//vLiq', $valores);
                if ($vLiqNodes->length > 0) {
                    $dados['vLiq'] = (float)trim($vLiqNodes->item(0)->nodeValue);
                    $dados['valorTotal'] = $dados['vLiq'];
                }
                
                $vTotalRetNodes = $xpath->query('.//sped:vTotalRet | .//vTotalRet', $valores);
                if ($vTotalRetNodes->length > 0) {
                    $dados['vTotalRet'] = (float)trim($vTotalRetNodes->item(0)->nodeValue);
                }
                
                // Tributação
                $tribNodes = $xpath->query('.//sped:trib | .//trib', $valores);
                if ($tribNodes->length > 0) {
                    $trib = $tribNodes->item(0);
                    
                    // Tributação Municipal
                    $tribMunNodes = $xpath->query('.//sped:tribMun | .//tribMun', $trib);
                    if ($tribMunNodes->length > 0) {
                        $tribMun = $tribMunNodes->item(0);
                        $dados['tributacao']['tribMun'] = [
                            'issRetido' => (int)$this->getNodeValue($xpath, $tribMun, './/sped:tpRetISSQN | .//tpRetISSQN'),
                        ];
                    }
                    
                    // Tributação Federal
                    $tribFedNodes = $xpath->query('.//sped:tribFed | .//tribFed', $trib);
                    if ($tribFedNodes->length > 0) {
                        $tribFed = $tribFedNodes->item(0);
                        $piscofinsNodes = $xpath->query('.//sped:piscofins | .//piscofins', $tribFed);
                        if ($piscofinsNodes->length > 0) {
                            $piscofins = $piscofinsNodes->item(0);
                            $dados['tributacao']['tribFed']['piscofins'] = [
                                'vPis' => (float)$this->getNodeValue($xpath, $piscofins, './/sped:vPis | .//vPis'),
                                'vCofins' => (float)$this->getNodeValue($xpath, $piscofins, './/sped:vCofins | .//vCofins'),
                            ];
                        }
                    }
                }
            }

            return $dados;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Helper para obter valor de nó
     */
    private function getNodeValue(\DOMXPath $xpath, \DOMNode $context, string $query): string
    {
        $nodes = $xpath->query($query, $context);
        if ($nodes->length > 0) {
            $value = trim($nodes->item(0)->nodeValue);
            // Garantir que o valor está em UTF-8
            if (!mb_check_encoding($value, 'UTF-8')) {
                $value = mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
            }
            return $value;
        }
        return '';
    }

    /**
     * Busca nome do município pelo código IBGE
     */
    private function buscarNomeMunicipio(string $codigoIBGE, string $uf = ''): string
    {
        try {
            // Tentar buscar do banco de dados se disponível
            if (class_exists('Db_conn')) {
                $Db_conn = new \Db_conn();
                $link = $Db_conn->OpenConnectionNewI();
                
                // Tentar buscar na tabela municipio se existir
                $query = "SELECT m_nome FROM municipio WHERE m_codigo_ibge = '$codigoIBGE' LIMIT 1";
                $result = $link->query($query);
                if ($result && $row = $result->fetch_assoc()) {
                    $nome = $row['m_nome'] ?? '';
                    if (!empty($nome)) {
                        $Db_conn->CloseConnectionNewI($link);
                        return $nome . ($uf ? ' - ' . $uf : '');
                    }
                }
                
                $Db_conn->CloseConnectionNewI($link);
            }
        } catch (\Exception $e) {
            // Ignora erros de banco
        }
        
        // Se não encontrou, retorna código IBGE + UF
        return $codigoIBGE . ($uf ? ' - ' . $uf : '');
    }

    /**
     * Formata endereço
     */
    private function formatarEndereco(array $endereco): string
    {
        $parts = [];
        if (!empty($endereco['xLgr'])) $parts[] = $endereco['xLgr'];
        if (!empty($endereco['nro'])) $parts[] = $endereco['nro'];
        if (!empty($endereco['xCpl'])) $parts[] = $endereco['xCpl'];
        if (!empty($endereco['xBairro'])) $parts[] = $endereco['xBairro'];
        if (!empty($endereco['CEP'])) $parts[] = 'CEP: ' . $this->formatarCep($endereco['CEP']);
        
        return implode(', ', $parts);
    }

    private function formatarCnpj(string $cnpj): string
    {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3) . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    private function formatarCpf(string $cpf): string
    {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }

    private function formatarCep(string $cep): string
    {
        $cep = preg_replace('/[^0-9]/', '', $cep);
        if (strlen($cep) !== 8) {
            return $cep;
        }
        return substr($cep, 0, 5) . '-' . substr($cep, 5, 3);
    }

    private function formatarTelefone(string $telefone): string
    {
        if (empty($telefone) || $telefone === '---') {
            return '---';
        }
        
        // Remove tudo que não é número
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        
        // Se não tiver 10 ou 11 dígitos, retorna sem formatação
        $len = strlen($telefone);
        if ($len < 10 || $len > 11) {
            return $telefone;
        }
        
        // Telefone fixo: (XX) XXXX-XXXX (10 dígitos)
        if ($len === 10) {
            return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 4) . '-' . substr($telefone, 6, 4);
        }
        
        // Celular: (XX) XXXXX-XXXX (11 dígitos)
        return '(' . substr($telefone, 0, 2) . ') ' . substr($telefone, 2, 5) . '-' . substr($telefone, 7, 4);
    }

    private function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function formatarPercentual(float $percentual): string
    {
        return number_format($percentual, 2, ',', '.') . '%';
    }

    private function formatarData(string $data): string
    {
        if (empty($data) || $data === '---') {
            return '---';
        }
        try {
            $dt = new \DateTime($data);
            return $dt->format('d/m/Y');
        } catch (\Exception $e) {
            return $data;
        }
    }

    private function formatarDataHora(string $dataHora): string
    {
        if (empty($dataHora) || $dataHora === '---') {
            return '---';
        }
        try {
            $dt = new \DateTime($dataHora);
            return $dt->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return $dataHora;
        }
    }
}

