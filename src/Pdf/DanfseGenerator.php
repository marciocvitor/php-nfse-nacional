<?php

declare(strict_types=1);

namespace NFSeNacional\Pdf;

use NFSeNacional\Common\Response;
use TCPDF;

/**
 * Gerador de DANFSe (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica) em PDF
 * 
 * @package NFSeNacional\Pdf
 */
class DanfseGenerator
{
    private TCPDF $pdf;

    public function __construct()
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->configurePdf();
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

        $this->adicionarPagina();
        $this->renderizarCabecalho($dados);
        $this->renderizarDadosPrestador($dados);
        $this->renderizarDadosTomador($dados);
        $this->renderizarDadosServico($dados);
        $this->renderizarValores($dados);
        $this->renderizarRodape($dados);

        return $this->pdf->Output('', 'S');
    }

    /**
     * Gera PDF e salva em arquivo
     */
    public function gerarArquivo(Response $response, string $caminho, array $dadosAdicionais = []): void
    {
        $pdfContent = $this->gerar($response, $dadosAdicionais);
        file_put_contents($caminho, $pdfContent);
    }

    /**
     * Gera PDF e retorna como string base64
     */
    public function gerarBase64(Response $response, array $dadosAdicionais = []): string
    {
        $pdfContent = $this->gerar($response, $dadosAdicionais);
        return base64_encode($pdfContent);
    }

    private function configurePdf(): void
    {
        $this->pdf->SetCreator('PHP NFSe Nacional');
        $this->pdf->SetAuthor('PHP NFSe Nacional');
        $this->pdf->SetTitle('DANFSe - Documento Auxiliar da NFSe');
        $this->pdf->SetSubject('DANFSe');
        $this->pdf->SetKeywords('NFSe, DANFSe, Nota Fiscal de Serviço');
        
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(10, 10, 10);
        $this->pdf->SetAutoPageBreak(true, 10);
    }

    private function adicionarPagina(): void
    {
        $this->pdf->AddPage();
    }

    private function renderizarCabecalho(array $dados): void
    {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->SetFillColor(200, 200, 200);
        $this->pdf->Rect(10, 10, 190, 15, 'F');
        
        $this->pdf->SetXY(10, 12);
        $this->pdf->Cell(190, 10, 'DANFSe - Documento Auxiliar da Nota Fiscal de Serviço Eletrônica', 0, 1, 'C');
        
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetXY(10, 22);
        $this->pdf->Cell(95, 6, 'Número: ' . ($dados['numero'] ?? '---'), 0, 0, 'L');
        $this->pdf->Cell(95, 6, 'Código de Verificação: ' . ($dados['codigoVerificacao'] ?? '---'), 0, 1, 'R');
        
        $this->pdf->SetXY(10, 28);
        $this->pdf->Cell(95, 6, 'Data de Emissão: ' . ($dados['dataEmissao'] ?? '---'), 0, 0, 'L');
        $this->pdf->Cell(95, 6, 'Competência: ' . ($dados['competencia'] ?? '---'), 0, 1, 'R');
    }

    private function renderizarDadosPrestador(array $dados): void
    {
        $y = 38;
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Rect(10, $y, 190, 8, 'F');
        $this->pdf->SetXY(10, $y + 2);
        $this->pdf->Cell(190, 6, 'DADOS DO PRESTADOR DE SERVIÇOS', 0, 1, 'L');
        
        $y += 10;
        $this->pdf->SetFont('helvetica', '', 10);
        $prestador = $dados['prestador'] ?? [];
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Razão Social: ' . ($prestador['razaoSocial'] ?? '---'), 0, 0, 'L');
        $this->pdf->Cell(95, 6, 'CNPJ: ' . $this->formatarCnpj($prestador['cnpj'] ?? ''), 0, 1, 'L');
        
        $y += 6;
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Inscrição Municipal: ' . ($prestador['im'] ?? '---'), 0, 1, 'L');
    }

    private function renderizarDadosTomador(array $dados): void
    {
        $y = $this->pdf->GetY() + 5;
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Rect(10, $y, 190, 8, 'F');
        $this->pdf->SetXY(10, $y + 2);
        $this->pdf->Cell(190, 6, 'DADOS DO TOMADOR DE SERVIÇOS', 0, 1, 'L');
        
        $y += 10;
        $this->pdf->SetFont('helvetica', '', 10);
        $tomador = $dados['tomador'] ?? [];
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Razão Social/Nome: ' . ($tomador['razaoSocial'] ?? '---'), 0, 0, 'L');
        
        $documento = $tomador['cpfCnpj'] ?? '';
        $tipoDoc = strlen($documento) === 11 ? 'CPF' : 'CNPJ';
        $docFormatado = $tipoDoc === 'CPF' ? $this->formatarCpf($documento) : $this->formatarCnpj($documento);
        $this->pdf->Cell(95, 6, $tipoDoc . ': ' . $docFormatado, 0, 1, 'L');
        
        if (!empty($tomador['email'])) {
            $y += 6;
            $this->pdf->SetXY(10, $y);
            $this->pdf->Cell(190, 6, 'E-mail: ' . $tomador['email'], 0, 1, 'L');
        }
    }

    private function renderizarDadosServico(array $dados): void
    {
        $y = $this->pdf->GetY() + 5;
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Rect(10, $y, 190, 8, 'F');
        $this->pdf->SetXY(10, $y + 2);
        $this->pdf->Cell(190, 6, 'DADOS DO SERVIÇO', 0, 1, 'L');
        
        $y += 10;
        $this->pdf->SetFont('helvetica', '', 10);
        $servico = $dados['servico'] ?? [];
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Item da Lista de Serviço: ' . ($servico['itemListaServico'] ?? '---'), 0, 0, 'L');
        $this->pdf->Cell(95, 6, 'Código do Município: ' . ($servico['codigoMunicipio'] ?? '---'), 0, 1, 'L');
        
        $y += 6;
        $this->pdf->SetXY(10, $y);
        $discriminacao = $servico['discriminacao'] ?? '---';
        $this->pdf->MultiCell(190, 6, 'Discriminação: ' . $discriminacao, 0, 'L');
    }

    private function renderizarValores(array $dados): void
    {
        $y = $this->pdf->GetY() + 5;
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->Rect(10, $y, 190, 8, 'F');
        $this->pdf->SetXY(10, $y + 2);
        $this->pdf->Cell(190, 6, 'VALORES', 0, 1, 'L');
        
        $y += 10;
        $this->pdf->SetFont('helvetica', '', 10);
        $servico = $dados['servico'] ?? [];
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Valor dos Serviços: ' . $this->formatarMoeda($servico['valorServicos'] ?? 0), 0, 0, 'L');
        $this->pdf->Cell(95, 6, 'Alíquota: ' . $this->formatarPercentual($servico['aliquota'] ?? 0), 0, 1, 'L');
        
        $y += 6;
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(95, 6, 'Valor do ISS: ' . $this->formatarMoeda($servico['valorIss'] ?? 0), 0, 0, 'L');
        
        $issRetido = $servico['issRetido'] ?? 2;
        $statusRetido = $issRetido === 1 ? 'Sim' : 'Não';
        $this->pdf->Cell(95, 6, 'ISS Retido: ' . $statusRetido, 0, 1, 'L');
    }

    private function renderizarRodape(array $dados): void
    {
        $y = $this->pdf->GetY() + 10;
        $this->pdf->SetFont('helvetica', '', 8);
        $this->pdf->SetTextColor(100, 100, 100);
        
        $codigoVerificacao = $dados['codigoVerificacao'] ?? '';
        $urlConsulta = 'https://www.nfse.gov.br/consulta';
        
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(190, 5, 'Código de Verificação: ' . $codigoVerificacao, 0, 1, 'C');
        
        $y += 5;
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(190, 5, 'Consulte a autenticidade em: ' . $urlConsulta, 0, 1, 'C');
        
        $y += 5;
        $this->pdf->SetXY(10, $y);
        $this->pdf->Cell(190, 5, 'Este documento é uma representação gráfica da NFSe e não substitui o XML original.', 0, 1, 'C');
    }

    private function extrairDados(string $xml): array
    {
        $simpleXml = @simplexml_load_string($xml);
        if ($simpleXml === false) {
            return [];
        }

        $dados = [];
        $ns = $simpleXml->getNamespaces(true);
        $simpleXml->registerXPathNamespace('nfse', $ns[''] ?? 'http://www.abrasf.org.br/nfse.xsd');

        // Extrair número
        $numeroNode = $simpleXml->xpath('//NumeroNfse');
        $dados['numero'] = !empty($numeroNode) ? (string) $numeroNode[0] : null;

        // Extrair código de verificação
        $codigoNode = $simpleXml->xpath('//CodigoVerificacao');
        $dados['codigoVerificacao'] = !empty($codigoNode) ? (string) $codigoNode[0] : null;

        // Extrair data de emissão
        $dataNode = $simpleXml->xpath('//DataEmissao');
        $dados['dataEmissao'] = !empty($dataNode) ? (string) $dataNode[0] : null;

        // Extrair competência
        $competenciaNode = $simpleXml->xpath('//Competencia');
        $dados['competencia'] = !empty($competenciaNode) ? (string) $competenciaNode[0] : null;

        // Extrair prestador
        $prestadorNode = $simpleXml->xpath('//Prestador');
        if (!empty($prestadorNode)) {
            $prestador = $prestadorNode[0];
            $cnpjNode = $prestador->xpath('.//Cnpj');
            $imNode = $prestador->xpath('.//InscricaoMunicipal');
            $dados['prestador'] = [
                'cnpj' => !empty($cnpjNode) ? (string) $cnpjNode[0] : '',
                'im' => !empty($imNode) ? (string) $imNode[0] : '',
                'razaoSocial' => $dados['prestador']['razaoSocial'] ?? ''
            ];
        }

        // Extrair tomador
        $tomadorNode = $simpleXml->xpath('//Tomador');
        if (!empty($tomadorNode)) {
            $tomador = $tomadorNode[0];
            $cpfNode = $tomador->xpath('.//Cpf');
            $cnpjNode = $tomador->xpath('.//Cnpj');
            $razaoNode = $tomador->xpath('.//RazaoSocial');
            $emailNode = $tomador->xpath('.//Email');
            
            $dados['tomador'] = [
                'cpfCnpj' => !empty($cpfNode) ? (string) $cpfNode[0] : (!empty($cnpjNode) ? (string) $cnpjNode[0] : ''),
                'razaoSocial' => !empty($razaoNode) ? (string) $razaoNode[0] : '',
                'email' => !empty($emailNode) ? (string) $emailNode[0] : ''
            ];
        }

        // Extrair serviço
        $servicoNode = $simpleXml->xpath('//Servico');
        if (!empty($servicoNode)) {
            $servico = $servicoNode[0];
            $dados['servico'] = [
                'itemListaServico' => (string) ($servico->ItemListaServico ?? ''),
                'discriminacao' => (string) ($servico->Discriminacao ?? ''),
                'codigoMunicipio' => (string) ($servico->CodigoMunicipio ?? ''),
                'valorServicos' => (float) ($servico->Valores->ValorServicos ?? 0),
                'valorIss' => (float) ($servico->Valores->ValorIss ?? 0),
                'aliquota' => (float) ($servico->Valores->Aliquota ?? 0),
                'issRetido' => (int) ($servico->IssRetido ?? 2)
            ];
        }

        return $dados;
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

    private function formatarMoeda(float $valor): string
    {
        return 'R$ ' . number_format($valor, 2, ',', '.');
    }

    private function formatarPercentual(float $percentual): string
    {
        return number_format($percentual * 100, 2, ',', '.') . '%';
    }
}

