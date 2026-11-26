<?php

declare(strict_types=1);

namespace NFSeNacional\Validator;

/**
 * Esquemas XSD oficiais do Portal Nacional de NFS-e (NT 009 v1.01).
 *
 * Os arquivos ficam em src/Schemas e vêm do pacote oficial
 * "esquemas-xsd-producao-restrita" (24/11/2026).
 */
enum SchemaType: string
{
    case DPS = 'DPS_v1.01';
    case NFSE = 'NFSe_v1.01';
    case PEDIDO_REGISTRO_EVENTO = 'pedRegEvento_v1.01';
}
