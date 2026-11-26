<?php

declare(strict_types=1);

namespace NFSeNacional\Pdf;

use NFSeNacional\Common\Response;
use NFSeNacional\Exception\ValidationException;
use Mpdf\Mpdf;

/**
 * Gerador de DANFSe (Documento Auxiliar da Nota Fiscal de Serviço Eletrônica) em PDF
 * Usa mPDF para gerar PDF com layout similar ao oficial do Portal Nacional
 *
 * Cabeçalho (layout de referência do portal nacional):
 *  - logo "NFS-e Nota Fiscal de Serviço eletrônica": embutido na biblioteca (src/Pdf/imagens/nfse-logo.png),
 *    carregado automaticamente;
 *  - QR Code de consulta pública: gerado automaticamente a partir da chave de acesso;
 *  - brasão da prefeitura: OPCIONAL e fornecido por quem usa a biblioteca (setBrasao() ou a opção
 *    'brasao' do construtor), pois não consta no XML da NFS-e. Sem brasão, o cabeçalho sai sem ele.
 *
 * Padrão exigido para o brasão (ver BRASAO_*):
 *  - formato: PNG (assinatura PNG válida; JPEG, GIF e demais formatos são recusados);
 *  - dimensões máximas: 300 x 300 pixels (recomendado 192 x 192, como no painel do portal nacional);
 *  - tamanho máximo do arquivo: 60 KB (61.440 bytes, o mesmo limite do painel do portal nacional);
 *  - arquivo íntegro (é decodificado por completo na validação).
 * Brasão fora do padrão NÃO é carregado e setBrasao() lança ValidationException com mensagem em
 * português explicando o problema e o padrão esperado.
 *
 * @package NFSeNacional\Pdf
 */
class DanfseGeneratorNacional
{
    /** Largura máxima do brasão, em pixels. */
    public const BRASAO_LARGURA_MAXIMA = 300;

    /** Altura máxima do brasão, em pixels. */
    public const BRASAO_ALTURA_MAXIMA = 300;

    /** Dimensão recomendada do brasão (192 x 192), igual à do painel do portal nacional. */
    public const BRASAO_DIMENSAO_RECOMENDADA = 192;

    /** Tamanho máximo do arquivo do brasão, em bytes (60 KB). */
    public const BRASAO_TAMANHO_MAXIMO_BYTES = 61440;

    /** Consulta pública da NFS-e (conteúdo do QR Code). */
    private const URL_CONSULTA_PUBLICA = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    private Mpdf $pdf;

    /** Conteúdo binário do brasão (PNG já validado) informado por quem usa a biblioteca. */
    private ?string $brasao = null;

    /**
     * @param array{brasao?: string|null} $opcoes
     *        brasao: caminho de um arquivo PNG, conteúdo binário do PNG ou data URI (data:image/png;base64,...),
     *        dentro do padrão descrito na documentação da classe.
     *
     * @throws ValidationException Se o brasão informado estiver fora do padrão
     */
    public function __construct(array $opcoes = [])
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

