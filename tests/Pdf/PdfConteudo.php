<?php

declare(strict_types=1);

namespace NFSeNacional\Tests\Pdf;

/**
 * Utilitário de teste: concatena os streams (Flate) de um PDF gerado pelo mPDF para
 * inspecionar os operadores de desenho, sem depender de pdftotext ou de outro leitor de PDF.
 */
final class PdfConteudo
{
    public static function descomprimido(string $pdf): string
    {
        preg_match_all('/stream\r?\n(.*?)endstream/s', $pdf, $streams);

        $conteudo = '';
        foreach ($streams[1] as $stream) {
            $descomprimido = @gzuncompress($stream);
            if ($descomprimido !== false) {
                $conteudo .= $descomprimido . "\n";
            }
        }

        return $conteudo;
    }

    /** O stream da marca d'água "CANCELADA" (texto vermelho #fb0000 rotacionado em 50°, opacidade 0,4). */
    public static function temMarcaCancelada(string $pdf): bool
    {
        $conteudo = self::descomprimido($pdf);

        // O mPDF escreve o texto em UTF-16BE: (\0C\0A\0N\0C\0E\0L\0A\0D\0A)
        $textoVermelho = '/0\.984 0\.000 0\.000 rg BT [\d.\-]+ [\d.\-]+ Td '
            . '\(\x00?C\x00?A\x00?N\x00?C\x00?E\x00?L\x00?A\x00?D\x00?A\)/';

        return preg_match($textoVermelho, $conteudo) === 1
            && str_contains($conteudo, '0.64279 0.76604 -0.76604 0.64279')
            && preg_match('/\/ca 0?\.4\b/', $pdf) === 1;
    }
}
