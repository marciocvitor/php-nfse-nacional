<?php

declare(strict_types=1);

namespace NFSeNacional\Tools;

use NFSeNacional\Config\Config;
use NFSeNacional\Common\Response;

/**
 * Classe principal para comunicação com o WebService da NFSe Nacional
 * 
 * @package NFSeNacional\Tools
 */
class Tools
{
    public function __construct(
        private readonly Config $config
    ) {}
    
    /**
     * Envia uma DPS individual (SÍNCRONO)
     * Retorna a NFSe imediatamente na mesma requisição
     * 
     * @param string $xml XML da DPS
     * @return Response Resposta com NFSe gerada
     */
    public function enviarDps(string $xml): Response
    {
        // TODO: Implementar envio real para o webservice
        return new Response(
            sucesso: true,
            mensagem: 'DPS enviado com sucesso (modo demonstração - SÍNCRONO)',
            numero: '12345',
            codigoVerificacao: 'ABC123',
            xml: $xml
        );
    }
    
    /**
     * Envia lote de DPS (ASSÍNCRONO)
     * Retorna protocolo para consulta posterior
     * 
     * @param array $listaDps Array com XMLs das DPS
     * @param string $numeroLote Número identificador do lote
     * @return Response Resposta com protocolo do lote
     */
    public function enviarLoteDps(array $listaDps, string $numeroLote): Response
    {
        if (empty($listaDps)) {
            return new Response(
                sucesso: false,
                mensagem: 'Lista de DPS vazia'
            );
        }
        
        if (count($listaDps) > 50) {
            return new Response(
                sucesso: false,
                mensagem: 'Limite máximo de 50 DPS por lote excedido'
            );
        }
        
        // Gerar XML do lote
        $xmlLote = $this->gerarXmlLote($listaDps, $numeroLote);
        
        // TODO: Implementar envio real para o webservice
        $protocolo = 'PROT' . date('YmdHis') . rand(1000, 9999);
        
        return new Response(
            sucesso: true,
            mensagem: 'Lote enviado com sucesso. Use o protocolo para consultar o resultado.',
            dados: [
                'protocolo' => $protocolo,
                'numeroLote' => $numeroLote,
                'quantidadeDps' => count($listaDps),
                'dataEnvio' => date('Y-m-d H:i:s')
            ]
        );
    }
    
    /**
     * Consulta resultado de um lote pelo protocolo (ASSÍNCRONO)
     * 
     * @param string $protocolo Protocolo retornado no envio do lote
     * @return Response Resposta com status e NFSes geradas
     */
    public function consultarLote(string $protocolo): Response
    {
        // TODO: Implementar consulta real
        
        // Simula processamento
        return new Response(
            sucesso: true,
            mensagem: 'Lote processado com sucesso',
            dados: [
                'protocolo' => $protocolo,
                'situacao' => 'Processado',
                'quantidadeProcessada' => 3,
                'quantidadeComSucesso' => 3,
                'quantidadeComErro' => 0,
                'nfses' => [
                    [
                        'numero' => '10001',
                        'codigoVerificacao' => 'ABC123',
                        'dataEmissao' => date('Y-m-d H:i:s')
                    ],
                    [
                        'numero' => '10002',
                        'codigoVerificacao' => 'DEF456',
                        'dataEmissao' => date('Y-m-d H:i:s')
                    ],
                    [
                        'numero' => '10003',
                        'codigoVerificacao' => 'GHI789',
                        'dataEmissao' => date('Y-m-d H:i:s')
                    ]
                ]
            ]
        );
    }
    
    /**
     * Consulta NFSe individual por número (SÍNCRONO)
     * 
     * @param string $numero Número da NFSe
     * @return Response
     */
    public function consultarNfse(string $numero): Response
    {
        // TODO: Implementar consulta real
        return new Response(
            sucesso: true,
            mensagem: 'Consulta realizada (modo demonstração)',
            dados: ['numero' => $numero]
        );
    }
    
    /**
     * Consulta NFSes por NSU (LOTE)
     * Retorna até 50 documentos a partir do último NSU
     * 
     * @param int $ultimoNsu Último NSU recebido (0 para começar do início)
     * @param int $limite Quantidade máxima a retornar (max: 50)
     * @return Response
     */
    public function consultarPorNsu(int $ultimoNsu = 0, int $limite = 50): Response
    {
        if ($limite > 50) {
            $limite = 50;
        }
        
        // TODO: Implementar consulta real
        return new Response(
            sucesso: true,
            mensagem: "Consultados até {$limite} documentos a partir do NSU {$ultimoNsu}",
            dados: [
                'ultimoNsu' => $ultimoNsu,
                'proximoNsu' => $ultimoNsu + $limite,
                'quantidadeRetornada' => 2,
                'documentos' => [
                    [
                        'nsu' => $ultimoNsu + 1,
                        'numero' => '20001',
                        'tipo' => 'NFSe'
                    ],
                    [
                        'nsu' => $ultimoNsu + 2,
                        'numero' => '20002',
                        'tipo' => 'NFSe'
                    ]
                ]
            ]
        );
    }
    
    /**
     * Cancela NFSe individual (SÍNCRONO)
     * 
     * @param string $numero Número da NFSe
     * @param string $motivo Motivo do cancelamento
     * @return Response
     */
    public function cancelarNfse(string $numero, string $motivo): Response
    {
        // TODO: Implementar cancelamento real
        return new Response(
            sucesso: true,
            mensagem: 'NFSe cancelada (modo demonstração)',
            dados: ['numero' => $numero, 'motivo' => $motivo]
        );
    }
    
    /**
     * Gera XML do lote de DPS
     * 
     * @param array $listaDps
     * @param string $numeroLote
     * @return string
     */
    private function gerarXmlLote(array $listaDps, string $numeroLote): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<EnviarLoteRpsEnvio xmlns="http://www.abrasf.org.br/nfse.xsd">';
        $xml .= '<LoteRps Id="' . $numeroLote . '" versao="1.00">';
        $xml .= '<NumeroLote>' . $numeroLote . '</NumeroLote>';
        $xml .= '<CpfCnpj><Cnpj>' . $this->config->cnpj . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . $this->config->im . '</InscricaoMunicipal>';
        $xml .= '<QuantidadeRps>' . count($listaDps) . '</QuantidadeRps>';
        $xml .= '<ListaRps>';
        
        foreach ($listaDps as $dps) {
            $xml .= $dps;
        }
        
        $xml .= '</ListaRps>';
        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';
        
        return $xml;
    }
}