        if (!empty($opcoes['brasao'])) {
            $this->setBrasao($opcoes['brasao']);
        }
    }

    /**
     * Informa o brasão da prefeitura exibido no cabeçalho (opcional; sem ele o layout segue normalmente).
     *
     * Padrão: PNG, até 300 x 300 pixels (recomendado 192 x 192) e até 60 KB, arquivo íntegro.
     * Brasão fora do padrão não é carregado: lança ValidationException e o brasão anterior é mantido.
     *
     * @param string|null $brasao Caminho do arquivo PNG, conteúdo binário do PNG ou data URI base64; null remove o brasão
     * @throws ValidationException Com a explicação em português do que está errado e do padrão esperado
     */
    public function setBrasao(?string $brasao): self
    {
        if ($brasao === null || $brasao === '') {
            $this->brasao = null;

            return $this;
        }

        $this->brasao = $this->validarBrasao($brasao);

        return $this;
    }

    /** Texto do padrão exigido para o brasão, usado nas mensagens de erro. */
    public static function padraoBrasao(): string
    {
        return sprintf(
            'PNG, no máximo %d x %d pixels (recomendado %d x %d) e até %d KB.',
            self::BRASAO_LARGURA_MAXIMA,
            self::BRASAO_ALTURA_MAXIMA,
            self::BRASAO_DIMENSAO_RECOMENDADA,
            self::BRASAO_DIMENSAO_RECOMENDADA,
            intdiv(self::BRASAO_TAMANHO_MAXIMO_BYTES, 1024)
        );
    }

    /**
     * Valida o brasão e devolve o conteúdo binário do PNG.
     *
     * @throws ValidationException
     */
    private function validarBrasao(string $origem): string
    {
        $padrao = ' Padrão esperado: ' . self::padraoBrasao();
        $bytes = $this->lerBrasao($origem, $padrao);

        $limite = self::BRASAO_TAMANHO_MAXIMO_BYTES;
        if (strlen($bytes) > $limite) {
            throw new ValidationException(sprintf(
                'Brasão inválido: o arquivo tem %s KB e excede o limite de %d KB.%s',
                number_format(strlen($bytes) / 1024, 1, ',', ''),
                intdiv($limite, 1024),
                $padrao
            ));
        }

        if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            throw new ValidationException(
                'Brasão inválido: o formato não é PNG (recebido: ' . $this->descreverFormato($bytes) . ').' . $padrao
            );
        }

        $info = @getimagesizefromstring($bytes);
        if ($info === false || @imagecreatefromstring($bytes) === false) {
            throw new ValidationException('Brasão inválido: o arquivo PNG está corrompido ou incompleto e não pôde ser lido.' . $padrao);
        }

        [$largura, $altura] = $info;
        if ($largura > self::BRASAO_LARGURA_MAXIMA || $altura > self::BRASAO_ALTURA_MAXIMA) {
            throw new ValidationException(sprintf(
                'Brasão inválido: a imagem tem %d x %d pixels e excede o máximo de %d x %d.%s',
                $largura,
                $altura,
                self::BRASAO_LARGURA_MAXIMA,
                self::BRASAO_ALTURA_MAXIMA,
                $padrao
            ));
        }

        return $bytes;
    }

    /**
     * Resolve a origem do brasão (data URI, caminho de arquivo ou conteúdo binário) para bytes.
     *
     * @throws ValidationException
     */
    private function lerBrasao(string $origem, string $padrao): string
    {
        if (str_starts_with($origem, 'data:')) {
            if (!preg_match('#^data:([\w/+.-]*)(;base64)?,(.*)$#s', $origem, $partes) || $partes[2] === '') {
                throw new ValidationException('Brasão inválido: o data URI deve estar em base64 (data:image/png;base64,...).' . $padrao);
            }
            if (strtolower($partes[1]) !== 'image/png') {
                throw new ValidationException(
                    'Brasão inválido: o formato não é PNG (data URI informado como "' . $partes[1] . '").' . $padrao
                );
            }
            $bytes = base64_decode($partes[3], true);
            if ($bytes === false || $bytes === '') {
                throw new ValidationException('Brasão inválido: o conteúdo base64 do data URI está corrompido.' . $padrao);
            }

            return $bytes;
        }

        // Texto curto, sem bytes de controle: é tratado como caminho de arquivo
        if (strlen($origem) <= 1024 && !preg_match('/[\x00-\x08\x0E-\x1F]/', $origem)) {
            if (!is_file($origem) || !is_readable($origem)) {
                throw new ValidationException('Brasão inválido: arquivo não encontrado ou sem permissão de leitura (' . $origem . ').' . $padrao);
            }
            $bytes = file_get_contents($origem);
            if ($bytes === false || $bytes === '') {
                throw new ValidationException('Brasão inválido: o arquivo está vazio ou não pôde ser lido.' . $padrao);
            }

            return $bytes;
        }

        return $origem;
    }

    private function descreverFormato(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'JPEG',
            str_starts_with($bytes, 'GIF8') => 'GIF',
            str_starts_with($bytes, 'BM') => 'BMP',
            str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP' => 'WEBP',
            default => 'formato desconhecido',
        };
    }

    /** Data URI de uma imagem embutida em src/Pdf/imagens/; vazio se o arquivo não existir. */
    private function imagemEmbutida(string $nome): string
    {
        $arquivo = __DIR__ . '/imagens/' . $nome;
        $conteudo = is_file($arquivo) ? file_get_contents($arquivo) : false;

        return $conteudo === false ? '' : 'data:image/png;base64,' . base64_encode($conteudo);
    }

    /** QR Code (PNG em data URI) da consulta pública da NFS-e, gerado a partir da chave de acesso. */
    private function qrCodeConsulta(string $chaveAcesso): string
    {
        if (!preg_match('/^[0-9]{50}$/', $chaveAcesso) || !class_exists(\TCPDF2DBarcode::class)) {
            return '';
        }

        try {
            // O TCPDF sorteia (rand) quais máscaras avaliar; fixamos a semente pela chave para que o mesmo
            // QR seja sempre gerado (PDF reprodutível) e depois devolvemos o gerador a uma semente aleatória.
            mt_srand(crc32($chaveAcesso));
            try {
                $codigo = (new \TCPDF2DBarcode(self::URL_CONSULTA_PUBLICA . $chaveAcesso, 'QRCODE,M'))->getBarcodeArray();
            } finally {
                mt_srand();
            }
            $modulos = (int) ($codigo['num_rows'] ?? 0);
            if ($modulos === 0 || empty($codigo['bcode'])) {
                return '';
            }

            // PNG opaco (fundo branco, 4 px por módulo, margem de 2 módulos), sem canal alfa
            $escala = 4;
            $margem = 2 * $escala;
            $lado = $modulos * $escala + 2 * $margem;
            $imagem = imagecreatetruecolor($lado, $lado);
            imagefill($imagem, 0, 0, imagecolorallocate($imagem, 255, 255, 255));
            $preto = imagecolorallocate($imagem, 0, 0, 0);
            foreach ($codigo['bcode'] as $linha => $colunas) {
                foreach ($colunas as $coluna => $valor) {
                    if ($valor == 1) {
                        $x = $margem + $coluna * $escala;
                        $y = $margem + $linha * $escala;
                        imagefilledrectangle($imagem, $x, $y, $x + $escala - 1, $y + $escala - 1, $preto);
                    }
                }
            }

            ob_start();
            imagepng($imagem, null, 9);
            $png = (string) ob_get_clean();
        } catch (\Throwable) {
            return '';
        }

        return $png !== '' ? 'data:image/png;base64,' . base64_encode($png) : '';
    }

    /**
     * Gera PDF do DANFSe a partir do Response da NFSe
     *
     * @param Response $response Response com o XML autorizado da NFS-e
     * @param array $dadosAdicionais Dados extras que complementam/substituem os extraídos do XML
     * @param bool $canceled Quando true, aplica a marca d'água "CANCELADA" (vermelha, diagonal).
     *                       O status de cancelamento não consta no XML autorizado; quem usa a
     *                       biblioteca precisa informá-lo (ex.: ERP com nfse_cod_status = 135).
     */
    public function gerar(Response $response, array $dadosAdicionais = [], bool $canceled = false): string
    {
        if (!$response->sucesso || !$response->xml) {
            throw new \InvalidArgumentException('Response inválido ou sem XML da NFSe');
        }

        $dados = $this->extrairDados($response->xml);
        $dados = array_merge($dados, $dadosAdicionais);

        $html = $this->gerarHTML($dados);

        // A marca d'água é configurada no mesmo mPDF que monta o DANFSe, antes do WriteHTML
        // (que cria a página); não se reabre o PDF pronto para carimbar.
        $this->pdf->showWatermarkText = $canceled;
        if ($canceled) {
            $this->pdf->SetWatermarkText(new \Mpdf\WatermarkText('CANCELADA', 120, 50, '#fb0000', 0.4));
        }

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
            table.cab td {
                border: 0;
                padding: 1px 2px;
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
            .text-center {
                text-align: center;
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

        // Layout de referência do portal nacional: logo | título | brasão + prefeitura | QR Code
        $logo = $this->imagemEmbutida('nfse-logo.png');
        $qrCode = $this->qrCodeConsulta((string) ($dados['chaveAcesso'] ?? ''));
        $municipio = trim((string) ($dados['municipioEmissor'] ?? ''));

        $html = '<table class="cab" style="width: 100%; border: 1px solid #000; margin-bottom: 0;"><tr>';

        $html .= '<td style="width: 27%; vertical-align: middle; padding: 3px 4px;">';
        if ($logo !== '') {
            $html .= '<img src="' . $logo . '" style="height: 10mm;" />';
        }
        $html .= '</td>';

        $html .= '<td style="width: 31%; text-align: center; vertical-align: middle; font-weight: bold; font-size: 9pt;">';
        $html .= 'DANFSe v1.0<br>Documento Auxiliar da NFS-e';
        $html .= '</td>';

        $html .= '<td style="width: 30%; vertical-align: middle; padding: 3px 4px;">';
        if ($this->brasao !== null || $municipio !== '') {
            $html .= '<table class="cab" style="width: 100%;"><tr>';
            if ($this->brasao !== null) {
                $html .= '<td style="width: 13mm; vertical-align: middle;">';
                $html .= '<img src="data:image/png;base64,' . base64_encode($this->brasao) . '" style="height: 12mm;" />';
                $html .= '</td>';
            }
            if ($municipio !== '') {
                $html .= '<td style="vertical-align: middle; font-size: 7pt; font-weight: bold;">';
                $html .= 'Prefeitura Municipal de ' . htmlspecialchars($municipio, ENT_QUOTES, 'UTF-8');
                $html .= '</td>';
            }
            $html .= '</tr></table>';
        }
        $html .= '</td>';

        $html .= '<td style="width: 12%; text-align: center; vertical-align: middle; padding: 2px;">';
        if ($qrCode !== '') {
            $html .= '<img src="' . $qrCode . '" style="width: 18mm; height: 18mm;" /><br>';
            $html .= '<span style="font-size: 4.5pt;">A autenticidade desta NFS-e pode ser verificada pela leitura deste código QR ou pela consulta da chave de acesso no portal nacional da NFS-e</span>';
        }
        $html .= '</td>';

        $html .= '</tr></table>';

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
        $html .= '<td class="text-center">' . $numero . '</td>';
        $html .= '<td class="text-center">' . $competencia . '</td>';
        $html .= '<td class="text-center">' . $dataEmissao . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<th>Número da DPS</th>';
        $html .= '<th>Série da DPS</th>';
        $html .= '<th>Data e Hora da emissão da DPS</th>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td class="text-center">' . $numeroDps . '</td>';
        $html .= '<td class="text-center">' . $serieDps . '</td>';
        $html .= '<td class="text-center">' . $dataEmissaoDps . '</td>';
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
        // Removido campo "Regime Tributário" conforme solicitado
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
        $html .= '<td class="text-center">' . htmlspecialchars($cTribNac, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td class="text-center">' . htmlspecialchars($cTribMun, ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td class="text-center">' . htmlspecialchars($localPrestacao, ENT_QUOTES, 'UTF-8') . '</td>';
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
        // tpRetISSQN: 1 = não retido; 2 = retido pelo tomador; 3 = retido pelo intermediário
        $issRetido = in_array((int) ($tribMun['issRetido'] ?? 0), [2, 3], true) ? 'Sim' : 'Não';
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
        $html .= '<td class="text-center">' . $aliquota . '</td>';
        $html .= '<td class="text-center">' . $issRetido . '</td>';
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
        // Garantir que valorServico não fique zerado - usar valorTotal como fallback
        $vServ = $dados['valorServico'] ?? $dados['vServ'] ?? 0;
        $vLiq = $dados['valorTotal'] ?? $dados['vLiq'] ?? 0;

        // Se valorServico estiver zerado mas valorTotal não, usar valorTotal
        if ($vServ == 0 && $vLiq > 0) {
            $vServ = $vLiq;
        }

        $vServ = $this->formatarMoeda($vServ);
        $vLiq = $this->formatarMoeda($vLiq);

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

            // Município emissor (nome), usado no cabeçalho "Prefeitura Municipal de ..."
            $xLocEmiNodes = $xpath->query('//sped:infNFSe/sped:xLocEmi | //infNFSe/xLocEmi');
            $dados['municipioEmissor'] = $xLocEmiNodes->length > 0 ? trim($xLocEmiNodes->item(0)->nodeValue) : '';

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
                // IM é opcional em infNFSe/emit; usa o IM da DPS (prest) quando faltar
                $imDps = $prestadorNodes->length > 0
                    ? $this->getNodeValue($xpath, $prestadorNodes->item(0), './/sped:IM | .//IM')
                    : '';

                $dados['prestador'] = [
                    'cnpj' => $this->getNodeValue($xpath, $prest, './/sped:CNPJ | .//CNPJ'),
                    'im' => $this->getNodeValue($xpath, $prest, './/sped:IM | .//IM') ?: $imDps,
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
            // valores da DPS (vServ, trib); os de infNFSe (vLiq, vBC, pAliqAplic, vISSQN) são lidos à parte.
            // No XML autorizado a DPS vem aninhada em infNFSe (NFSe/infNFSe/DPS/infDPS), por isso o
            // filtro é pelo pai infDPS e não pela ausência do ancestral infNFSe.
            $valoresNodes = $xpath->query('//sped:infDPS/sped:valores | //infDPS/valores');
            if ($valoresNodes->length > 0) {
                $valores = $valoresNodes->item(0);
                $dados['vServ'] = (float)$this->getNodeValue($xpath, $valores, './/sped:vServ | .//vServ');
                $dados['valorServico'] = $dados['vServ'];

                // Fallback: Se não encontrou dentro de valores, tentar busca global (útil quando vServ está dentro de vServPrest ou outros nós)
                if ($dados['valorServico'] <= 0) {
                    $vServGlobal = $xpath->query('//sped:vServ | //vServ');
                    if ($vServGlobal->length > 0) {
                        $dados['vServ'] = (float)trim($vServGlobal->item(0)->nodeValue);
                        $dados['valorServico'] = $dados['vServ'];
                    }
                }

                // Totais e ISSQN ficam em infNFSe/valores (TCValoresNFSe), não dentro de trib
                $valoresNfseNodes = $xpath->query('//sped:infNFSe/sped:valores | //infNFSe/valores');
                $valoresNfse = $valoresNfseNodes->length > 0 ? $valoresNfseNodes->item(0) : $valores;

                $vLiqNodes = $xpath->query('.//sped:vLiq | .//vLiq', $valoresNfse);
                if ($vLiqNodes->length > 0) {
                    $dados['vLiq'] = (float)trim($vLiqNodes->item(0)->nodeValue);
                    $dados['valorTotal'] = $dados['vLiq'];
                }

                // Fallback para Valor Líquido/Total: se não encontrou vLiq, usar vServ
                if (!isset($dados['valorTotal']) || $dados['valorTotal'] <= 0) {
                    if ($dados['valorServico'] > 0) {
                        $dados['vLiq'] = $dados['valorServico'];
                        $dados['valorTotal'] = $dados['valorServico'];
                    }
                }

                $vTotalRetNodes = $xpath->query('.//sped:vTotalRet | .//vTotalRet', $valoresNfse);
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
                            'aliquota' => (float)$this->getNodeValue($xpath, $tribMun, './/sped:pAliq | .//pAliq'),
                        ];

                        // Dados de ISSQN calculados no XML autorizado (infNFSe/valores)
                        $aliqAplic = (float)$this->getNodeValue($xpath, $valoresNfse, './/sped:pAliqAplic | .//pAliqAplic');
                        if ($aliqAplic > 0) {
                            $dados['tributacao']['tribMun']['aliquota'] = $aliqAplic;
                        }
                        $dados['tributacao']['tribMun']['bcIssqn'] = (float)$this->getNodeValue($xpath, $valoresNfse, './/sped:vBC | .//vBC');
                        $dados['tributacao']['tribMun']['issApurado'] = (float)$this->getNodeValue($xpath, $valoresNfse, './/sped:vISSQN | .//vISSQN');
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
        if (!empty($endereco['xLgr'])) {
            $parts[] = $endereco['xLgr'];
        }
        if (!empty($endereco['nro'])) {
            $parts[] = $endereco['nro'];
        }
        if (!empty($endereco['xCpl'])) {
            $parts[] = $endereco['xCpl'];
        }
        if (!empty($endereco['xBairro'])) {
            $parts[] = $endereco['xBairro'];
        }
        if (!empty($endereco['CEP'])) {
            $parts[] = 'CEP: ' . $this->formatarCep($endereco['CEP']);
        }

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
