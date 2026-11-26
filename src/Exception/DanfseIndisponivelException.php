<?php

declare(strict_types=1);

namespace NFSeNacional\Exception;

/**
 * A API oficial de download do DANFSe (ADN, GET /danfse/{chave}) está indisponível.
 *
 * O serviço foi descontinuado em 03/08/2026 (NT 008 v1.02): o ADN responde 5xx, recusa o
 * handshake TLS ou nem aceita a conexão. Quem chama deve gerar o DANFSe localmente
 * (veja Tools::obterDanfse() e DanfseGeneratorNacional) em vez de tentar de novo.
 *
 * Estende CommunicationException, então os "catch" existentes continuam funcionando.
 */
class DanfseIndisponivelException extends CommunicationException
{
}
